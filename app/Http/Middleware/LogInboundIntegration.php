<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\IntegrationLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs request + response to a per-service log channel for any inbound
 * integration call — webhooks from backoffice / P24, public order/contact
 * submits. Use as route middleware with `<channel>.<sub-tag>` argument:
 *
 *   Route::post('orders', ...)->middleware('log.integration:orders.store');
 *   Route::post('webhook', ...)->middleware('log.integration:p24.webhook');
 *
 * The text before the first dot becomes the folder name in `storage/logs/`,
 * so the example above writes to:
 *   storage/logs/orders/laravel-YYYY-MM-DD.log
 *   storage/logs/p24/laravel-YYYY-MM-DD.log
 */
class LogInboundIntegration
{
    public function handle(Request $request, Closure $next, string $tag = 'inbound'): Response
    {
        IntegrationLogger::inboundRequest($tag, $request);

        $start = microtime(true);
        $response = $next($request);

        IntegrationLogger::inboundResponse(
            $tag,
            $response->getStatusCode(),
            microtime(true) - $start,
            $response->getContent(),
        );

        return $response;
    }
}
