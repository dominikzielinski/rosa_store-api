<?php

declare(strict_types=1);

namespace Modules\Orders\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Models\Order;

/**
 * Public status endpoint payload — used by FE polling on /platnosc/return.
 * Reveals only what's needed to drive the UI; no PII.
 *
 * @mixin Order
 */
class OrderStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $orderNumber */
            'orderNumber' => $this->order_number,
            /** @var array{id: string, name: string, slug: string} $status */
            'status' => $this->status->getData(),
            /** @var array{id: string, name: string, slug: string} $paymentMethod */
            'paymentMethod' => $this->payment_method->getData(),
            /** @var bool $isPaid */
            'isPaid' => $this->p24_paid_at !== null,
            /** @var bool $isFinalized Order has reached a terminal state */
            'isFinalized' => $this->status->isFinalized(),
        ];
    }
}
