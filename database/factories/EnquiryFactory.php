<?php

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Enums\EnquiryStatus;
use App\Enums\TravelStyle;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Enquiry> */
class EnquiryFactory extends Factory
{
    public function definition(): array
    {
        $departure = $this->faker->dateTimeBetween('+2 weeks', '+4 months');

        return [
            'customer_id' => Customer::factory(),
            'destination' => $this->faker->randomElement(['Maasai Mara', 'Diani Beach', 'Zanzibar', 'Dubai', 'Cape Town']),
            'departure_date' => $departure,
            'return_date' => (clone $departure)->modify('+6 days'),
            'travellers_adults' => 2,
            'travellers_children' => 0,
            'budget' => 200000,
            'trip_type' => TravelStyle::Beach,
            'channel' => CustomerSource::WhatsApp,
            'status' => EnquiryStatus::New,
            'expected_value' => 180000,
            'probability' => 10,
            'stage_changed_at' => now(),
            'last_activity_at' => now(),
        ];
    }
}
