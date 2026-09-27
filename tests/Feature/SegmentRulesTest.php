<?php

use App\Enums\BookingStatus;
use App\Enums\CustomerType;
use App\Enums\LifecycleStage;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tag;
use App\Services\SegmentService;

beforeEach(fn () => $this->segments = app(SegmentService::class));

it('matches the example rule set from the brief', function () {
    $match = Customer::factory()->consented()->stage(LifecycleStage::Vip)->create();
    $match->preference()->create(['travel_style' => 'safari']);

    $wrongStyle = Customer::factory()->consented()->stage(LifecycleStage::Repeat)->create();
    $wrongStyle->preference()->create(['travel_style' => 'beach']);

    $noConsent = Customer::factory()->consented(false)->stage(LifecycleStage::Repeat)->create();
    $noConsent->preference()->create(['travel_style' => 'safari']);

    $rules = ['lifecycle_stage' => ['repeat', 'vip'], 'travel_style' => ['safari'], 'marketing_consent' => true];

    expect($this->segments->query($rules)->pluck('id')->all())->toBe([$match->id])
        ->and($this->segments->matches($match, $rules))->toBeTrue()
        ->and($this->segments->matches($wrongStyle, $rules))->toBeFalse()
        ->and($this->segments->matches($noConsent, $rules))->toBeFalse();
});

it('treats empty rules as "everyone"', function () {
    Customer::factory()->count(4)->create();

    expect($this->segments->count(['lifecycle_stage' => [], 'min_bookings' => '']))->toBe(4)
        ->and($this->segments->describe([]))->toBe('All customers');
});

it('filters by bookings, lifetime value, tags and type', function () {
    $big = Customer::factory()->create(['type' => CustomerType::Corporate]);
    Booking::factory()->count(2)->for($big)->create(['total_amount' => 300000]);
    $tag = Tag::create(['name' => 'Frequent flyer']);
    $big->tags()->attach($tag);

    $small = Customer::factory()->create();
    Booking::factory()->for($small)->create(['total_amount' => 50000]);
    Booking::factory()->for($small)->create(['total_amount' => 900000, 'status' => BookingStatus::Cancelled]);

    expect($this->segments->query(['min_bookings' => 2])->pluck('id')->all())->toBe([$big->id])
        ->and($this->segments->query(['min_lifetime_value' => 500000])->pluck('id')->all())->toBe([$big->id])
        ->and($this->segments->query(['tags' => [$tag->id]])->pluck('id')->all())->toBe([$big->id])
        ->and($this->segments->query(['type' => ['corporate']])->pluck('id')->all())->toBe([$big->id]);
});

it('finds lapsed travellers', function () {
    $lapsed = Customer::factory()->create();
    Booking::factory()->for($lapsed)->create(['start_date' => now()->subMonths(14), 'end_date' => now()->subMonths(14)->addWeek()]);
    $recent = Customer::factory()->create();
    Booking::factory()->for($recent)->create(['start_date' => now()->subMonths(2), 'end_date' => now()->subMonths(2)->addWeek()]);
    Customer::factory()->create(); // never travelled

    expect($this->segments->query(['not_travelled_within_months' => 12])->pluck('id')->all())->toBe([$lapsed->id]);
});
