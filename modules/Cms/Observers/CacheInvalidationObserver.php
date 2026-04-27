<?php

declare(strict_types=1);

namespace Modules\Cms\Observers;

use Modules\Cms\Contracts\HasCacheInvalidation;

/**
 * Generic observer — calls `clearCache()` on the model class after any mutation.
 * Attached to SiteSetting, FaqItem, Testimonial in CmsServiceProvider.
 *
 * Works for backoffice writes that go through Eloquent. Raw SQL updates bypass
 * the model lifecycle and rely on the cache TTL as the fallback.
 */
class CacheInvalidationObserver
{
    public function saved(HasCacheInvalidation $model): void
    {
        $model::clearCache();
    }

    public function deleted(HasCacheInvalidation $model): void
    {
        $model::clearCache();
    }
}
