<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Support\Facades\File;

it('exports a customer\'s data as JSON for subject access requests', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $customer = Customer::factory()->create(['first_name' => 'Njeri', 'passport_number' => 'B7654321']);
    Booking::factory()->for($customer)->create();

    $response = $this->actingAs($owner)->get(route('customers.export', $customer))->assertOk();
    $data = json_decode($response->streamedContent(), true);

    expect($data['customer']['first_name'])->toBe('Njeri')
        ->and($data['customer']['passport_number'])->toBe('B7654321')
        ->and($data['bookings'])->toHaveCount(1);
});

it('does not let consultants export personal data', function () {
    $consultant = User::factory()->withRole(Role::Consultant)->create();
    $customer = Customer::factory()->create(['assigned_to' => $consultant->id]);

    $this->actingAs($consultant)->get(route('customers.export', $customer))->assertForbidden();
});

it('anonymises then soft-deletes a customer but keeps bookings for audit', function () {
    $customer = Customer::factory()->create(['email' => 'secret@example.com', 'passport_number' => 'X1']);
    Booking::factory()->for($customer)->create();

    app(PrivacyService::class)->anonymise($customer);

    $fresh = Customer::withTrashed()->find($customer->id);
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->email)->toBeNull()
        ->and($fresh->passport_number)->toBeNull()
        ->and($fresh->first_name)->toBe('Anonymised')
        ->and($fresh->anonymised_at)->not->toBeNull()
        ->and(Booking::where('customer_id', $customer->id)->count())->toBe(1);
});

it('backs up the database to storage/app/backups', function () {
    Customer::factory()->create(['first_name' => 'Backup']);

    $this->artisan('crm:backup')->expectsOutputToContain('Backup written to')->assertSuccessful();

    $files = glob(storage_path('app/backups/wanderlink-*.sql'));
    expect(file_get_contents(end($files)))->toContain('CREATE TABLE "customers"')->toContain('Backup');
    expect($files)->not->toBeEmpty();
    File::delete(end($files));
});
