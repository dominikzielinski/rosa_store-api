<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Module providers handle their own bindings (PIM/CMS/Contact/Orders).
     * This provider only configures app-wide concerns.
     */
    public function register(): void {}

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiters();
    }

    /**
     * Eloquent strict-mode in non-prod (catches lazy loading + missing attributes
     * + silent discards). Auto eager-loading flips lazy queries into eager.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();
    }

    /**
     * Named rate limiters used across API routes.
     */
    private function configureRateLimiters(): void
    {
        // Contact form — public endpoint, 5 submissions per IP per minute
        RateLimiter::for('contact', function (Request $request) {
            $limit = (int) config('contact.rate_limit_per_minute', 5);

            return Limit::perMinute($limit)->by($request->ip());
        });

        // Orders — public endpoint, more lenient than contact since legitimate
        // checkouts can take multiple submission attempts (validation iterations).
        RateLimiter::for('orders', function (Request $request) {
            $limit = (int) config('orders.rate_limit_per_minute', 10);

            return Limit::perMinute($limit)->by($request->ip());
        });
    }
}
