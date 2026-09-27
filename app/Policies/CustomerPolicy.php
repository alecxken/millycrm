<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    use OwnsRecords;

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $this->ownsOrOversees($user, $customer->assigned_to);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') && $this->ownsOrOversees($user, $customer->assigned_to);
    }

    public function export(User $user, Customer $customer): bool
    {
        return $user->can('customers.privacy');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.privacy');
    }
}
