<?php

declare(strict_types=1);

namespace Modules\Contact\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Contact\Repositories\ContactSubmissionRepository;
use Modules\Contact\Repositories\Interfaces\ContactSubmissionRepositoryInterface;

class ContactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ContactSubmissionRepositoryInterface::class,
            ContactSubmissionRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../databases/migrations');
        $this->loadViewsFrom(__DIR__.'/../views', 'contact');

        $this->app->register(RouteServiceProvider::class);
    }
}
