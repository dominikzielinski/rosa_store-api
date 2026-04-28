<?php

declare(strict_types=1);

use App\Exceptions\ClientErrorException;
use App\Exceptions\ServerErrorException;
use App\Http\Middleware\VerifyBackofficeToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'backoffice' => VerifyBackofficeToken::class,
            'log.integration' => \App\Http\Middleware\LogInboundIntegration::class,
        ]);

        // Trust reverse-proxy headers so $request->ip() returns the real client IP.
        // CRITICAL for rate limiters (orders/contact) when running behind Cloudflare/Nginx —
        // without this every request looks like it comes from the proxy, breaking per-IP throttle.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain 4xx — don't pollute Sentry/log; client did something wrong.
        $exceptions->dontReport([
            ClientErrorException::class,
            ValidationException::class,
        ]);

        $renderJson = static fn (Request $request): bool => $request->expectsJson() || $request->is('api/*');

        $exceptions->render(function (ClientErrorException $e, Request $request) use ($renderJson) {
            return $renderJson($request)
                ? response()->json(['message' => $e->getMessage()], $e->getStatusCode())
                : null;
        });

        $exceptions->render(function (ServerErrorException $e, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }
            // Don't leak internal misconfiguration details to clients in production.
            $message = app()->isProduction()
                ? 'Wystąpił błąd serwera. Spróbuj ponownie za chwilę.'
                : $e->getMessage();

            return response()->json(['message' => $message], $e->getStatusCode());
        });

        // Uniform 422 validation payload
        $exceptions->render(function (ValidationException $e, Request $request) use ($renderJson) {
            return $renderJson($request)
                ? response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422)
                : null;
        });
    })->create();
