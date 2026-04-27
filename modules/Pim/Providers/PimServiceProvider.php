<?php

declare(strict_types=1);

namespace Modules\Pim\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pim\Repositories\Interfaces\PackageRepositoryInterface;
use Modules\Pim\Repositories\PackageRepository;
use Modules\Pim\Sync\Console\SyncPimFullCommand;

class PimServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PackageRepositoryInterface::class, PackageRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../databases/migrations');
        $this->app->register(RouteServiceProvider::class);

        $this->commands([
            SyncPimFullCommand::class,
        ]);
    }
}
