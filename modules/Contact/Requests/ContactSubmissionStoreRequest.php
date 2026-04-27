<?php

declare(strict_types=1);

namespace Modules\Contact\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Contact\Enums\ContactSubmissionTypeEnum;
use Modules\Contact\Enums\PreferredContactEnum;

class ContactSubmissionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string|object>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContactSubmissionTypeEnum::class)],
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],

            // Optional fields
            'phone' => ['nullable', 'string', 'regex:/^[+\-\d\s]{9,15}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'eventType' => ['nullable', 'string', 'max:255'],
            'giftCount' => ['nullable', 'string', 'max:50'],
            'preferredContact' => ['nullable', Rule::enum(PreferredContactEnum::class)],

            // Consents — consent_data is required true, marketing is optional
            'consentData' => ['accepted'],
            'consentMarketing' => ['nullable', 'boolean'],

            // Honeypot — handled silently by the controller, NOT validated.
            // Bots that fill it get a fake-success response (no row, no email).
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Podaj typ zgłoszenia.',
            'type.enum' => 'Nieprawidłowy typ zgłoszenia.',
            'fullName.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'message.required' => 'Wpisz wiadomość.',
            'message.min' => 'Wiadomość musi mieć minimum 10 znaków.',
            'message.max' => 'Wiadomość może mieć maksymalnie 5000 znaków.',
            'phone.regex' => 'Podaj poprawny numer telefonu.',
            'nip.regex' => 'NIP musi składać się z 10 cyfr.',
            'consentData.accepted' => 'Zgoda na przetwarzanie danych jest wymagana.',
        ];
    }

    /**
     * Returns true when the honeypot was filled — controller should silently
     * pretend the submission was received (no row, no mail, fake 201).
     */
    public function looksLikeBot(): bool
    {
        return is_string($this->input('website')) && $this->input('website') !== '';
    }

    /**
     * Normalize JSON input:
     * - empty strings → null (so `nullable` rules pass)
     * - NIP → strip dashes/spaces before regex validation
     */
    protected function prepareForValidation(): void
    {
        $input = [];
        foreach ($this->all() as $key => $value) {
            $input[$key] = is_string($value) && trim($value) === '' ? null : $value;
        }

        if (isset($input['nip']) && is_string($input['nip'])) {
            $input['nip'] = preg_replace('/[\s\-]/', '', $input['nip']);
        }

        $this->replace($input);
    }
}
