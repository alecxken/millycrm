<?php

use App\Enums\EnquiryStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\ServiceTicket;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->consultant = User::factory()->withRole(Role::Consultant)->create();
    $this->actingAs($this->consultant);
});

it('asks for a lost reason when a card is dragged to Lost, then records it', function () {
    $enquiry = Enquiry::factory()->create(['assigned_to' => $this->consultant->id]);

    Volt::test('pages.pipeline.index')
        ->call('moveCard', $enquiry->id, 'lost', 0)
        ->assertSet('panel', 'lost')
        ->call('confirmLost')->assertHasErrors('lostReason')
        ->set('lostReason', 'Booked with another agency')
        ->call('confirmLost')->assertHasNoErrors();

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Lost)
        ->and($enquiry->fresh()->lost_reason)->toBe('Booked with another agency');
});

it('moves cards between stages with undo', function () {
    $enquiry = Enquiry::factory()->create(['assigned_to' => $this->consultant->id]);

    $component = Volt::test('pages.pipeline.index')->call('moveCard', $enquiry->id, 'contacted', 0)->assertDispatched('toast');
    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Contacted);

    $component->call('undoMove', $enquiry->id, 'new');
    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::New);
});

it('stops consultants moving other people\'s enquiries', function () {
    $enquiry = Enquiry::factory()->create(['assigned_to' => User::factory()->withRole(Role::Consultant)->create()->id]);

    Volt::test('pages.pipeline.index')->call('moveCard', $enquiry->id, 'contacted', 0)->assertForbidden();
});

it('logs a call from Customer 360 and schedules a follow-up', function () {
    $customer = Customer::factory()->create(['assigned_to' => $this->consultant->id]);

    Volt::test('pages.customers.show', ['customer' => $customer])
        ->call('open', 'log-call')
        ->set('log.subject', 'Discussed Zanzibar dates')
        ->set('log.follow_up', true)
        ->call('logInteraction')
        ->assertHasNoErrors()
        ->assertSet('panel', null)
        ->assertDispatched('toast');

    expect($customer->interactions()->count())->toBe(1)
        ->and($customer->tasks()->count())->toBe(1)
        ->and($customer->fresh()->last_contacted_at)->not->toBeNull();
});

it('builds a quote line with the default markup for its type', function () {
    $enquiry = Enquiry::factory()->create(['assigned_to' => $this->consultant->id]);
    $quote = app(\App\Services\QuoteService::class)->createDraft($enquiry);

    Volt::test('pages.quotes.builder', ['quote' => $quote])
        ->set('item.type', 'hotel')->assertSet('item.markup', '15')
        ->set('item.description', 'Beach resort, 5 nights')
        ->set('item.cost', '100000')
        ->call('addItem')->assertHasNoErrors();

    expect($quote->fresh()->total_amount)->toEqual('115000.00');
});

it('blocks consultants from giving discounts above 5%', function () {
    $enquiry = Enquiry::factory()->create(['assigned_to' => $this->consultant->id]);
    $quote = app(\App\Services\QuoteService::class)->createDraft($enquiry);
    app(\App\Services\QuoteService::class)->addItem($quote, ['type' => 'hotel', 'description' => 'Hotel', 'cost' => 100000, 'markup' => 0]);

    Volt::test('pages.quotes.builder', ['quote' => $quote])
        ->set('discount', '20000')->call('saveTerms')->assertHasErrors('discount')
        ->set('discount', '4000')->call('saveTerms')->assertHasNoErrors();
});

it('lets support resolve a ticket with a resolution note', function () {
    $this->actingAs(User::factory()->withRole(Role::Support)->create());
    $ticket = app(\App\Services\TicketService::class)->open(['customer_id' => Customer::factory()->create()->id, 'subject' => 'Lost passport', 'category' => 'lost_document', 'priority' => 'urgent']);

    expect($ticket->sla_due_at->diffInHours($ticket->created_at, true))->toEqual(4);

    Volt::test('pages.tickets.show', ['ticket' => $ticket])
        ->set('resolution', 'Emergency travel document issued.')
        ->call('resolve')->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});
