<?php

declare(strict_types=1);

namespace Modules\Pim\Repositories\Interfaces;

use App\Exceptions\ClientErrorException;
use Illuminate\Database\Eloquent\Collection;
use Modules\Pim\Models\Package;

interface PackageRepositoryInterface
{
    /**
     * Active packages sorted for display. Eager-loads images + boxes.
     *
     * @return Collection<int, Package>
     */
    public function getAllActive(): Collection;

    /**
     * Get an active package by slug (standard/premium/vip).
     *
     * @throws ClientErrorException when no active package matches (404)
     */
    public function getActiveBySlug(string $slug): Package;
}
