<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    use OwnsRecords;

    public function viewAny(User $user): bool
    {
        return $user->can('pipeline.view');
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        return $user->can('pipeline.view') && $this->ownsOrOversees($user, $enquiry->assigned_to);
    }

    public function create(User $user): bool
    {
        return $user->can('pipeline.manage');
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        return $user->can('pipeline.manage') && $this->ownsOrOversees($user, $enquiry->assigned_to);
    }

    public function reassign(User $user): bool
    {
        return $user->can('pipeline.view_all');
    }
}
