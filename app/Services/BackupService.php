<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /** Dump the database to storage/app/backups/wanderlink-YYYYmmdd-His.(sqlite|sql). */
    public function run(): string
    {
        File::ensureDirectoryExists($this->directory());
        $stamp = now()->format('Ymd-His');
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
            $target = $this->directory()."/wanderlink-{$stamp}.sqlite";
            // VACUUM INTO produces a consistent copy even while the app is running.
            $connection->statement('VACUUM INTO ?', [$target]);

            return $target;
        }

        if ($connection->getDriverName() === 'mysql') {
            $config = $connection->getConfig();
            $target = $this->directory()."/wanderlink-{$stamp}.sql";
            $process = new Process([
                'mysqldump', '--single-transaction', '-h', $config['host'], '-P', (string) $config['port'],
                '-u', $config['username'], $config['database'], '--result-file='.$target,
            ], env: ['MYSQL_PWD' => $config['password']]);
            $process->mustRun();

            return $target;
        }

        throw new RuntimeException('Backups are supported for SQLite and MySQL only.');
    }

    /** @return Collection<int, array{name:string, size:int, created_at:\Illuminate\Support\Carbon}> */
    public function list(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::files($this->directory()))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => \Illuminate\Support\Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->sortByDesc('created_at')->values();
    }

    public function path(string $name): string
    {
        $path = $this->directory().'/'.basename($name);
        abort_unless(File::exists($path), 404);

        return $path;
    }
}
