<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\IntegrationLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs request + response to the `integrations` log channel for any inbound
 * integration call — webhooks od backoffice / P24, public order/contact
 * submits. Use as a route middleware:
 *
 *   Route::post('orders', ...)->middleware('log.integration:orders.store');
 *
 * The optional argument is the tag that will appear in the log line so a
 * grep-by-tag pulls everything related to one integration.
 */
class LogInboundIntegration
{
    public function handle(Request $request, Closure $next, string $tag = 'inbound'): Response
    {
        IntegrationLogger::inboundRequest($tag, $request);

        $start = microtime(true);
        $response = $next($request);

        IntegrationLogger::inboundResponse(
            $response->getStatusCode(),
            microtime(true) - $start,
            $response->getContent(),
        );

        return $response;
    }
}
