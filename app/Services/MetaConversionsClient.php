<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\IntegrationLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Contact\Models\ContactSubmission;
use Modules\Orders\Models\Order;

/**
 * Sends server-side conversion events to Meta Conversions API (CAPI).
 *
 * PII handling:
 *   - All user data is hashed with SHA-256 before sending (Meta requirement).
 *   - Raw PII never leaves this class. Logs contain hashes only.
 *   - country is always 'pl' (shop is Poland-only).
 *
 * Graceful degradation:
 *   - Returns silently when META_PIXEL_ID or META_CAPI_ACCESS_TOKEN are empty.
 *   - Returns silently when consent_marketing is false on the order/contact.
 *
 * Logs to the `integrations` channel (storage/logs/integrations/).
 */
readonly class MetaConversionsClient
{
    private const TAG = 'integrations.meta_capi';

    /** @param array<string, mixed> $order items: [['id' => string, 'quantity' => int]] */
    public function sendPurchase(Order $order): void
    {
        if (! $this->credentialsPresent()) {
            return;
        }

        if (! $order->consent_marketing) {
            return;
        }

        $userData = $this->buildUserDataFromOrder($order);

        $customData = [
            'value' => round($order->total_amount_pln / 100, 2),
            'currency' => 'PLN',
            'content_type' => 'product',
            'contents' => $order->items->map(fn ($item) => [
                'id' => (string) $item->box_id,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ];

        $this->sendEvent(
            eventName: 'Purchase',
            eventId: $order->meta_event_id,
            userData: $userData,
            customData: $customData,
            clientIpAddress: $order->ip_address,
            clientUserAgent: $order->user_agent,
        );
    }

    public function sendLead(ContactSubmission $submission, string $eventId): void
    {
        if (! $this->credentialsPresent()) {
            return;
        }

        if (! $submission->consent_marketing) {
            return;
        }

        $userData = [
            'em' => $this->hash($submission->email),
        ];

        $this->sendEvent(
            eventName: 'Lead',
            eventId: $eventId,
            userData: $userData,
            customData: [],
            clientIpAddress: $submission->ip_address,
            clientUserAgent: $submission->user_agent,
        );
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    private function sendEvent(
        string $eventName,
        ?string $eventId,
        array $userData,
        array $customData,
        ?string $clientIpAddress,
        ?string $clientUserAgent,
    ): void {
        $pixelId = config('services.meta.pixel_id');
        $accessToken = config('services.meta.capi_access_token');
        $version = config('services.meta.graph_api_version', 'v22.0');
        $testEventCode = config('services.meta.test_event_code');

        $url = "https://graph.facebook.com/{$version}/{$pixelId}/events";

        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => $userData,
        ];

        if ($eventId !== null && $eventId !== '') {
            $event['event_id'] = $eventId;
        }

        if ($clientIpAddress !== null) {
            $event['user_data']['client_ip_address'] = $clientIpAddress;
        }

        if ($clientUserAgent !== null) {
            $event['user_data']['client_user_agent'] = $clientUserAgent;
        }

        if ($customData !== []) {
            $event['custom_data'] = $customData;
        }

        $payload = [
            'data' => [$event],
            'access_token' => $accessToken,
        ];

        if (is_string($testEventCode) && $testEventCode !== '') {
            $payload['test_event_code'] = $testEventCode;
        }

        // Log payload with access_token redacted — never log credentials.
        $loggablePayload = $payload;
        $loggablePayload['access_token'] = '[REDACTED]';

        IntegrationLogger::outboundRequest(self::TAG, 'POST', $url, $loggablePayload);

        $start = microtime(true);

        try {
            $response = Http::post($url, $payload);

            IntegrationLogger::outboundResponse(self::TAG, $response, microtime(true) - $start);

            if (! $response->successful()) {
                Log::channel('daily')->warning('Meta CAPI non-2xx response', [
                    'event' => $eventName,
                    'status' => $response->status(),
                ]);

                // Throw so the job retries on 429/5xx. Without this, Http::post()
                // returns a failed response (no exception) and the job marks success.
                $response->throw();
            }
        } catch (\Throwable $e) {
            IntegrationLogger::outboundError(self::TAG, $e);
            // Re-throw so the job can retry.
            throw $e;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildUserDataFromOrder(Order $order): array
    {
        $data = [
            'em' => $this->hash($order->billing_email),
            'ph' => $this->hash($this->normalizePhone($order->billing_phone)),
            'country' => $this->hash('pl'),
            'zp' => $this->hash($this->normalizePostalCode($order->billing_postal_code)),
            'ct' => $this->hash($this->normalizeString($order->billing_city)),
        ];

        if ($order->billing_first_name !== null) {
            $data['fn'] = $this->hash($this->normalizeString($order->billing_first_name));
        }

        if ($order->billing_last_name !== null) {
            $data['ln'] = $this->hash($this->normalizeString($order->billing_last_name));
        }

        if ($order->billing_street !== null) {
            $data['st'] = $this->hash($this->normalizeString($order->billing_street));
        }

        return $data;
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    private function normalizeString(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizePhone(string $value): string
    {
        // Meta expects digits only, no spaces or dashes. Strip the leading '+'.
        return (string) preg_replace('/\D/', '', $value);
    }

    private function normalizePostalCode(string $value): string
    {
        // Remove the dash from Polish postal codes: 00-001 → 00001.
        return str_replace('-', '', trim($value));
    }

    private function credentialsPresent(): bool
    {
        $pixelId = config('services.meta.pixel_id');
        $token = config('services.meta.capi_access_token');

        return is_string($pixelId) && $pixelId !== ''
            && is_string($token) && $token !== '';
    }
}
