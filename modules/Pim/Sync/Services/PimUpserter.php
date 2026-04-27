<?php

declare(strict_types=1);

namespace Modules\Pim\Sync\Services;

use App\Exceptions\ServerErrorException;
use Illuminate\Database\DatabaseManager;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;
use Modules\Pim\Models\PackageImage;

/**
 * Maps backoffice feed payloads to local DB rows. `backoffice_id` is the upsert key.
 *
 * Soft-disable strategy: when a remote entity disappears (or webhook signals
 * `deleted`), we set `active=false` rather than removing the row. Historical
 * `order_items` keep the reference, and re-activation is idempotent.
 */
readonly class PimUpserter
{
    public function __construct(
        protected DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ServerErrorException on unknown entity type
     */
    public function upsert(string $entity, array $payload): void
    {
        match ($entity) {
            'package' => $this->upsertPackage($payload),
            'box' => $this->upsertBox($payload),
            default => throw new ServerErrorException("PimUpserter: unsupported entity '{$entity}'."),
        };
    }

    /**
     * @throws ServerErrorException on unknown entity type
     */
    public function markInactive(string $entity, int $backofficeId): void
    {
        match ($entity) {
            'package' => Package::where('backoffice_id', $backofficeId)->update(['active' => false]),
            'box' => Box::where('backoffice_id', $backofficeId)->update(['active' => false]),
            default => throw new ServerErrorException("PimUpserter: unsupported entity '{$entity}'."),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertPackage(array $data): void
    {
        $this->databaseManager->transaction(function () use ($data): void {
            $package = Package::updateOrCreate(
                ['backoffice_id' => $this->intOrNull($data, 'id')],
                [
                    // Backoffice exposes `type` (e.g. 'standard'/'premium'/'vip') — that's our slug.
                    'slug' => $this->stringOrNull($data, 'type')
                        ?? $this->stringOrNull($data, 'slug')
                        ?? '',
                    'name' => $this->stringOrNull($data, 'name') ?? '',
                    'tagline' => $this->stringOrNull($data, 'tagline'),
                    'description' => $this->stringOrNull($data, 'description'),
                    'price_pln' => $this->priceFromPayload($data, 'PLN') ?? 0,
                    'price_eur' => $this->priceFromPayload($data, 'EUR'),
                    'price_usd' => $this->priceFromPayload($data, 'USD'),
                    'highlighted' => (bool) ($data['highlighted'] ?? false),
                    'sort_order' => (int) ($data['sortOrder'] ?? $data['position'] ?? 0),
                    'active' => true,
                ],
            );

            // Optional nested images replace gallery
            if (isset($data['images']) && is_array($data['images'])) {
                $this->syncPackageImages($package, $data['images']);
            }

            // Optional nested boxes — upsert each, mark missing as inactive
            if (isset($data['boxes']) && is_array($data['boxes'])) {
                $seenBoxBackofficeIds = [];
                foreach ($data['boxes'] as $boxData) {
                    if (! is_array($boxData)) {
                        continue;
                    }
                    $boxData['packageId'] = $package->id;
                    $this->upsertBox($boxData);
                    $bid = $this->intOrNull($boxData, 'id');
                    if ($bid !== null) {
                        $seenBoxBackofficeIds[] = $bid;
                    }
                }

                Box::where('package_id', $package->id)
                    ->when($seenBoxBackofficeIds !== [], fn ($q) => $q->whereNotIn('backoffice_id', $seenBoxBackofficeIds))
                    ->update(['active' => false]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertBox(array $data): void
    {
        $packageLocalId = $this->resolvePackageLocalId($data);
        if ($packageLocalId === null) {
            // Can't place this box without a parent package — skip silently.
            return;
        }

        Box::updateOrCreate(
            ['backoffice_id' => $this->intOrNull($data, 'id')],
            [
                'package_id' => $packageLocalId,
                // Backoffice exposes `sku` (e.g. 'BOX-STD-W-01') — that's our slug.
                'slug' => $this->stringOrNull($data, 'sku')
                    ?? $this->stringOrNull($data, 'slug')
                    ?? '',
                'gender' => $this->genderSlug($data),
                'name' => $this->stringOrNull($data, 'name') ?? '',
                'description' => $this->stringOrNull($data, 'description'),
                'price_pln' => $this->priceFromPayload($data, 'PLN'),
                'price_eur' => $this->priceFromPayload($data, 'EUR'),
                'price_usd' => $this->priceFromPayload($data, 'USD'),
                'image_url' => $this->stringOrNull($data, 'image') ?? $this->stringOrNull($data, 'imageUrl'),
                'available' => (bool) ($data['available'] ?? true),
                'sort_order' => (int) ($data['sortOrder'] ?? $data['position'] ?? 0),
                'active' => true,
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    private function syncPackageImages(Package $package, array $images): void
    {
        $keptIds = [];

        foreach ($images as $img) {
            if (! is_array($img)) {
                continue;
            }
            $row = PackageImage::updateOrCreate(
                ['backoffice_id' => $this->intOrNull($img, 'id')],
                [
                    'package_id' => $package->id,
                    'url' => $this->stringOrNull($img, 'url') ?? '',
                    'alt' => $this->stringOrNull($img, 'alt'),
                    'sort_order' => (int) ($img['sortOrder'] ?? $img['position'] ?? 0),
                ],
            );
            $keptIds[] = $row->id;
        }

        PackageImage::where('package_id', $package->id)
            ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();
    }

    /**
     * Resolve the local package_id for an inbound box payload. Backoffice may
     * send `packageBackofficeId`, or nest the box under a package being upserted
     * (in which case caller injected `packageId` directly).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePackageLocalId(array $data): ?int
    {
        if (isset($data['packageId']) && is_int($data['packageId'])) {
            return $data['packageId'];
        }
        $remote = $data['packageBackofficeId'] ?? $data['package']['id'] ?? null;
        if (! is_int($remote)) {
            return null;
        }

        return Package::where('backoffice_id', $remote)->value('id');
    }

    /**
     * Extract a price for the given currency code. Handles three payload shapes:
     *
     *   prices: [{currency: {code: 'PLN'}, amount: 35000}, ...]   ← real backoffice
     *   prices: {PLN: 35000, EUR: 8000}
     *   pricePln: 35000  /  priceEur: 8000
     *
     * @param  array<string, mixed>  $data
     */
    private function priceFromPayload(array $data, string $code): ?int
    {
        // 1. Array of {currency: {code}, amount} entries
        if (isset($data['prices']) && is_array($data['prices'])) {
            foreach ($data['prices'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $entryCode = is_array($entry['currency'] ?? null) ? ($entry['currency']['code'] ?? null) : null;
                if ($entryCode === $code && is_int($entry['amount'] ?? null)) {
                    return $entry['amount'];
                }
            }
            // 2. Map shape — e.g. prices: {PLN: 35000}
            if (isset($data['prices'][$code]) && is_int($data['prices'][$code])) {
                return $data['prices'][$code];
            }
        }

        // 3. Flat keys — e.g. pricePln: 35000
        $flatKey = 'price'.ucfirst(strtolower($code));
        if (isset($data[$flatKey]) && is_int($data[$flatKey])) {
            return $data[$flatKey];
        }

        return null;
    }

    /**
     * Backoffice may send gender as `'male'|'female'|'unisex'` (their slug)
     * while the shop enum is `'women'|'men'`. Map known forms; fall back to raw.
     *
     * @param  array<string, mixed>  $data
     */
    private function genderSlug(array $data): string
    {
        $raw = $data['gender']['slug'] ?? $data['gender'] ?? null;
        if (! is_string($raw)) {
            return 'women';
        }

        return match (strtolower($raw)) {
            'female', 'women', 'woman' => 'women',
            'male', 'men', 'man' => 'men',
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function intOrNull(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_int($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringOrNull(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }
}
