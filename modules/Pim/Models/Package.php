<?php

declare(strict_types=1);

namespace Modules\Pim\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property string|null $description
 * @property int $price_pln
 * @property int|null $price_eur
 * @property int|null $price_usd
 * @property bool $highlighted
 * @property int $sort_order
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<PackageImage> $images
 * @property Collection<Box> $boxes
 */
class Package extends Model
{
    protected $table = 'pim_packages';

    protected $fillable = [
        'backoffice_id',
        'slug',
        'name',
        'tagline',
        'description',
        'price_pln',
        'price_eur',
        'price_usd',
        'highlighted',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price_pln' => 'integer',
            'price_eur' => 'integer',
            'price_usd' => 'integer',
            'highlighted' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class)->orderBy('sort_order');
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class)->orderBy('sort_order');
    }
}
