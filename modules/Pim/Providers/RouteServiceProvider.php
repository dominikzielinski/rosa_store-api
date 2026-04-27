<?php

declare(strict_types=1);

namespace Modules\Pim\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as Base;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends Base
{
    public function boot(): void
    {
        $this->routes(function (): void {
            // Public read endpoints — throttled to deter scraping
            Route::middleware(['api', 'throttle:60,1'])
                ->name('pim.')
                ->prefix('api/packages')
                ->group(__DIR__.'/../routes/api.php');

            // Panel webhook — backoffice signals a PIM change, shop pulls feed
            Route::middleware(['api', 'backoffice'])
                ->name('panel.')
                ->prefix('api/panel')
                ->group(__DIR__.'/../Sync/routes/panel.php');
        });
    }
}
