<?php

use App\Enums\ReportFrequency;
use App\Models\Booking;
use App\Models\ScheduledReport;
use Illuminate\Support\Facades\Mail;

it('generates due reports and emails recipients', function () {
    Mail::fake();
    Booking::factory()->count(3)->create(['total_amount' => 100000]);

    $due = ScheduledReport::factory()->create(['frequency' => ReportFrequency::Weekly, 'last_run_at' => now()->subDays(8), 'recipients' => ['owner@wanderlink.test', 'manager@wanderlink.test']]);
    $notDue = ScheduledReport::factory()->create(['frequency' => ReportFrequency::Monthly, 'last_run_at' => now()->subDays(3)]);

    $this->artisan('crm:run-scheduled-reports')
        ->expectsOutputToContain('Generated 1 report(s).')
        ->assertSuccessful();

    expect($due->fresh()->last_run_at->isToday())->toBeTrue()
        ->and($due->fresh()->last_output)->toContain('New bookings')->toContain('3')
        ->and($notDue->fresh()->last_run_at->isToday())->toBeFalse();
});

it('reports when nothing is due and supports --all', function () {
    ScheduledReport::factory()->create(['frequency' => ReportFrequency::Daily, 'last_run_at' => now()->subHour()]);

    $this->artisan('crm:run-scheduled-reports')->expectsOutput('No scheduled reports are due.')->assertSuccessful();
    $this->artisan('crm:run-scheduled-reports --all')->expectsOutputToContain('Generated 1 report(s).')->assertSuccessful();
});

it('is registered on the scheduler', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');

    expect($events)->toContain('crm:run-scheduled-reports')->toContain('crm:backup')->toContain('crm:daily');
});
