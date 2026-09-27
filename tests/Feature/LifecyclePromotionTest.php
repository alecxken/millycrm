<?php

use App\Enums\BookingStatus;
use App\Enums\LifecycleStage;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\LifecycleService;

it('promotes a lead to customer on the first booking', function () {
    $customer = Customer::factory()->stage(LifecycleStage::Lead)->create();

    Booking::factory()->for($customer)->create(['total_amount' => 100000]);

    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Customer);
});

it('promotes a customer to repeat on the second booking', function () {
    $customer = Customer::factory()->create();
    Booking::factory()->count(2)->for($customer)->create(['total_amount' => 100000]);

    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Repeat);
});

it('promotes to VIP at five bookings', function () {
    $customer = Customer::factory()->create();
    Booking::factory()->count(5)->for($customer)->create(['total_amount' => 50000]);

    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Vip);
});

it('promotes to VIP at a lifetime value of KES 1,000,000', function () {
    $customer = Customer::factory()->create();
    Booking::factory()->for($customer)->create(['total_amount' => 600000]);
    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Customer);

    Booking::factory()->for($customer)->create(['total_amount' => 400000]);
    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Vip);
});

it('ignores cancelled bookings', function () {
    $customer = Customer::factory()->create();
    Booking::factory()->for($customer)->create(['status' => BookingStatus::Cancelled]);

    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Lead);
});

it('marks customers inactive after 18 months without activity', function () {
    $customer = Customer::factory()->create(['created_at' => now()->subYears(3)]);
    Booking::factory()->for($customer)->create([
        'start_date' => now()->subMonths(24), 'end_date' => now()->subMonths(24)->addDays(5),
        'created_at' => now()->subMonths(25),
    ]);
    // Simulate a customer who was active until the sweep runs.
    Customer::whereKey($customer->id)->update(['last_contacted_at' => now()->subMonths(20), 'lifecycle_stage' => LifecycleStage::Customer]);

    $changed = app(LifecycleService::class)->sweep();

    expect($changed)->toBeGreaterThanOrEqual(1)
        ->and($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Inactive);
});

it('never demotes a manually flagged VIP', function () {
    $customer = Customer::factory()->stage(LifecycleStage::Vip)->create();
    Booking::factory()->for($customer)->create();

    expect($customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Vip);
});
