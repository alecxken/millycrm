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

        if ($connection->getDriverName() === 'sqlite' && $connection->transactionLevel() > 0) {
            // VACUUM cannot run inside a transaction: fall back to a portable SQL dump.
            return $this->sqliteDump($this->directory()."/wanderlink-{$stamp}.sql");
        }

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

    private function sqliteDump(string $target): string
    {
        $pdo = DB::connection()->getPdo();
        $out = fopen($target, 'w');
        fwrite($out, "-- WanderLink CRM backup ".now()->toIso8601String()."\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");

        foreach (DB::select("select name, sql from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $table) {
            fwrite($out, $table->sql.";\n");
            foreach (DB::table($table->name)->get() as $row) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                fwrite($out, 'INSERT INTO "'.$table->name.'" VALUES ('.implode(',', $values).");\n");
            }
        }

        fwrite($out, "COMMIT;\n");
        fclose($out);

        return $target;
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
