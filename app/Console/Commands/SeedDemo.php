<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    protected $signature = 'crm:seed-demo {--if-empty : Only seed when the database has no users yet}';

    protected $description = 'Load the WanderLink demo data (used by the deploy script on first install)';

    public function handle(): int
    {
        if ($this->option('if-empty') && User::query()->exists()) {
            $this->info('Demo data already present — skipping seed.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Demo data loaded. Log in with owner@wanderlink.test / password.');

        return self::SUCCESS;
    }
}
