<?php

declare(strict_types=1);

namespace Modules\Pim\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pim\Models\Box;

/**
 * @mixin Box
 */
class BoxResource extends JsonResource
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
            /** @var string $packageSlug */
            'packageSlug' => $this->package->slug,
            /** @var array{id: string, name: string, slug: string} $gender */
            'gender' => $this->gender->getData(),
            /** @var string $name */
            'name' => $this->name,
            /** @var string|null $description */
            'description' => $this->description,
            /** @var array{PLN: int, EUR: int|null, USD: int|null} $prices Price in grosze/cents */
            'prices' => [
                'PLN' => $this->price_pln ?? $this->package->price_pln,
                'EUR' => $this->price_eur ?? $this->package->price_eur,
                'USD' => $this->price_usd ?? $this->package->price_usd,
            ],
            /** @var string|null $image */
            'image' => $this->image_url,
            /** @var bool $available */
            'available' => $this->available,
        ];
    }
}
