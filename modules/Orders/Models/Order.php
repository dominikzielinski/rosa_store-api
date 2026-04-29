<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Orders\Enums\BillingTypeEnum;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Enums\PaymentMethodEnum;

/**
 * @property int $id
 * @property string $order_number
 * @property OrderStatusEnum $status
 * @property PaymentMethodEnum $payment_method
 * @property int $total_amount_pln
 * @property BillingTypeEnum $billing_type
 * @property string|null $billing_first_name
 * @property string|null $billing_last_name
 * @property string|null $billing_company
 * @property string|null $billing_nip
 * @property string $billing_email
 * @property string $billing_phone
 * @property string $billing_street
 * @property string $billing_house_number
 * @property string $billing_postal_code
 * @property string $billing_city
 * @property string|null $note
 * @property bool $consent_terms
 * @property bool $consent_marketing
 * @property string|null $p24_session_id
 * @property string|null $p24_token
 * @property int|null $p24_order_id
 * @property array<string, mixed>|null $p24_notification_payload
 * @property Carbon|null $p24_paid_at
 * @property Carbon|null $backoffice_synced_at
 * @property string|null $backoffice_order_id
 * @property int $backoffice_sync_attempts
 * @property string|null $backoffice_last_error
 * @property string|null $backoffice_pushed_status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Collection<OrderItem> $items
 */
class Order extends Model
{
    use SoftDeletes;

    /**
     * Mass-assignment whitelist: ONLY fields populated from user-supplied DTO at order creation.
     * Payment/sync/audit state (`status`, `p24_*`, `backoffice_*`) is set via explicit
     * `forceFill()` / `update()` from service or job — never from request body.
     */
    protected $fillable = [
        'order_number',
        'payment_method',
        'total_amount_pln',

        'billing_type',
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_nip',
        'billing_email',
        'billing_phone',
        'billing_street',
        'billing_house_number',
        'billing_postal_code',
        'billing_city',

        'note',
        'consent_terms',
        'consent_marketing',

        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatusEnum::class,
            'payment_method' => PaymentMethodEnum::class,
            'billing_type' => BillingTypeEnum::class,
            'total_amount_pln' => 'integer',
            'p24_order_id' => 'integer',
            'p24_notification_payload' => 'array',
            'p24_paid_at' => 'datetime:U',
            'consent_terms' => 'boolean',
            'consent_marketing' => 'boolean',
            'backoffice_synced_at' => 'datetime:U',
            'backoffice_sync_attempts' => 'integer',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
            'deleted_at' => 'datetime:U',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
