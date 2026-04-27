<?php

declare(strict_types=1);

namespace Modules\Cms\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Models\Testimonial;
use Modules\Cms\Observers\CacheInvalidationObserver;
use Modules\Cms\Repositories\FaqRepository;
use Modules\Cms\Repositories\Interfaces\FaqRepositoryInterface;
use Modules\Cms\Repositories\Interfaces\TestimonialRepositoryInterface;
use Modules\Cms\Repositories\TestimonialRepository;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FaqRepositoryInterface::class, FaqRepository::class);
        $this->app->bind(TestimonialRepositoryInterface::class, TestimonialRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../databases/migrations');

        // Cache invalidation when backoffice updates any CMS model.
        // For raw SQL updates (bypassing Eloquent), cache TTL is the fallback.
        SiteSetting::observe(CacheInvalidationObserver::class);
        FaqItem::observe(CacheInvalidationObserver::class);
        Testimonial::observe(CacheInvalidationObserver::class);

        $this->app->register(RouteServiceProvider::class);
    }
}
