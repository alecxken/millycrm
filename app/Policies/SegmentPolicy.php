<?php

namespace App\Policies;

use App\Models\Segment;
use App\Models\User;

class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('marketing.view');
    }

    public function view(User $user, Segment $model): bool
    {
        return $user->can('marketing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('marketing.manage');
    }

    public function update(User $user, Segment $model): bool
    {
        return $user->can('marketing.manage');
    }

    public function delete(User $user, Segment $model): bool
    {
        return $user->can('marketing.manage');
    }
}
