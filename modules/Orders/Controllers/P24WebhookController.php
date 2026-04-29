<?php

declare(strict_types=1);

namespace Modules\Orders\Controllers;

use App\Exceptions\ClientErrorException;
use App\Exceptions\ServerErrorException;
use App\Http\Controllers\ApiController;
use App\Support\IntegrationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Jobs\PushOrderToBackofficeJob;
use Modules\Orders\Models\Order;
use Modules\Orders\Requests\P24WebhookRequest;
use Modules\Orders\Services\P24Client;
use Modules\Orders\Services\P24VerifyParams;

/**
 * @tags [Orders] P24 Webhook
 */
class P24WebhookController extends ApiController
{
    public function __construct(
        protected readonly P24Client $p24Client,
    ) {}

    /**
     * P24 IPN endpoint — fires when a customer completes a payment.
     *
     * Flow:
     *   1. Verify HMAC signature (constant-time).
     *   2. Look up the local order by `sessionId` (= our order_number).
     *   3. Skip if already paid (P24 retries the webhook — be idempotent).
     *   4. Sanity-check amount.
     *   5. Call P24 `verify` to confirm the payment server-side.
     *   6. Mark order paid + dispatch backoffice push.
     *
     * @throws ClientErrorException
     * @throws ServerErrorException
     *
     * @response array{message: string}
     */
    public function __invoke(P24WebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $tag = 'p24.webhook #'.($payload['sessionId'] ?? '?');

        // Log the entire P24 notification payload
        IntegrationLogger::inboundRequest($tag, $request);

        if (! $this->p24Client->verifyNotificationSignature($payload)) {
            Log::warning('P24 webhook: invalid signature', [
                'sessionId' => $payload['sessionId'] ?? null,
            ]);
            throw new ClientErrorException('Invalid signature.', 401);
        }

        $order = Order::where('p24_session_id', $payload['sessionId'])->first();

        if (! $order) {
            throw new ClientErrorException('Order not found.', 404);
        }

        // Idempotent — P24 retries the webhook on non-2xx responses.
        if ($order->p24_paid_at !== null) {
            return $this->success(message: 'Already processed.');
        }

        if ($order->total_amount_pln !== (int) $payload['amount']) {
            Log::error('P24 webhook: amount mismatch', [
                'orderId' => $order->id,
                'expected' => $order->total_amount_pln,
                'received' => $payload['amount'],
            ]);
            throw new ClientErrorException('Amount mismatch.', 422);
        }

        $verified = $this->p24Client->verify(new P24VerifyParams(
            sessionId: (string) $payload['sessionId'],
            orderId: (int) $payload['orderId'],
            amountGrosze: (int) $payload['amount'],
        ));

        if (! $verified) {
            throw new ServerErrorException('P24 verify did not return success.', 502);
        }

        // Store the full P24 notification (without sign — it's a one-time HMAC)
        $notificationPayload = collect($payload)->except('sign')->all();

        $order->forceFill([
            'p24_order_id' => (int) $payload['orderId'],
            'p24_notification_payload' => $notificationPayload,
            'p24_paid_at' => now(),
            'status' => OrderStatusEnum::Paid,
        ])->save();

        // Now that the payment is confirmed, push to backoffice.
        PushOrderToBackofficeJob::dispatch($order->id);

        return $this->success(message: 'Payment confirmed.');
    }
}
