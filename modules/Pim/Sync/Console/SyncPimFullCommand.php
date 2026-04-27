<?php

declare(strict_types=1);

namespace Modules\Pim\Sync\Console;

use Illuminate\Console\Command;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;
use Modules\Pim\Sync\Services\PimFeedClient;
use Modules\Pim\Sync\Services\PimUpserter;

/**
 * Full pull of the backoffice PIM feed. Acts as a fail-safe for missed
 * webhooks — every package and box is reconciled against the remote state,
 * and anything no longer present is marked inactive locally.
 *
 * Schedule: every 3h (see `routes/console.php`).
 */
class SyncPimFullCommand extends Command
{
    protected $signature = 'pim:sync-full';

    protected $description = 'Full pull of PIM data from the backoffice (fallback when webhooks are missed)';

    public function handle(PimFeedClient $feed, PimUpserter $upserter): int
    {
        $remotePackages = $feed->listPackages();
        $remotePackageIds = [];

        foreach ($remotePackages as $packageRow) {
            // Listing is lean — fetch detail to get nested boxes + images
            $id = $packageRow['id'] ?? null;
            if (! is_int($id)) {
                continue;
            }
            $detail = $feed->getPackage($id);
            if ($detail === null) {
                continue;
            }
            $upserter->upsert('package', $detail);
            $remotePackageIds[] = $id;
        }

        // Anything in shop with backoffice_id not seen → inactive
        Package::whereNotNull('backoffice_id')
            ->when($remotePackageIds !== [], fn ($q) => $q->whereNotIn('backoffice_id', $remotePackageIds))
            ->update(['active' => false]);

        // Same for boxes — the per-package upsert covers nested boxes, but boxes
        // whose parent package vanished entirely also need to be disabled.
        $remoteBoxes = $feed->listBoxes();
        $remoteBoxIds = array_filter(
            array_map(static fn ($b) => is_array($b) ? ($b['id'] ?? null) : null, $remoteBoxes),
            'is_int',
        );

        Box::whereNotNull('backoffice_id')
            ->when($remoteBoxIds !== [], fn ($q) => $q->whereNotIn('backoffice_id', $remoteBoxIds))
            ->update(['active' => false]);

        $this->info(sprintf(
            'Synced %d package(s), %d box(es) from backoffice.',
            count($remotePackageIds),
            count($remoteBoxIds),
        ));

        return self::SUCCESS;
    }
}
