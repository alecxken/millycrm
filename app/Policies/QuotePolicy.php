<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    use OwnsRecords;

    public function view(User $user, Quote $quote): bool
    {
        return $user->can('pipeline.view') && $this->ownsOrOversees($user, $quote->enquiry->assigned_to);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can('pipeline.manage') && $this->ownsOrOversees($user, $quote->enquiry->assigned_to);
    }

    /** Discounts above 5% of the quote value need a manager. */
    public function approveDiscount(User $user): bool
    {
        return $user->can('discounts.approve');
    }
}
