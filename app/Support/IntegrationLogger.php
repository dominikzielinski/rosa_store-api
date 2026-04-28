<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\LogInboundIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Per-service log writer for inter-service traffic.
 *
 * Tag convention: `<channel>.<sub-tag>`. The text before the first dot becomes
 * the folder name in `storage/logs/`, so:
 *   `p24.register #RD-1`        → storage/logs/p24/laravel-YYYY-MM-DD.log
 *   `backoffice.pushOrder ...`  → storage/logs/backoffice/laravel-...log
 *   `pim.pim_packages`          → storage/logs/pim/laravel-...log
 *   `orders.store`              → storage/logs/orders/laravel-...log
 *
 * Format mirrors rosa/wms ApiRepository:
 *   ~~~~~~~~~~
 *   {request body / params}
 *   [METHOD][URL]
 *   Czas: 0.1234 s
 *   Status: 200
 *   {response body}
 *
 * Authorization headers are NOT logged so a leaked log file doesn't compromise
 * the bearer token.
 */
class IntegrationLogger
{
    private const SEPARATOR = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function outboundRequest(string $tag, string $method, string $url, ?array $payload = null): void
    {
        $log = self::log($tag);
        $log->info(self::SEPARATOR);
        $log->info("OUT [{$tag}]");
        $log->info("[{$method}][{$url}]");
        if ($payload !== null) {
            $log->info(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public static function outboundResponse(string $tag, Response $response, float $durationSeconds): void
    {
        $log = self::log($tag);
        $log->info('Czas: '.round($durationSeconds, 4).' s');
        $log->info('Status: '.$response->status());
        $log->info($response->body());
    }

    public static function outboundError(string $tag, Throwable $e): void
    {
        self::log($tag)->error("OUT [{$tag}] FAILED: ".$e::class.': '.$e->getMessage());
    }

    /**
     * Log the inbound side — used by {@see LogInboundIntegration}.
     */
    public static function inboundRequest(string $tag, Request $request): void
    {
        $log = self::log($tag);
        $log->info(self::SEPARATOR);
        $log->info("IN  [{$tag}] from {$request->ip()}");
        $log->info('['.strtoupper($request->method()).']['.$request->fullUrl().']');

        $body = $request->getContent();
        if ($body !== '') {
            $log->info($body);
        }
    }

    public static function inboundResponse(string $tag, int $status, float $durationSeconds, mixed $body = null): void
    {
        $log = self::log($tag);
        $log->info('Czas: '.round($durationSeconds, 4).' s');
        $log->info('Status: '.$status);
        if ($body !== null && $body !== '') {
            $log->info(is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Build a daily channel under `storage/logs/{channel}/`. The channel name
     * is the text before the first dot in the tag (defaults to `default` if
     * no dot is present).
     */
    private static function log(string $tag): LoggerInterface
    {
        $channel = self::resolveChannel($tag);

        return Log::build([
            'driver' => 'daily',
            'path' => storage_path("logs/{$channel}/laravel.log"),
            'level' => 'info',
            'days' => (int) env('LOG_INTEGRATIONS_DAYS', 14),
            'replace_placeholders' => true,
        ]);
    }

    private static function resolveChannel(string $tag): string
    {
        $first = explode('.', $tag, 2)[0] ?? '';
        $sanitized = preg_replace('/[^a-z0-9_-]/i', '', $first) ?? '';

        return $sanitized !== '' ? strtolower($sanitized) : 'default';
    }
}
