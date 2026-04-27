<?php

declare(strict_types=1);

namespace Modules\Pim\Repositories;

use App\Exceptions\ClientErrorException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Pim\Models\Package;
use Modules\Pim\Repositories\Interfaces\PackageRepositoryInterface;

readonly class PackageRepository implements PackageRepositoryInterface
{
    public function __construct(
        protected Package $model,
    ) {}

    public function getAllActive(): Collection
    {
        return $this->withRelations()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getActiveBySlug(string $slug): Package
    {
        try {
            return $this->withRelations()
                ->where('active', true)
                ->where('slug', $slug)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            throw new ClientErrorException("Pakiet '{$slug}' nie istnieje.", 404);
        }
    }

    private function withRelations(): Builder
    {
        return $this->model->with([
            'images',
            // `boxes.package` eager-loaded so BoxResource's parent-price fallback
            // hits already-resolved relations (no N+1).
            'boxes' => fn ($q) => $q->where('active', true)->orderBy('sort_order')->with('package'),
        ]);
    }
}
