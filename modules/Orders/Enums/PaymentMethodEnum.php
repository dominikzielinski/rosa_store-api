<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

use App\Http\Traits\EnumHelpers;

enum PaymentMethodEnum: string
{
    use EnumHelpers;

    case Transfer = 'transfer';
    case P24 = 'p24';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::Transfer => ['id' => $this->value, 'name' => 'Przelew tradycyjny', 'slug' => $this->value],
            self::P24 => ['id' => $this->value, 'name' => 'Przelewy24', 'slug' => $this->value],
        };
    }

    /**
     * Status assigned to a freshly created order based on its payment method.
     * Transfer is queued for backoffice push; P24 waits for the gateway webhook.
     */
    public function initialOrderStatus(): OrderStatusEnum
    {
        return match ($this) {
            self::Transfer => OrderStatusEnum::Accepted,
            self::P24 => OrderStatusEnum::PendingPayment,
        };
    }
}
