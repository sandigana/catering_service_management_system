<?php
// app/Console/Commands/BackupDatabase.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * php artisan db:backup [--keep=14]
 * MySQL/MariaDB: runs mysqldump (password passed through the environment, never on the command line).
 * SQLite: copies the database file. Old backups beyond --keep days are deleted.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=14 : Days of backups to keep}';

    protected $description = 'Create a database backup in storage/app/backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $stamp = now()->format('Ymd-His');

        $target = match ($config['driver']) {
            'sqlite' => $this->backupSqlite($config, "{$dir}/db-{$stamp}.sqlite"),
            'mysql', 'mariadb' => $this->backupMysql($config, "{$dir}/db-{$stamp}.sql"),
            default => null,
        };

        if (! $target) {
            $this->error("Backups are not supported for the '{$config['driver']}' driver (or the backup failed).");

            return self::FAILURE;
        }

        $this->prune($dir, (int) $this->option('keep'));
        $this->info('Backup written: ' . $target);

        return self::SUCCESS;
    }

    private function backupSqlite(array $config, string $target): ?string
    {
        $source = $config['database'];

        if ($source === ':memory:' || ! is_file($source)) {
            return null;
        }

        return copy($source, $target) ? $target : null;
    }

    private function backupMysql(array $config, string $target): ?string
    {
        $handle = fopen($target, 'w');

        $process = new Process([
            env('MYSQLDUMP_PATH', 'mysqldump'),
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            '--single-transaction',
            '--routines',
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']]);

        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });
        fclose($handle);

        if (! $process->isSuccessful()) {
            @unlink($target);
            $this->error(trim($process->getErrorOutput()));

            return null;
        }

        return $target;
    }

    private function prune(string $dir, int $keepDays): void
    {
        foreach (File::files($dir) as $file) {
            if (str_starts_with($file->getFilename(), 'db-') && $file->getMTime() < now()->subDays($keepDays)->getTimestamp()) {
                File::delete($file->getPathname());
            }
        }
    }
}
