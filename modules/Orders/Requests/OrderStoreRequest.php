<?php

declare(strict_types=1);

namespace Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\BillingTypeEnum;
use Modules\Orders\Enums\PaymentMethodEnum;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * Honeypot (`website`) is intentionally NOT validated here — a non-empty
     * value is detected silently by the controller (returns a fake-success
     * response) so bots get no feedback. See {@see OrderController::store}.
     *
     * @return array<string, array<string|object>>
     */
    public function rules(): array
    {
        return [
            // Cart items — only id + quantity. Server fetches the rest from pim_boxes.
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.boxId' => ['required', 'integer', 'exists:pim_boxes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

            'paymentMethod' => ['required', Rule::enum(PaymentMethodEnum::class)],

            // Billing
            'billing' => ['required', 'array'],
            'billing.type' => ['required', Rule::enum(BillingTypeEnum::class)],

            // individual fields
            'billing.firstName' => ['required_if:billing.type,individual', 'nullable', 'string', 'max:255'],
            'billing.lastName' => ['required_if:billing.type,individual', 'nullable', 'string', 'max:255'],

            // company fields
            'billing.companyName' => ['required_if:billing.type,company', 'nullable', 'string', 'max:255'],
            'billing.nip' => ['required_if:billing.type,company', 'nullable', 'string', 'regex:/^[\d\s\-]{10,15}$/'],

            // shared
            'billing.email' => ['required', 'email', 'max:255'],
            'billing.phone' => ['required', 'string', 'regex:/^[+\-\d\s]{9,15}$/'],
            'billing.street' => ['required', 'string', 'max:255'],
            'billing.houseNumber' => ['required', 'string', 'max:32'],
            'billing.postalCode' => ['required', 'string', 'regex:/^\d{2}-\d{3}$/'],
            'billing.city' => ['required', 'string', 'max:100'],

            'note' => ['nullable', 'string', 'max:5000'],
            'consentTerms' => ['accepted'],
            'consentMarketing' => ['nullable', 'boolean'],

            // honeypot — handled silently by the controller, NOT validated
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Koszyk jest pusty.',
            'items.min' => 'Koszyk jest pusty.',
            'items.*.boxId.exists' => 'Wybrany produkt nie istnieje.',
            'paymentMethod.required' => 'Wybierz metodę płatności.',
            'billing.type.required' => 'Wybierz typ rozliczenia.',
            'billing.firstName.required_if' => 'Podaj imię.',
            'billing.lastName.required_if' => 'Podaj nazwisko.',
            'billing.companyName.required_if' => 'Podaj nazwę firmy.',
            'billing.nip.required_if' => 'Podaj NIP.',
            'billing.nip.regex' => 'NIP musi składać się z 10 cyfr.',
            'billing.email.required' => 'Podaj adres e-mail.',
            'billing.email.email' => 'Podaj poprawny adres e-mail.',
            'billing.phone.required' => 'Podaj numer telefonu.',
            'billing.phone.regex' => 'Podaj poprawny numer telefonu.',
            'billing.street.required' => 'Podaj nazwę ulicy.',
            'billing.houseNumber.required' => 'Podaj numer domu/mieszkania.',
            'billing.postalCode.required' => 'Podaj kod pocztowy.',
            'billing.postalCode.regex' => 'Format kodu pocztowego: 00-000.',
            'billing.city.required' => 'Podaj miasto.',
            'consentTerms.accepted' => 'Akceptacja regulaminu jest wymagana.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        // Coerce empty strings to null in optional billing fields so `required_if` works.
        if (isset($input['billing']) && is_array($input['billing'])) {
            foreach ($input['billing'] as $key => $value) {
                if (is_string($value) && trim($value) === '') {
                    $input['billing'][$key] = null;
                }
            }
        }
        $this->replace($input);
    }

    /**
     * Returns true when the honeypot field was filled — caller should silently
     * pretend the order was placed without actually creating one.
     */
    public function looksLikeBot(): bool
    {
        return is_string($this->input('website')) && $this->input('website') !== '';
    }
}
