<?php

declare(strict_types=1);

namespace Modules\Cms\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Repositories\Interfaces\FaqRepositoryInterface;

readonly class FaqRepository implements FaqRepositoryInterface
{
    public function __construct(
        protected FaqItem $model,
    ) {}

    public function getAllActive(): Collection
    {
        return Cache::remember(
            FaqItem::CACHE_KEY,
            FaqItem::CACHE_TTL_SECONDS,
            fn () => $this->model
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
