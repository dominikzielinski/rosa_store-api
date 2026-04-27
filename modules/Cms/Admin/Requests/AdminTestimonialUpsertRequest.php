<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminTestimonialUpsertRequest extends FormRequest
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
            'authorName' => ['required', 'string', 'max:120'],
            'authorNote' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'source' => ['nullable', 'string', 'in:b2b,retail'],
            'postedAt' => ['nullable', 'date_format:Y-m-d'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
