<?php

declare(strict_types=1);

namespace Modules\Cms\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Modules\Cms\Models\FaqItem;

interface FaqRepositoryInterface
{
    /**
     * @return Collection<int, FaqItem>
     */
    public function getAllActive(): Collection;
}
