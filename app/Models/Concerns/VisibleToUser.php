<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row-level security: consultants only see records assigned to them;
 * managers, owners, marketing and support see everything they have
 * module access to.
 */
trait VisibleToUser
{
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(Role::Consultant->value) && ! $user->isManagerOrAbove()) {
            return $query->where($this->getTable().'.'.$this->ownerColumn(), $user->id);
        }

        return $query;
    }

    public function ownerColumn(): string
    {
        return 'assigned_to';
    }
}
