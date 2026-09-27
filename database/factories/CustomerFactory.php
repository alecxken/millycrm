<?php

namespace Database\Factories;

use App\Enums\ContactChannel;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\LifecycleStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $consent = $this->faker->boolean(70);

        return [
            'type' => CustomerType::Individual,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+2547'.$this->faker->numerify('########'),
            'whatsapp' => null,
            'date_of_birth' => $this->faker->dateTimeBetween('-65 years', '-20 years'),
            'nationality' => 'Kenyan',
            'city' => 'Nairobi',
            'country' => 'Kenya',
            'passport_number' => 'A'.$this->faker->numerify('#######'),
            'passport_expiry' => $this->faker->dateTimeBetween('+3 months', '+8 years'),
            'preferred_contact_channel' => ContactChannel::WhatsApp,
            'source' => $this->faker->randomElement(CustomerSource::cases()),
            'lifecycle_stage' => LifecycleStage::Lead,
            'marketing_consent' => $consent,
            'consent_at' => $consent ? now()->subMonths(3) : null,
        ];
    }

    public function consented(bool $consent = true): static
    {
        return $this->state(fn () => ['marketing_consent' => $consent, 'consent_at' => $consent ? now() : null]);
    }

    public function stage(LifecycleStage $stage): static
    {
        return $this->state(fn () => ['lifecycle_stage' => $stage]);
    }
}
