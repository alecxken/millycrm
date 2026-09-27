<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'crm:backup';

    protected $description = 'Dump the CRM database to storage/app/backups with a timestamp';

    public function handle(BackupService $backups): int
    {
        $path = $backups->run();
        activity()->log('database backup created: '.basename($path));
        $this->info('Backup written to '.$path);

        return self::SUCCESS;
    }
}
