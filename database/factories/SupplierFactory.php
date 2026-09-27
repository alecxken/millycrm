<?php

namespace Database\Factories;

use App\Enums\SupplierCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'category' => SupplierCategory::Hotel,
            'contact_name' => $this->faker->name(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'commission_rate' => 10,
            'rating' => 4.2,
        ];
    }
}
