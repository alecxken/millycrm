<?php

namespace App\Policies;

use App\Models\ScheduledReport;
use App\Models\User;

class ScheduledReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function update(User $user, ScheduledReport $report): bool
    {
        return $user->can('reports.view');
    }

    public function delete(User $user, ScheduledReport $report): bool
    {
        return $report->created_by === $user->id || $user->isManagerOrAbove();
    }
}
