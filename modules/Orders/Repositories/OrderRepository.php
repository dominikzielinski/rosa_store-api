<?php

declare(strict_types=1);

namespace Modules\Orders\Repositories;

use App\Exceptions\ClientErrorException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Orders\Models\Order;
use Modules\Orders\Repositories\Interfaces\OrderRepositoryInterface;

readonly class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected Order $model,
    ) {}

    public function generateOrderNumber(): string
    {
        // RD-XXXXXXXX — 6 digits from ms timestamp + 2 random. The unique index on
        // `order_number` is the real collision guard; this loop just keeps retries rare.
        do {
            $candidate = 'RD-'.substr((string) (int) (microtime(true) * 1000), -6)
                .str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        } while ($this->model->where('order_number', $candidate)->exists());

        return $candidate;
    }

    public function create(array $attributes): Order
    {
        // Repository is internal API — DTO-validated data, can bypass mass-assignment guard.
        // The Order::$fillable safeguard exists to protect against accidental
        // controller-level `Order::create($request->all())` (which we don't do).
        $order = $this->model->newInstance();
        $order->forceFill($attributes)->save();

        return $order;
    }

    public function getByOrderNumber(string $orderNumber): Order
    {
        try {
            return $this->model->where('order_number', $orderNumber)->firstOrFail();
        } catch (ModelNotFoundException) {
            throw new ClientErrorException("Zamówienie {$orderNumber} nie istnieje.", 404);
        }
    }
}
