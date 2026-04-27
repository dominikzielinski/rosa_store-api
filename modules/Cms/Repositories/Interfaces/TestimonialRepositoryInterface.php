<?php

declare(strict_types=1);

namespace Modules\Cms\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Modules\Cms\Models\Testimonial;

interface TestimonialRepositoryInterface
{
    /**
     * @return Collection<int, Testimonial>
     */
    public function getAllActive(): Collection;
}
