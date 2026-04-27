<?php

declare(strict_types=1);

namespace Modules\Orders\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Models\Order;

/**
 * Returned to FE right after POST /api/orders. Minimal shape — frontend only
 * needs orderNumber + redirectUrl to navigate. We do NOT echo billing data
 * back (privacy + smaller payload).
 *
 * @mixin Order
 */
class OrderCreatedResource extends JsonResource
{
    public function __construct(
        Order $resource,
        private readonly string $redirectUrl,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $orderNumber */
            'orderNumber' => $this->order_number,
            /** @var array{id: string, name: string, slug: string} $paymentMethod */
            'paymentMethod' => $this->payment_method->getData(),
            /** @var int $totalAmountPln Total in grosze */
            'totalAmountPln' => $this->total_amount_pln,
            /** @var string $redirectUrl Where the FE should navigate next */
            'redirectUrl' => $this->redirectUrl,
        ];
    }
}
