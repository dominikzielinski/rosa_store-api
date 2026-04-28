<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use App\Exceptions\ServerErrorException;
use App\Support\IntegrationLogger;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Przelewy24 REST API wrapper.
 *
 * Two flows we use:
 *   1. register()  → POST /api/v1/transaction/register   (returns a `token`)
 *   2. verify()    → PUT  /api/v1/transaction/verify     (called from webhook)
 *
 * Sign is SHA-384 of a deterministically-ordered JSON payload that includes
 * the CRC key. `JSON_UNESCAPED_SLASHES` matters — P24 server-side hashes the
 * raw JSON the same way.
 */
readonly class P24Client
{
    public function __construct(
        protected HttpFactory $http,
    ) {}

    /**
     * Register a new transaction. Returns the P24-hosted payment URL the user
     * should be redirected to.
     *
     * @return array{token: string, redirectUrl: string}
     *
     * @throws ServerErrorException
     */
    public function register(P24RegisterParams $params): array
    {
        $config = $this->config();

        $payload = [
            'merchantId' => $config['merchant_id'],
            'posId' => $config['pos_id'],
            'sessionId' => $params->sessionId,
            'amount' => $params->amountGrosze,
            'currency' => $config['currency'],
            'description' => $params->description,
            'email' => $params->email,
            'country' => $config['country'],
            'language' => $config['language'],
            'urlReturn' => $params->urlReturn,
            'urlStatus' => $params->urlStatus,
            'sign' => $this->signRegister(
                $params->sessionId,
                $params->amountGrosze,
                $config['currency'],
                $config['crc_key'],
            ),
        ];

        $url = $this->baseUrl().'/api/v1/transaction/register';
        $tag = "p24.register #{$params->sessionId}";

        IntegrationLogger::outboundRequest($tag, 'POST', $url, $payload);
        $start = microtime(true);

        try {
            $response = $this->http
                ->withBasicAuth((string) $config['pos_id'], $config['reports_key'])
                ->acceptJson()
                ->timeout((int) $config['timeout'])
                ->post($url, $payload);
        } catch (Throwable $e) {
            IntegrationLogger::outboundError($tag, $e);
            throw $e;
        }

        IntegrationLogger::outboundResponse($tag, $response, microtime(true) - $start);

        if (! $response->successful()) {
            throw new ServerErrorException(
                'P24 register failed: HTTP '.$response->status(),
                502,
            );
        }

        $body = $response->json();
        $token = $body['data']['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ServerErrorException('P24 register: missing token in response.', 502);
        }

        return [
            'token' => $token,
            'redirectUrl' => $this->baseUrl().'/trnRequest/'.$token,
        ];
    }

    /**
     * Verify a transaction after the IPN webhook arrives. P24 will not consider
     * a payment confirmed until the merchant calls verify.
     *
     * @throws ServerErrorException on HTTP/contract failure
     */
    public function verify(P24VerifyParams $params): bool
    {
        $config = $this->config();

        $payload = [
            'merchantId' => $config['merchant_id'],
            'posId' => $config['pos_id'],
            'sessionId' => $params->sessionId,
            'amount' => $params->amountGrosze,
            'currency' => $config['currency'],
            'orderId' => $params->orderId,
            'sign' => $this->signVerify(
                $params->sessionId,
                $params->orderId,
                $params->amountGrosze,
                $config['currency'],
                $config['crc_key'],
            ),
        ];

        $url = $this->baseUrl().'/api/v1/transaction/verify';
        $tag = "p24.verify #{$params->sessionId}";

        IntegrationLogger::outboundRequest($tag, 'PUT', $url, $payload);
        $start = microtime(true);

        try {
            $response = $this->http
                ->withBasicAuth((string) $config['pos_id'], $config['reports_key'])
                ->acceptJson()
                ->timeout((int) $config['timeout'])
                ->put($url, $payload);
        } catch (Throwable $e) {
            IntegrationLogger::outboundError($tag, $e);
            throw $e;
        }

        IntegrationLogger::outboundResponse($tag, $response, microtime(true) - $start);

        if (! $response->successful()) {
            throw new ServerErrorException(
                'P24 verify failed: HTTP '.$response->status(),
                502,
            );
        }

        $status = $response->json('data.status');

        return $status === 'success';
    }

    /**
     * Verify the signature P24 sent in the IPN webhook payload — constant-time
     * comparison against our locally-computed value.
     */
    public function verifyNotificationSignature(array $payload): bool
    {
        $config = $this->config();
        $remote = (string) ($payload['sign'] ?? '');

        $expected = hash('sha384', json_encode([
            'merchantId' => (int) ($payload['merchantId'] ?? 0),
            'posId' => (int) ($payload['posId'] ?? 0),
            'sessionId' => (string) ($payload['sessionId'] ?? ''),
            'amount' => (int) ($payload['amount'] ?? 0),
            'originAmount' => (int) ($payload['originAmount'] ?? 0),
            'currency' => (string) ($payload['currency'] ?? ''),
            'orderId' => (int) ($payload['orderId'] ?? 0),
            'methodId' => (int) ($payload['methodId'] ?? 0),
            'statement' => (string) ($payload['statement'] ?? ''),
            'crc' => $config['crc_key'],
        ], JSON_UNESCAPED_SLASHES));

        return $remote !== '' && hash_equals($expected, $remote);
    }

    private function signRegister(string $sessionId, int $amount, string $currency, string $crc): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'merchantId' => $this->config()['merchant_id'],
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function signVerify(string $sessionId, int $orderId, int $amount, string $currency, string $crc): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function baseUrl(): string
    {
        return $this->config()['sandbox']
            ? 'https://sandbox.przelewy24.pl'
            : 'https://secure.przelewy24.pl';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ServerErrorException
     */
    private function config(): array
    {
        $config = config('p24');

        if (! is_array($config)
            || (int) $config['merchant_id'] === 0
            || (int) $config['pos_id'] === 0
            || $config['reports_key'] === ''
            || $config['crc_key'] === ''
        ) {
            throw new ServerErrorException('P24 not configured.', 503);
        }

        return $config;
    }
}
