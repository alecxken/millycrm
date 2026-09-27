<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Services\ReportService;
use Illuminate\Console\Command;

class RunScheduledReports extends Command
{
    protected $signature = 'crm:run-scheduled-reports {--all : Run every report, not just the ones that are due}';

    protected $description = 'Generate due scheduled MIS reports and send them to recipients (log mail driver)';

    public function handle(ReportService $reports): int
    {
        $due = ScheduledReport::all()->filter(fn (ScheduledReport $r) => $this->option('all') || $r->isDue());

        if ($due->isEmpty()) {
            $this->info('No scheduled reports are due.');

            return self::SUCCESS;
        }

        foreach ($due as $report) {
            $reports->deliver($report);
            $this->line("✓ {$report->name} → ".implode(', ', $report->recipients));
        }

        $this->info("Generated {$due->count()} report(s).");

        return self::SUCCESS;
    }
}
