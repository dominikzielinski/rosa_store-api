<?php

declare(strict_types=1);

namespace Modules\Pim\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $package_id
 * @property string $url
 * @property string|null $alt
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Package $package
 */
class PackageImage extends Model
{
    protected $table = 'pim_package_images';

    protected $fillable = [
        'backoffice_id',
        'package_id',
        'url',
        'alt',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
