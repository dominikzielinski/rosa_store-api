<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminSiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // middleware already authenticated the backoffice
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:30'],
            'contactPhoneHref' => ['nullable', 'string', 'max:30'],
            'contactAddress' => ['nullable', 'string', 'max:500'],
            'businessHours' => ['nullable', 'string', 'max:255'],
            'socialFacebook' => ['nullable', 'url:http,https', 'max:500'],
            'socialInstagram' => ['nullable', 'url:http,https', 'max:500'],
            'socialLinkedin' => ['nullable', 'url:http,https', 'max:500'],
            'heroVideoUrl' => ['nullable', 'url:http,https', 'max:500'],
        ];
    }
}
