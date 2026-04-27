<?php

declare(strict_types=1);

namespace Modules\Cms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cms\Models\Testimonial;

/**
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int $id */
            'id' => $this->id,
            /** @var string $authorName */
            'authorName' => $this->author_name,
            /** @var string $content */
            'content' => $this->content,
            /** @var int|null $rating */
            'rating' => $this->rating,
            /** @var string|null $source */
            'source' => $this->source,
            /** @var int|null $postedAt Unix timestamp */
            'postedAt' => $this->posted_at?->timestamp,
        ];
    }
}
