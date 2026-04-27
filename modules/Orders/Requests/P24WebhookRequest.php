<?php

declare(strict_types=1);

namespace Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;

class P24WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is the HMAC signature, validated in the controller.
        return true;
    }

    /**
     * @return array<string, array<string|object>>
     */
    public function rules(): array
    {
        return [
            'merchantId' => ['required', 'integer'],
            'posId' => ['required', 'integer'],
            'sessionId' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'originAmount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'orderId' => ['required', 'integer', 'min:1'],
            'methodId' => ['required', 'integer'],
            'statement' => ['nullable', 'string', 'max:255'],
            'sign' => ['required', 'string'],
        ];
    }
}
