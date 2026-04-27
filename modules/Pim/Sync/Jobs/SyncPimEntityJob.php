<?php

declare(strict_types=1);

namespace Modules\Pim\Sync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Pim\Sync\Services\PimFeedClient;
use Modules\Pim\Sync\Services\PimUpserter;

/**
 * Refreshes a single PIM entity in the local DB after a webhook signal.
 *
 * Pulls the entity from backoffice's read-only feed and upserts by `backoffice_id`.
 * On `deleted` action (or 404 from feed) the entity is soft-disabled locally
 * (we set `active=false` rather than removing the row, to keep historical
 * order_items snapshots referenceable).
 */
class SyncPimEntityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120, 300, 600];

    public function __construct(
        public readonly string $entity,   // 'package' | 'box' | 'assortment'
        public readonly int $backofficeId,
        public readonly string $action,   // 'created' | 'updated' | 'deleted'
    ) {}

    public function handle(PimFeedClient $feed, PimUpserter $upserter): void
    {
        if ($this->action === 'deleted') {
            $upserter->markInactive($this->entity, $this->backofficeId);

            return;
        }

        $payload = match ($this->entity) {
            'package' => $feed->getPackage($this->backofficeId),
            'box' => $feed->getBox($this->backofficeId),
            // Assortments are nested inside box payload — backoffice signals the
            // assortment but we resolve it via its parent box. Caller must include
            // the parent boxId via dispatching SyncPimEntityJob('box', boxId, 'updated').
            // We just no-op here so retries don't pile up.
            default => null,
        };

        if ($payload === null) {
            // 404 → backoffice no longer exposes this entity (inactive or removed).
            // Mirror locally.
            $upserter->markInactive($this->entity, $this->backofficeId);

            return;
        }

        $upserter->upsert($this->entity, $payload);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncPimEntityJob failed', [
            'entity' => $this->entity,
            'backofficeId' => $this->backofficeId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }
}
