<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('marketing.view');
    }

    public function view(User $user, Campaign $model): bool
    {
        return $user->can('marketing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('marketing.manage');
    }

    public function update(User $user, Campaign $model): bool
    {
        return $user->can('marketing.manage');
    }

    public function delete(User $user, Campaign $model): bool
    {
        return $user->can('marketing.manage');
    }
}
