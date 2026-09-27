<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('admin.users') && ! $model->is($user);
    }
}
