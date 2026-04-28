<?php

declare(strict_types=1);

namespace Modules\Orders\Jobs;

use App\Exceptions\ServerErrorException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\BackofficeClient;
use Throwable;

/**
 * Pushes an accepted/paid order to the backoffice.
 *
 * Retry policy:
 *   - 3 attempts total, exponential backoff between attempts (5s, 30s)
 *   - On 4xx (validation/contract bug) → no retry, mark sync_failed
 *   - On 5xx / connection error → retry up to limit, then mark sync_failed
 *
 * PII safety: response bodies are NOT logged or persisted verbatim — they may
 * contain the rejected payload (email, phone, address, NIP). We store only the
 * HTTP status + truncated message hint.
 */
class PushOrderToBackofficeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> Backoff between attempts (n-1 entries for n tries). */
    public array $backoff = [5, 30];

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(BackofficeClient $client): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('PushOrderToBackofficeJob: order missing', ['orderId' => $this->orderId]);

            return;
        }

        // `paymentStatus` we'll send in this push. The discriminator below ensures
        // we re-push exactly once when this value changes (pending → paid for p24).
        $currentPaymentStatus = $order->p24_paid_at !== null ? 'paid' : 'pending';

        if ($order->backoffice_pushed_status === $currentPaymentStatus) {
            // Already pushed with this exact paymentStatus — duplicate dispatch, no-op.
            return;
        }

        try {
            $response = $client->pushOrder($order->load('items'));

            $updates = [
                'backoffice_synced_at' => now(),
                'backoffice_order_id' => $response['data']['backofficeOrderId'] ?? $order->backoffice_order_id,
                'backoffice_sync_attempts' => $order->backoffice_sync_attempts + 1,
                'backoffice_last_error' => null,
                'backoffice_pushed_status' => $currentPaymentStatus,
            ];

            // Mark as Synced (terminal — shop's job done) once the final state was pushed:
            //   transfer: there's only one push (paymentStatus=pending), shop never sees paid
            //             confirmation (operator marks it paid in backoffice manually).
            //   p24:      synced after the paid push, not the initial pending one.
            $isTerminalPush = $order->payment_method->value === 'transfer'
                || $currentPaymentStatus === 'paid';
            if ($isTerminalPush) {
                $updates['status'] = OrderStatusEnum::Synced;
            }

            $order->forceFill($updates)->save();
        } catch (RequestException $e) {
            $status = $e->response?->status() ?? 0;
            $hint = "HTTP {$status}";

            $order->forceFill([
                'backoffice_sync_attempts' => $order->backoffice_sync_attempts + 1,
                'backoffice_last_error' => $hint,
            ])->save();

            if ($status >= 400 && $status < 500) {
                // Contract bug — backoffice rejected our payload. No retry.
                $this->markFailed($order, $hint);
                $this->fail($e);

                return;
            }

            // 5xx — let the queue retry
            throw $e;
        } catch (ConnectionException $e) {
            $order->forceFill([
                'backoffice_sync_attempts' => $order->backoffice_sync_attempts + 1,
                'backoffice_last_error' => 'Connection error',
            ])->save();
            throw $e;
        } catch (ServerErrorException $e) {
            // Local misconfig — no point retrying without ops intervention
            $this->markFailed($order, $e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Called by the queue worker after `tries` exhausted.
     * Skip if already marked (handle() may have set sync_failed with a more
     * specific HTTP error message — don't overwrite with generic exception text).
     */
    public function failed(Throwable $exception): void
    {
        $order = Order::find($this->orderId);
        if (! $order) {
            return;
        }
        if ($order->status === OrderStatusEnum::Synced
            || $order->status === OrderStatusEnum::SyncFailed) {
            return;
        }

        $this->markFailed($order, $exception->getMessage());
    }

    private function markFailed(Order $order, string $error): void
    {
        // Truncate aggressively — error string may have come from an exception message
        // that included parts of the payload. We store a hint, not a forensic record.
        $hint = substr($error, 0, 200);

        $order->forceFill([
            'status' => OrderStatusEnum::SyncFailed,
            'backoffice_last_error' => $hint,
        ])->save();

        Log::error('Order sync to backoffice failed', [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'attempts' => $order->backoffice_sync_attempts,
            'hint' => $hint,
        ]);
    }
}
