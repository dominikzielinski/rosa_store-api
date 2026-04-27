<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

use App\Http\Traits\EnumHelpers;

/**
 * Order lifecycle in the shop. After `synced` the master is in backoffice;
 * shop keeps the row for audit + P24 webhook lookup, no further state changes.
 */
enum OrderStatusEnum: string
{
    use EnumHelpers;

    /** Transfer flow — order accepted, queued to push to backoffice */
    case Accepted = 'accepted';

    /** P24 flow — waiting for payment */
    case PendingPayment = 'pending_payment';

    /** P24 flow — payment confirmed by webhook, queued to push to backoffice */
    case Paid = 'paid';

    /** Backoffice acknowledged the push — terminal state in shop */
    case Synced = 'synced';

    /** P24 timed out / failed / cancelled by user */
    case Cancelled = 'cancelled';

    /** Push to backoffice failed after retries — needs manual intervention */
    case SyncFailed = 'sync_failed';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::Accepted => ['id' => $this->value, 'name' => 'Przyjęte', 'slug' => $this->value],
            self::PendingPayment => ['id' => $this->value, 'name' => 'Oczekuje na płatność', 'slug' => $this->value],
            self::Paid => ['id' => $this->value, 'name' => 'Opłacone', 'slug' => $this->value],
            self::Synced => ['id' => $this->value, 'name' => 'Przekazane do realizacji', 'slug' => $this->value],
            self::Cancelled => ['id' => $this->value, 'name' => 'Anulowane', 'slug' => $this->value],
            self::SyncFailed => ['id' => $this->value, 'name' => 'Błąd synchronizacji', 'slug' => $this->value],
        };
    }

    /**
     * Whether the order has reached a state from which it will not transition further
     * by itself. Used by FE polling to stop refreshing.
     *
     * `Paid` is NOT terminal — it's an interim state between the P24 webhook and
     * the backoffice push (transitions to `Synced` or `SyncFailed`).
     */
    public function isFinalized(): bool
    {
        return match ($this) {
            self::Synced, self::Cancelled, self::SyncFailed => true,
            default => false,
        };
    }
}
