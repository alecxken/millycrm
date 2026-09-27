<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Shared row-level rule: consultants only touch records assigned to them. */
trait OwnsRecords
{
    protected function ownsOrOversees(User $user, ?int $ownerId): bool
    {
        if (! $user->hasRole(Role::Consultant->value) || $user->isManagerOrAbove()) {
            return true;
        }

        return $ownerId === $user->id;
    }
}
