<?php

namespace App\Policies;

use App\Models\ServiceTicket;
use App\Models\User;

class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, ServiceTicket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.manage') || $user->can('customers.manage');
    }

    public function update(User $user, ServiceTicket $ticket): bool
    {
        return $user->can('tickets.manage');
    }
}
