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
