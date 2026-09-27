<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 week', '+3 months');

        return [
            'customer_id' => Customer::factory(),
            'destination' => 'Zanzibar',
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+5 days'),
            'total_amount' => 150000,
            'amount_paid' => 0,
            'currency' => 'KES',
            'payment_status' => PaymentStatus::Pending,
            'status' => BookingStatus::Confirmed,
        ];
    }
}
