<?php

declare(strict_types=1);

namespace Modules\Cms\Contracts;

use Modules\Cms\Observers\CacheInvalidationObserver;

/**
 * Models attached to {@see CacheInvalidationObserver}
 * implement this so a static `clearCache()` exists at compile time —
 * a renamed method becomes an interface error, not a silent no-op.
 */
interface HasCacheInvalidation
{
    public static function clearCache(): void;
}
