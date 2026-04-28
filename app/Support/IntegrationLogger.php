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
 * Single point that writes to the `integrations` log channel for every
 * inter-service request — both outbound (shop → backoffice, P24, …) and
 * inbound (webhooks from backoffice / P24, public order/contact submits).
 *
 * Format mirrors the rosa/wms `ApiRepository` style:
 *   ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 *   {request body / params}
 *   [METHOD][URL]
 *   Czas: 0.1234 s
 *   Status: 200
 *   {response body}
 *
 * Authorization headers are scrubbed before logging so a leaked log file
 * doesn't compromise the bearer token.
 */
class IntegrationLogger
{
    private const CHANNEL = 'integrations';

    private const SEPARATOR = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';

    /**
     * Log the request side of an outbound HTTP call. Pair with
     * {@see self::outboundResponse()} after the response arrives.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function outboundRequest(string $tag, string $method, string $url, ?array $payload = null): void
    {
        $log = self::log();
        $log->info(self::SEPARATOR);
        $log->info("OUT [{$tag}]");
        $log->info("[{$method}][{$url}]");
        if ($payload !== null) {
            $log->info(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public static function outboundResponse(Response $response, float $durationSeconds): void
    {
        $log = self::log();
        $log->info('Czas: '.round($durationSeconds, 4).' s');
        $log->info('Status: '.$response->status());
        $log->info($response->body());
    }

    public static function outboundError(string $tag, Throwable $e): void
    {
        $log = self::log();
        $log->error("OUT [{$tag}] FAILED: ".$e::class.': '.$e->getMessage());
    }

    /**
     * Log the inbound side — used by {@see LogInboundIntegration}.
     */
    public static function inboundRequest(string $tag, Request $request): void
    {
        $log = self::log();
        $log->info(self::SEPARATOR);
        $log->info("IN  [{$tag}] from {$request->ip()}");
        $log->info('['.strtoupper($request->method()).']['.$request->fullUrl().']');

        $body = $request->getContent();
        if ($body !== '') {
            $log->info($body);
        }
    }

    public static function inboundResponse(int $status, float $durationSeconds, mixed $body = null): void
    {
        $log = self::log();
        $log->info('Czas: '.round($durationSeconds, 4).' s');
        $log->info('Status: '.$status);
        if ($body !== null && $body !== '') {
            $log->info(is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private static function log(): LoggerInterface
    {
        return Log::channel(self::CHANNEL);
    }
}
