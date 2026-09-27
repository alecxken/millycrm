<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function update(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id || $user->isManagerOrAbove();
    }
}
