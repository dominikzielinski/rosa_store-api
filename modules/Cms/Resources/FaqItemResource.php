<?php

declare(strict_types=1);

namespace Modules\Cms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cms\Models\FaqItem;

/**
 * @mixin FaqItem
 */
class FaqItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int $id */
            'id' => $this->id,
            /** @var string|null $slug */
            'slug' => $this->slug,
            /** @var string $question */
            'question' => $this->question,
            /** @var string $answer */
            'answer' => $this->answer,
            /** @var string|null $category */
            'category' => $this->category,
        ];
    }
}
