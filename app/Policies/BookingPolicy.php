<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    use OwnsRecords;

    public function viewAny(User $user): bool
    {
        return $user->can('pipeline.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return ($user->can('pipeline.view') || $user->can('tickets.view')) && $this->ownsOrOversees($user, $booking->consultant_id);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('pipeline.manage') && $this->ownsOrOversees($user, $booking->consultant_id);
    }
}
