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
 * @property string|null $slug
 * @property string $question
 * @property string $answer
 * @property string|null $category
 * @property int $sort_order
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FaqItem extends Model implements HasCacheInvalidation
{
    public const CACHE_KEY = 'cms.faq_items';

    public const CACHE_TTL_SECONDS = 300;

    protected $table = 'cms_faq_items';

    /**
     * `backoffice_id` is here so Eloquent `updateOrCreate(['backoffice_id' => X], ...)`
     * actually writes the lookup column. It's set ONLY by the admin upsert path
     * (which controls the value via the URL parameter) — never from a public
     * request payload.
     */
    protected $fillable = [
        'backoffice_id',
        'slug',
        'question',
        'answer',
        'category',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
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
