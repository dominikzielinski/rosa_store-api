<?php

declare(strict_types=1);

namespace Modules\Pim\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pim\Models\Package;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int $id */
            'id' => $this->id,
            /** @var string $slug */
            'slug' => $this->slug,
            /** @var string $name */
            'name' => $this->name,
            /** @var string|null $tagline */
            'tagline' => $this->tagline,
            /** @var string|null $description */
            'description' => $this->description,
            /** @var array{PLN: int, EUR: int|null, USD: int|null} $prices Price in grosze/cents */
            'prices' => [
                'PLN' => $this->price_pln,
                'EUR' => $this->price_eur,
                'USD' => $this->price_usd,
            ],
            /** @var bool $highlighted */
            'highlighted' => $this->highlighted,
            /** @var string[] $images */
            'images' => $this->images->pluck('url')->all(),
            /** @var BoxResource[] $boxes */
            'boxes' => BoxResource::collection($this->boxes),
        ];
    }
}
