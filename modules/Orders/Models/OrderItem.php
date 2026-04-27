<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $box_id
 * @property string $box_slug
 * @property string $box_name
 * @property string $package_slug
 * @property string $gender
 * @property int $quantity
 * @property int $unit_price_pln
 * @property int $total_price_pln
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Order $order
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'box_id',
        'box_slug',
        'box_name',
        'package_slug',
        'gender',
        'quantity',
        'unit_price_pln',
        'total_price_pln',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pln' => 'integer',
            'total_price_pln' => 'integer',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
