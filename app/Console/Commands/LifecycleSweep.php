<?php

namespace App\Console\Commands;

use App\Services\LifecycleService;
use App\Services\TaskAutomationService;
use Illuminate\Console\Command;

class LifecycleSweep extends Command
{
    protected $signature = 'crm:daily';

    protected $description = 'Daily housekeeping: booking statuses, feedback & re-booking tasks, lifecycle stages (incl. inactive after 18 months)';

    public function handle(TaskAutomationService $tasks, LifecycleService $lifecycle): int
    {
        $result = $tasks->run();
        $changed = $lifecycle->sweep();

        $this->info("Booking statuses updated: {$result['statuses']}");
        $this->info("Feedback requests created: {$result['feedback']}");
        $this->info("Re-booking suggestions created: {$result['rebook']}");
        $this->info("Lifecycle stages changed: {$changed}");

        return self::SUCCESS;
    }
}
