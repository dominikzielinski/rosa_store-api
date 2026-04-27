<?php

declare(strict_types=1);

namespace Modules\Orders\Console\Commands;

use Illuminate\Console\Command;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Enums\PaymentMethodEnum;
use Modules\Orders\Models\Order;

/**
 * Cancels P24 orders that have been `pending_payment` for more than 24h.
 * P24 sessions expire after 24h by default — we mirror that on our side
 * to keep the orders table tidy and prevent stale records.
 *
 * Schedule from `routes/console.php`:
 *     Schedule::command('orders:cancel-stale-p24')->hourly();
 */
class CancelStalePendingP24OrdersCommand extends Command
{
    protected $signature = 'orders:cancel-stale-p24 {--hours=24 : Threshold in hours}';

    protected $description = 'Cancel P24 orders stuck in pending_payment past the threshold';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours);

        $count = Order::query()
            ->where('payment_method', PaymentMethodEnum::P24)
            ->where('status', OrderStatusEnum::PendingPayment)
            ->where('created_at', '<', $threshold)
            ->update(['status' => OrderStatusEnum::Cancelled]);

        $this->info("Cancelled {$count} stale P24 order(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
