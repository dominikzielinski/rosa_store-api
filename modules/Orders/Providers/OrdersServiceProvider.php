<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Orders\Console\Commands\CancelStalePendingP24OrdersCommand;
use Modules\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use Modules\Orders\Repositories\OrderRepository;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../databases/migrations');
        $this->app->register(RouteServiceProvider::class);

        $this->commands([
            CancelStalePendingP24OrdersCommand::class,
        ]);
    }
}
