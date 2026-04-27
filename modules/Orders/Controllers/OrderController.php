<?php

declare(strict_types=1);

namespace Modules\Orders\Controllers;

use App\Exceptions\ClientErrorException;
use App\Exceptions\ServerErrorException;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Orders\DataTransferObjects\OrderStoreDto;
use Modules\Orders\Enums\PaymentMethodEnum;
use Modules\Orders\Models\Order;
use Modules\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use Modules\Orders\Requests\OrderStoreRequest;
use Modules\Orders\Resources\OrderCreatedResource;
use Modules\Orders\Resources\OrderStatusResource;
use Modules\Orders\Services\OrderService;
use Modules\Orders\Services\P24Client;
use Modules\Orders\Services\P24RegisterParams;

/**
 * @tags [Orders]
 */
class OrderController extends ApiController
{
    public function __construct(
        protected readonly OrderService $orderService,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly P24Client $p24Client,
    ) {}

    /**
     * Place a new order.
     *
     * Server-side calculates total from current pim_boxes prices — FE prices are
     * ignored (anti-tamper). Returns the order number + redirect URL the FE should
     * navigate to. For `transfer` it points at /dziekujemy on the shop; for `p24`
     * it's the P24-hosted payment page (after registering the transaction).
     *
     * @throws \Throwable
     *
     * @response array{data: OrderCreatedResource, message: string}
     */
    public function store(OrderStoreRequest $request): JsonResponse
    {
        // Honeypot — pretend success without persisting anything. Bots get no feedback.
        if ($request->looksLikeBot()) {
            $fakeNumber = 'RD-00000000';
            $redirectUrl = $this->buildShopRedirectUrl($fakeNumber, PaymentMethodEnum::Transfer);

            return $this->created(
                ['orderNumber' => $fakeNumber, 'redirectUrl' => $redirectUrl, 'paymentMethod' => PaymentMethodEnum::Transfer->getData(), 'totalAmountPln' => 0],
                'Zamówienie przyjęte.',
            );
        }

        $dto = OrderStoreDto::fromRequest($request);
        $order = $this->orderService->store($dto);

        $redirectUrl = $dto->paymentMethod === PaymentMethodEnum::P24
            ? $this->registerP24Transaction($order)
            : $this->buildShopRedirectUrl($order->order_number, $dto->paymentMethod);

        return $this->created(
            new OrderCreatedResource($order, $redirectUrl),
            'Zamówienie przyjęte.',
        );
    }

    /**
     * Read-only public status check — used by FE polling on /platnosc/return.
     *
     * @throws ClientErrorException on unknown order number (404)
     *
     * @response array{data: OrderStatusResource}
     */
    public function status(string $orderNumber): JsonResponse
    {
        $order = $this->orderRepository->getByOrderNumber($orderNumber);

        return $this->success(new OrderStatusResource($order));
    }

    /**
     * Register the order with P24 and return the hosted checkout URL.
     * On failure the order stays in `pending_payment` and is auto-cancelled
     * by `orders:cancel-stale-p24` after 24h.
     *
     * @throws ServerErrorException
     */
    private function registerP24Transaction(Order $order): string
    {
        $shopUrl = $this->shopFrontendUrl();

        $result = $this->p24Client->register(new P24RegisterParams(
            sessionId: $order->order_number,
            amountGrosze: $order->total_amount_pln,
            description: "Rosa D'oro #{$order->order_number}",
            email: $order->billing_email,
            urlReturn: $shopUrl.'/platnosc/return?order='.$order->order_number,
            urlStatus: route('p24.webhook'),
        ));

        $order->forceFill([
            'p24_session_id' => $order->order_number,
            'p24_token' => $result['token'],
        ])->save();

        return $result['redirectUrl'];
    }

    /**
     * Frontend URL for non-P24 (transfer) redirects.
     *
     * @throws ServerErrorException when shop frontend URL is not configured in production
     */
    private function buildShopRedirectUrl(string $orderNumber, PaymentMethodEnum $method): string
    {
        $params = http_build_query([
            'order' => $orderNumber,
            'method' => $method->value,
        ]);

        return rtrim($this->shopFrontendUrl(), '/')."/dziekujemy?{$params}";
    }
	
	/**
	 * @throws ServerErrorException
	 */
	private function shopFrontendUrl(): string
    {
        $shopUrl = config('app.shop_frontend_url');

        if (! is_string($shopUrl) || $shopUrl === '') {
            if (! app()->isLocal()) {
                throw new ServerErrorException('Shop frontend URL is not configured.');
            }
            $shopUrl = 'http://localhost:3000';
        }

        return $shopUrl;
    }
}
