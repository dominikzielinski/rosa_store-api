<?php

declare(strict_types=1);

namespace Modules\Cms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Contracts\HasCacheInvalidation;

/**
 * Singleton — always exactly one row, ID 1. Use `SiteSetting::current()` to get it.
 *
 * @property int $id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_phone_href
 * @property string|null $contact_address
 * @property string|null $business_hours
 * @property string|null $social_facebook
 * @property string|null $social_instagram
 * @property string|null $social_linkedin
 * @property string|null $hero_video_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SiteSetting extends Model implements HasCacheInvalidation
{
    public const CACHE_KEY = 'cms.site_settings';

    public const CACHE_TTL_SECONDS = 60;

    protected $table = 'site_settings';

    protected $fillable = [
        'contact_email',
        'contact_phone',
        'contact_phone_href',
        'contact_address',
        'business_hours',
        'social_facebook',
        'social_instagram',
        'social_linkedin',
        'hero_video_url',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    /**
     * Get the current settings, cached. Auto-creates an empty row on first call
     * so the frontend never gets null.
     */
    public static function current(): self
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            // Pin to id=1 (singleton). `forceCreate` bypasses fillable for the id
            // field — strict mode would reject mass-assignment of id otherwise.
            fn () => self::query()->first() ?? self::query()->forceCreate(['id' => 1]),
        );
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
