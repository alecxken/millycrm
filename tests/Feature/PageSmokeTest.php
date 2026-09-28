<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/*
 * Renders every screen for every role against the full demo data set.
 * Catches N+1 lazy-loading violations, missing eager loads and Blade errors.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

dataset('roles', [
    'owner' => 'owner@wanderlink.test',
    'manager' => 'manager@wanderlink.test',
    'consultant' => 'consultant@wanderlink.test',
    'marketing' => 'marketing@wanderlink.test',
    'support' => 'support@wanderlink.test',
]);

it('renders the dashboard for every role', function (string $email) {
    $this->actingAs(User::where('email', $email)->first())
        ->get(route('dashboard'))
        ->assertOk();
})->with('roles');

it('renders the customer list and a customer profile on every tab', function () {
    $user = User::where('email', 'owner@wanderlink.test')->first();
    $customer = \App\Models\Customer::has('bookings', '>=', 2)->first();

    $this->actingAs($user)->get(route('customers.index'))->assertOk()->assertSee($customer->exists ? 'Customers' : '');
    foreach (['timeline', 'trips', 'preferences', 'documents', 'contacts'] as $tab) {
        $this->actingAs($user)->get(route('customers.show', [$customer, 'tab' => $tab]))->assertOk()->assertSee($customer->display_name);
    }
});

it('renders sales screens: pipeline, quote builder, print, bookings and My Day', function () {
    $user = User::where('email', 'consultant@wanderlink.test')->first();
    $quote = \App\Models\Quote::whereHas('enquiry', fn ($q) => $q->where('assigned_to', $user->id))->has('items')->first();
    $booking = \App\Models\Booking::where('consultant_id', $user->id)->first();

    $this->actingAs($user);
    $this->get(route('pipeline'))->assertOk()->assertSee('Sales pipeline');
    $this->get(route('pipeline', ['view' => 'list']))->assertOk();
    $this->get(route('pipeline', ['enquiry' => $quote->enquiry_id]))->assertOk()->assertSee($quote->reference);
    $this->get(route('quotes.edit', $quote))->assertOk()->assertSee($quote->reference);
    $this->get(route('quotes.print', $quote))->assertOk()->assertSee('QUOTATION');
    $this->get(route('bookings.index'))->assertOk();
    $this->get(route('bookings.show', $booking))->assertOk()->assertSee($booking->reference);
    $this->get(route('my-day'))->assertOk()->assertSee('My Day');
});

it('renders service screens and the public feedback form', function () {
    $user = User::where('email', 'support@wanderlink.test')->first();
    $ticket = \App\Models\ServiceTicket::first();
    $booking = \App\Models\Booking::doesntHave('feedback')->where('status', 'completed')->first();

    $this->actingAs($user);
    foreach (['open', 'mine', 'breached', 'resolved'] as $filter) {
        $this->get(route('tickets.index', ['filter' => $filter]))->assertOk();
    }
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->subject);
    $this->get(route('feedback.index'))->assertOk()->assertSee('Net Promoter Score');

    auth()->logout();
    $url = app(\App\Services\BookingService::class)->feedbackUrl($booking);
    $this->get($url)->assertOk()->assertSee($booking->destination);
    $this->get(route('feedback.public', $booking))->assertForbidden();
});

it('renders marketing screens', function () {
    $user = User::where('email', 'marketing@wanderlink.test')->first();
    $this->actingAs($user);
    $this->get(route('segments.index'))->assertOk()->assertSee('Segment builder');
    $this->get(route('segments.index', ['segment' => \App\Models\Segment::first()->id]))->assertOk();
    $this->get(route('campaigns.index'))->assertOk();
    foreach (\App\Models\Campaign::all() as $campaign) {
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee($campaign->name);
    }
});

it('renders suppliers and report screens', function () {
    $this->actingAs(User::where('email', 'manager@wanderlink.test')->first());
    $this->get(route('suppliers.index'))->assertOk()->assertSee('Kenya Airways');
    foreach (['customers', 'enquiries', 'bookings'] as $entity) {
        foreach (array_keys(\App\Services\ReportService::ENTITIES[$entity]['group_by']) as $group) {
            $this->get(route('reports.builder', ['entity' => $entity, 'group' => $group]))->assertOk();
        }
    }
    $this->get(route('reports.export', ['entity' => 'bookings', 'group_by' => 'destination']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->get(route('reports.scheduled'))->assertOk()->assertSee('Weekly sales summary');
});

it('renders governance screens for the owner', function () {
    $this->actingAs(User::where('email', 'owner@wanderlink.test')->first());
    $this->get(route('admin.users'))->assertOk()->assertSee('Permission matrix');
    $this->get(route('admin.audit'))->assertOk()->assertSee('Audit trail');
    $this->get(route('admin.backups'))->assertOk();
    $this->get(route('admin.appearance'))->assertOk()->assertSee('Start from a palette');
    $this->get(route('profile'))->assertOk();
});

it('shows the about-system framework page to every role', function (string $email) {
    $this->actingAs(User::where('email', $email)->first())
        ->get(route('about-system'))
        ->assertOk()
        ->assertSee('CRM process framework')
        ->assertSee('Data sources and data types')
        ->assertSee('Stakeholder benefits matrix');
})->with('roles');
