<?php

declare(strict_types=1);

namespace Modules\Cms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cms\Models\SiteSetting;

/**
 * @mixin SiteSetting
 */
class SiteSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var array{email: string|null, phone: string|null, phoneHref: string|null, address: string|null, hours: string|null} $contact */
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'phoneHref' => $this->contact_phone_href,
                'address' => $this->contact_address,
                'hours' => $this->business_hours,
            ],
            /** @var array{facebook: string|null, instagram: string|null, linkedin: string|null} $social */
            'social' => [
                'facebook' => $this->social_facebook,
                'instagram' => $this->social_instagram,
                'linkedin' => $this->social_linkedin,
            ],
            /** @var array{heroVideoUrl: string|null} $hero */
            'hero' => [
                'heroVideoUrl' => $this->hero_video_url,
            ],
        ];
    }
}
