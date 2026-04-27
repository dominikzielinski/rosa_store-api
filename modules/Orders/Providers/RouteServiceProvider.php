<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as Base;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends Base
{
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::middleware('api')
                ->name('orders.')
                ->prefix('api/orders')
                ->group(__DIR__.'/../routes/api.php');

            // P24 IPN webhook — auth via HMAC signature in payload, not Bearer.
            Route::middleware('api')
                ->prefix('api/p24')
                ->group(__DIR__.'/../routes/p24.php');
        });
    }
}
