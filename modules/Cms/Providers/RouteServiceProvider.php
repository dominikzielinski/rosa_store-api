<?php

declare(strict_types=1);

namespace Modules\Cms\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as Base;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends Base
{
    public function boot(): void
    {
        $this->routes(function (): void {
            // Public read endpoints — throttled, route names prefixed with `cms.`
            Route::middleware(['api', 'throttle:120,1'])
                ->name('cms.')
                ->prefix('api')
                ->group(__DIR__.'/../routes/api.php');

            // Admin write endpoints (backoffice only) — names prefixed with `admin.cms.`
            // Logged to `integrations` channel since these are inbound BE→shop calls.
            Route::middleware(['api', 'backoffice', 'log.integration:admin.cms'])
                ->name('admin.cms.')
                ->prefix('api/admin')
                ->group(__DIR__.'/../Admin/routes/api.php');
        });
    }
}
