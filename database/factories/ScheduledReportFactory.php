<?php

namespace Database\Factories;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ScheduledReport> */
class ScheduledReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Weekly sales summary',
            'report_type' => ReportType::SalesSummary,
            'frequency' => ReportFrequency::Weekly,
            'recipients' => ['owner@wanderlink.test'],
        ];
    }
}
