<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use App\Exceptions\ServerErrorException;
use App\Support\IntegrationLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Modules\Orders\Models\Order;
use Throwable;

/**
 * HTTP wrapper for outbound sync to the backoffice app.
 *
 * Uses Laravel HTTP client which is fakeable via Http::fake() — that's how
 * tests run without a real backoffice. Production hits the real URL configured
 * in `config/backoffice.php` outbound section.
 */
readonly class BackofficeClient
{
    public function __construct(
        protected HttpFactory $http,
    ) {}

    /**
     * Push an order to the backoffice. Throws on non-2xx so the calling job
     * can decide retry vs. dead-letter. Returns the parsed JSON body.
     *
     * @return array<string, mixed>
     *
     * @throws ServerErrorException on misconfiguration or unrecoverable error
     * @throws ConnectionException on network failure
     */
    public function pushOrder(Order $order): array
    {
        $base = (string) config('backoffice.url');
        $token = (string) config('backoffice.token');
        $path = (string) config('backoffice.paths.orders');

        if ($base === '' || $token === '' || $path === '') {
            throw new ServerErrorException('Backoffice outbound not configured.', 503);
        }

        $url = rtrim($base, '/').$path;

        if (app()->isProduction() && ! str_starts_with($url, 'https://')) {
            throw new ServerErrorException('Backoffice URL must use HTTPS in production.', 503);
        }

        $payload = $this->buildPayload($order);
        $tag = "backoffice.pushOrder #{$order->order_number}";

        IntegrationLogger::outboundRequest($tag, 'POST', $url, $payload);
        $start = microtime(true);

        try {
            $response = $this->http
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('backoffice.timeout', 10))
                ->post($url, $payload);
        } catch (Throwable $e) {
            IntegrationLogger::outboundError($tag, $e);
            throw $e;
        }

        IntegrationLogger::outboundResponse($tag, $response, microtime(true) - $start);

        // Throws RequestException for 4xx/5xx — caller decides what to do.
        // Job handles retries; non-retryable 4xx logs + DLQ.
        $response->throw();

        return $this->parseResponse($response);
    }

    /**
     * Build the payload matching the contract documented in
     * `_docs/api-specs/backoffice-orders-inbound.md`.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order): array
    {
        return [
            'shopOrderNumber' => $order->order_number,
            'paymentMethod' => $order->payment_method->value,
            'paymentStatus' => $order->p24_paid_at !== null ? 'paid' : 'pending',
            'paymentMeta' => [
                'p24SessionId' => $order->p24_session_id,
                'p24OrderId' => $order->p24_order_id,
                'paidAt' => $order->p24_paid_at?->getTimestamp(),
                'p24Notification' => $order->p24_notification_payload,
            ],
            'totalAmountPln' => $order->total_amount_pln,
            'items' => $order->items->map(fn ($item) => [
                'boxId' => $item->box_id,
                'boxSlug' => $item->box_slug,
                'boxName' => $item->box_name,
                'packageSlug' => $item->package_slug,
                'gender' => $item->gender,
                'quantity' => $item->quantity,
                'unitPricePln' => $item->unit_price_pln,
                'totalPricePln' => $item->total_price_pln,
            ])->all(),
            'billing' => [
                'type' => $order->billing_type->value,
                'firstName' => $order->billing_first_name,
                'lastName' => $order->billing_last_name,
                'companyName' => $order->billing_company,
                'nip' => $order->billing_nip,
                'email' => $order->billing_email,
                'phone' => $order->billing_phone,
                'street' => $order->billing_street,
                'houseNumber' => $order->billing_house_number,
                'postalCode' => $order->billing_postal_code,
                'city' => $order->billing_city,
            ],
            'note' => $order->note,
            'consents' => [
                'terms' => $order->consent_terms,
                'marketing' => $order->consent_marketing,
            ],
            'metadata' => [
                'ipAddress' => $order->ip_address,
                'userAgent' => $order->user_agent,
                'shopCreatedAt' => $order->created_at->getTimestamp(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
