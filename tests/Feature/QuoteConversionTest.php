<?php

use App\Enums\BookingStatus;
use App\Enums\EnquiryStatus;
use App\Enums\LifecycleStage;
use App\Enums\QuoteStatus;
use App\Enums\Role;
use App\Enums\TaskType;
use App\Models\Enquiry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\QuoteService;

beforeEach(function () {
    $this->consultant = User::factory()->withRole(Role::Consultant)->create();
    $this->actingAs($this->consultant);
    $this->enquiry = Enquiry::factory()->create(['assigned_to' => $this->consultant->id]);
    $this->quotes = app(QuoteService::class);
});

it('applies markup automatically to quote lines', function () {
    $quote = $this->quotes->createDraft($this->enquiry);
    $this->quotes->addItem($quote, ['type' => 'hotel', 'description' => 'Hotel', 'cost' => 100000, 'markup' => 15, 'supplier_id' => Supplier::factory()->create()->id]);
    $this->quotes->addItem($quote, ['type' => 'flight', 'description' => 'Flights', 'cost' => 50000, 'markup' => 8]);

    expect($quote->fresh()->total_amount)->toEqual('169000.00')
        ->and(QuoteService::priceFor(1000, 12.5))->toBe(1125.0);
});

it('sending a quote moves the enquiry to quoted and schedules a follow-up in 2 days', function () {
    $quote = $this->quotes->createDraft($this->enquiry);
    $this->quotes->addItem($quote, ['type' => 'tour', 'description' => 'Safari', 'cost' => 80000, 'markup' => 18]);

    $this->quotes->send($quote);

    $task = $this->enquiry->tasks()->first();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent)
        ->and($this->enquiry->fresh()->status)->toBe(EnquiryStatus::Quoted)
        ->and($task->type)->toBe(TaskType::FollowUp)
        ->and($task->due_at->isSameDay(now()->addDays(2)))->toBeTrue();
});

it('converts a quote to a booking in one step', function () {
    $quote = $this->quotes->createDraft($this->enquiry);
    $this->quotes->addItem($quote, ['type' => 'hotel', 'description' => 'Hotel', 'cost' => 100000, 'markup' => 15]);

    $booking = $this->quotes->convertToBooking($quote);

    expect($booking->total_amount)->toEqual('115000.00')
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->customer_id)->toBe($this->enquiry->customer_id)
        ->and($booking->start_date->toDateString())->toBe($this->enquiry->departure_date->toDateString())
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and($this->enquiry->fresh()->status)->toBe(EnquiryStatus::Won)
        ->and($this->enquiry->customer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Customer);
});

it('refuses to convert the same quote twice or an empty quote', function () {
    $empty = $this->quotes->createDraft($this->enquiry);
    expect(fn () => $this->quotes->convertToBooking($empty))->toThrow(RuntimeException::class);

    $quote = $this->quotes->createDraft($this->enquiry);
    $this->quotes->addItem($quote, ['type' => 'visa', 'description' => 'Visa', 'cost' => 5000, 'markup' => 30]);
    $this->quotes->convertToBooking($quote);

    expect(fn () => $this->quotes->convertToBooking($quote))->toThrow(RuntimeException::class);
});

it('requires a reason to mark an enquiry as lost', function () {
    $pipeline = app(\App\Services\PipelineService::class);

    expect(fn () => $pipeline->move($this->enquiry, EnquiryStatus::Lost))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $pipeline->move($this->enquiry, EnquiryStatus::Lost, 'Price too high');
    expect($this->enquiry->fresh()->lost_reason)->toBe('Price too high');
});
