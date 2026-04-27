<?php

declare(strict_types=1);

namespace Modules\Cms\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Resources\SiteSettingResource;

/**
 * @tags [CMS] Site Settings
 */
class SiteSettingController extends ApiController
{
    /**
     * Get the site-wide settings (contact info, social, hero media).
     *
     * Single singleton row — always returns the same shape. Values may be null
     * before the backoffice fills them in.
     *
     *
     * @response array{data: SiteSettingResource}
     */
    public function show(): JsonResponse
    {
        return $this->success(new SiteSettingResource(SiteSetting::current()));
    }
}
