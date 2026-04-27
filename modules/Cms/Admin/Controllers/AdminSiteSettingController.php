<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Admin\Requests\AdminSiteSettingUpdateRequest;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Resources\SiteSettingResource;

/**
 * @tags [Admin][CMS] Site Settings
 */
class AdminSiteSettingController extends ApiController
{
    /**
     * Upsert the site-wide settings singleton. Only sent fields are updated;
     * omitted keys keep their current value.
     *
     * @response array{data: SiteSettingResource, message: string}
     */
    public function update(AdminSiteSettingUpdateRequest $request): JsonResponse
    {
        // Singleton — pinned to id=1. forceCreate bypasses strict mass-assign on id.
        $settings = SiteSetting::query()->first()
            ?? SiteSetting::query()->forceCreate(['id' => 1]);

        $settings->fill([
            'contact_email' => $request->input('contactEmail', $settings->contact_email),
            'contact_phone' => $request->input('contactPhone', $settings->contact_phone),
            'contact_phone_href' => $request->input('contactPhoneHref', $settings->contact_phone_href),
            'contact_address' => $request->input('contactAddress', $settings->contact_address),
            'business_hours' => $request->input('businessHours', $settings->business_hours),
            'social_facebook' => $request->input('socialFacebook', $settings->social_facebook),
            'social_instagram' => $request->input('socialInstagram', $settings->social_instagram),
            'social_linkedin' => $request->input('socialLinkedin', $settings->social_linkedin),
            'hero_video_url' => $request->input('heroVideoUrl', $settings->hero_video_url),
        ])->save();

        return $this->success(new SiteSettingResource($settings), 'Site settings updated.');
    }
}
