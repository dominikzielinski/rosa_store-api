<?php

declare(strict_types=1);

namespace Modules\Contact\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as Base;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends Base
{
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::middleware('api')
                ->name('contact.')
                ->prefix('api/contact')
                ->group(__DIR__.'/../routes/api.php');
        });
    }
}
