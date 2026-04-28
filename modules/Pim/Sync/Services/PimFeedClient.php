<?php

declare(strict_types=1);

namespace Modules\Pim\Sync\Services;

use App\Exceptions\ServerErrorException;
use App\Support\IntegrationLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Read-only HTTP client for backoffice's PIM feed.
 *
 * Endpoints (per `_docs/api-specs/shop-pim-feed.md`):
 *   GET /api/shop/pim/packages
 *   GET /api/shop/pim/packages/{id}
 *   GET /api/shop/pim/boxes
 *   GET /api/shop/pim/boxes/{id}
 *
 * Auth: same Bearer token used everywhere else (`backoffice.token`).
 */
readonly class PimFeedClient
{
    public function __construct(
        protected HttpFactory $http,
    ) {}

    /**
     * Returns null when the entity is not active in backoffice (404) — caller
     * treats as "soft delete locally". Throws on 5xx / connection errors so
     * the queue can retry.
     *
     * @return array<string, mixed>|null
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getPackage(int $backofficeId): ?array
    {
        return $this->getOne('pim_package', ['{id}' => (string) $backofficeId]);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getBox(int $backofficeId): ?array
    {
        return $this->getOne('pim_box', ['{id}' => (string) $backofficeId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function listPackages(): array
    {
        return $this->getList('pim_packages');
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function listBoxes(): array
    {
        return $this->getList('pim_boxes');
    }

    /**
     * @param  array<string, string>  $replace
     * @return array<string, mixed>|null
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    private function getOne(string $pathKey, array $replace): ?array
    {
        $url = $this->buildUrl($pathKey, $replace);
        $tag = "pim.{$pathKey}";

        IntegrationLogger::outboundRequest($tag, 'GET', $url);
        $start = microtime(true);

        try {
            $response = $this->client()->get($url);
        } catch (Throwable $e) {
            IntegrationLogger::outboundError($tag, $e);
            throw $e;
        }

        IntegrationLogger::outboundResponse($response, microtime(true) - $start);

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        $body = $response->json();
        $data = is_array($body) && isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ServerErrorException
     * @throws ConnectionException
     * @throws RequestException
     */
    private function getList(string $pathKey): array
    {
        $url = $this->buildUrl($pathKey, []);
        $tag = "pim.{$pathKey}";

        IntegrationLogger::outboundRequest($tag, 'GET', $url);
        $start = microtime(true);

        try {
            $response = $this->client()->get($url);
        } catch (Throwable $e) {
            IntegrationLogger::outboundError($tag, $e);
            throw $e;
        }

        IntegrationLogger::outboundResponse($response, microtime(true) - $start);
        $response->throw();

        $body = $response->json();
        $items = is_array($body) && isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private function client(): PendingRequest
    {
        $token = (string) config('backoffice.token');

        if ($token === '') {
            throw new ServerErrorException('Backoffice token not configured.', 503);
        }

        return $this->http
            ->withToken($token)
            ->acceptJson()
            ->timeout((int) config('backoffice.timeout', 10));
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function buildUrl(string $pathKey, array $replace): string
    {
        $base = (string) config('backoffice.url');
        $path = (string) config("backoffice.paths.{$pathKey}");

        if ($base === '' || $path === '') {
            throw new ServerErrorException("Backoffice path '{$pathKey}' not configured.", 503);
        }

        if ($replace !== []) {
            $path = strtr($path, $replace);
        }

        $url = rtrim($base, '/').$path;

        if (app()->isProduction() && ! str_starts_with($url, 'https://')) {
            throw new ServerErrorException('Backoffice URL must use HTTPS in production.', 503);
        }

        return $url;
    }
}
