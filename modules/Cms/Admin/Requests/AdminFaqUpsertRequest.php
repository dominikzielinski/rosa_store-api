<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminFaqUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
