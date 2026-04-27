<?php

declare(strict_types=1);

namespace Modules\Cms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Contracts\HasCacheInvalidation;

/**
 * @property int $id
 * @property int|null $backoffice_id
 * @property string $author_name
 * @property string|null $author_note
 * @property string $content
 * @property int|null $rating
 * @property string|null $source
 * @property Carbon|null $posted_at
 * @property int $sort_order
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Testimonial extends Model implements HasCacheInvalidation
{
    public const CACHE_KEY = 'cms.testimonials';

    public const CACHE_TTL_SECONDS = 300;

    protected $table = 'cms_testimonials';

    /**
     * `backoffice_id` is here for `updateOrCreate(['backoffice_id' => X], ...)`
     * to write the lookup column. Set ONLY by the admin upsert path (URL param).
     */
    protected $fillable = [
        'backoffice_id',
        'author_name',
        'author_note',
        'content',
        'rating',
        'source',
        'posted_at',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'posted_at' => 'date',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
