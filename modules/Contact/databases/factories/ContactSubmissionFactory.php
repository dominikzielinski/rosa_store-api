<?php

declare(strict_types=1);

namespace Modules\Contact\databases\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contact\Enums\ContactSubmissionTypeEnum;
use Modules\Contact\Enums\PreferredContactEnum;
use Modules\Contact\Models\ContactSubmission;

/**
 * @extends Factory<ContactSubmission>
 */
class ContactSubmissionFactory extends Factory
{
    protected $model = ContactSubmission::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(ContactSubmissionTypeEnum::cases()),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'message' => fake()->paragraph(3),
            'phone' => fake()->optional()->numerify('+48#########'),
            'company' => null,
            'nip' => null,
            'event_type' => null,
            'gift_count' => null,
            'preferred_contact' => null,
            'consent_data' => true,
            'consent_marketing' => fake()->boolean(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function b2b(): self
    {
        return $this->state(fn () => [
            'type' => ContactSubmissionTypeEnum::B2B,
            'company' => fake()->company(),
            'nip' => (string) fake()->numerify('##########'),
            'event_type' => fake()->randomElement(['Firmowy event', 'Konferencja', 'Święta', 'Dzień kobiet']),
            'gift_count' => (string) fake()->numberBetween(5, 500),
            'preferred_contact' => fake()->randomElement(PreferredContactEnum::cases()),
        ]);
    }

    public function retail(): self
    {
        return $this->state(fn () => [
            'type' => ContactSubmissionTypeEnum::Retail,
        ]);
    }
}
