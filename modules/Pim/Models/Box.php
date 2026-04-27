<?php

declare(strict_types=1);

namespace Modules\Pim\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pim\Enums\BoxGenderEnum;

/**
 * @property int $id
 * @property int $package_id
 * @property string $slug
 * @property BoxGenderEnum $gender
 * @property string $name
 * @property string|null $description
 * @property int|null $price_pln
 * @property int|null $price_eur
 * @property int|null $price_usd
 * @property string|null $image_url
 * @property bool $available
 * @property int $sort_order
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Package $package
 */
class Box extends Model
{
    protected $table = 'pim_boxes';

    protected $fillable = [
        'backoffice_id',
        'package_id',
        'slug',
        'gender',
        'name',
        'description',
        'price_pln',
        'price_eur',
        'price_usd',
        'image_url',
        'available',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'gender' => BoxGenderEnum::class,
            'price_pln' => 'integer',
            'price_eur' => 'integer',
            'price_usd' => 'integer',
            'available' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Effective PLN price — box override or fallback to package.
     */
    public function getEffectivePricePln(): int
    {
        return $this->price_pln ?? $this->package->price_pln;
    }
}
