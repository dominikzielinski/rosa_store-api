<?php

declare(strict_types=1);

namespace Modules\Orders\Repositories\Interfaces;

use App\Exceptions\ClientErrorException;
use Modules\Orders\Models\Order;

interface OrderRepositoryInterface
{
    /**
     * Generate a unique order number (RD-XXXXXXXX). The unique DB index is the
     * actual collision guard; this just minimizes the chance of needing a retry.
     */
    public function generateOrderNumber(): string;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Order;

    /**
     * @throws ClientErrorException when no order matches (404)
     */
    public function getByOrderNumber(string $orderNumber): Order;
}
