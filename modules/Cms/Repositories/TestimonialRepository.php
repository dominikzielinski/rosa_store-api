<?php

declare(strict_types=1);

namespace Modules\Cms\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Models\Testimonial;
use Modules\Cms\Repositories\Interfaces\TestimonialRepositoryInterface;

readonly class TestimonialRepository implements TestimonialRepositoryInterface
{
    public function __construct(
        protected Testimonial $model,
    ) {}

    public function getAllActive(): Collection
    {
        return Cache::remember(
            Testimonial::CACHE_KEY,
            Testimonial::CACHE_TTL_SECONDS,
            fn () => $this->model
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
