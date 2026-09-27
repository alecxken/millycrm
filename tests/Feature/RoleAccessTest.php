<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\User;

function userWith(Role $role): User
{
    return User::factory()->withRole($role)->create();
}

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

it('blocks roles from modules they cannot use', function (Role $role, string $route, int $status) {
    $this->actingAs(userWith($role))->get(route($route))->assertStatus($status);
})->with([
    'consultant → marketing' => [Role::Consultant, 'campaigns.index', 403],
    'consultant → tickets' => [Role::Consultant, 'tickets.index', 403],
    'consultant → audit' => [Role::Consultant, 'admin.audit', 403],
    'consultant → reports' => [Role::Consultant, 'reports.builder', 403],
    'marketing → pipeline' => [Role::Marketing, 'pipeline', 403],
    'marketing → segments' => [Role::Marketing, 'segments.index', 200],
    'support → pipeline' => [Role::Support, 'pipeline', 403],
    'support → tickets' => [Role::Support, 'tickets.index', 200],
    'manager → backups' => [Role::Manager, 'admin.backups', 403],
    'owner → backups' => [Role::Owner, 'admin.backups', 200],
    'manager → audit' => [Role::Manager, 'admin.audit', 200],
]);

it('hides modules from the sidebar instead of showing error pages', function () {
    $this->actingAs(userWith(Role::Consultant))->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pipeline')
        ->assertDontSee('Campaigns')
        ->assertDontSee('Audit trail');
});

it('lets consultants see only their own customers and enquiries', function () {
    $me = userWith(Role::Consultant);
    $other = userWith(Role::Consultant);
    $mine = Customer::factory()->create(['assigned_to' => $me->id]);
    $theirs = Customer::factory()->create(['assigned_to' => $other->id]);
    $theirEnquiry = Enquiry::factory()->for($theirs)->create(['assigned_to' => $other->id]);

    $this->actingAs($me);
    $this->get(route('customers.show', $mine))->assertOk();
    $this->get(route('customers.show', $theirs))->assertForbidden();
    expect(Customer::visibleTo($me)->pluck('id')->all())->toBe([$mine->id])
        ->and(Enquiry::visibleTo($me)->whereKey($theirEnquiry->id)->exists())->toBeFalse();
});

it('lets managers see every consultant\'s records', function () {
    $consultant = userWith(Role::Consultant);
    $customer = Customer::factory()->create(['assigned_to' => $consultant->id]);

    $this->actingAs(userWith(Role::Manager))->get(route('customers.show', $customer))->assertOk();
});

it('does not allow deactivated staff to sign in', function () {
    $user = User::factory()->create(['is_active' => false]);

    \Livewire\Volt\Volt::test('pages.auth.login')
        ->set('form.email', $user->email)->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors('form.email');

    $this->assertGuest();
});

it('stores passport numbers encrypted at rest', function () {
    $customer = Customer::factory()->create(['passport_number' => 'A1234567']);

    $raw = \Illuminate\Support\Facades\DB::table('customers')->where('id', $customer->id)->value('passport_number');

    expect($raw)->not->toContain('A1234567')
        ->and($customer->fresh()->passport_number)->toBe('A1234567');
});
