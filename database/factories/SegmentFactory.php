<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Segment> */
class SegmentFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Test segment', 'rules' => []];
    }
}
