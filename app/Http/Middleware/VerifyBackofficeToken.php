<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards all `/api/admin/*` routes — compares the `Authorization: Bearer` token
 * from the request against the shared secret in `config/backoffice.php`.
 *
 * Uses `hash_equals` for constant-time comparison (resistant to timing attacks).
 * Returns 401 on any mismatch without leaking whether the token existed.
 */
class VerifyBackofficeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('backoffice.token');

        if (! is_string($expected) || $expected === '') {
            // Server-side misconfiguration — refuse rather than letting all requests through.
            return response()->json(['message' => 'Backoffice sync not configured.'], 503);
        }

        $provided = $this->extractToken($request);

        if ($provided === null || ! hash_equals($expected, $provided)) {
            Log::warning('Backoffice auth failed', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
