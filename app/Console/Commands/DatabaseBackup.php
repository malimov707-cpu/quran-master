<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    protected $signature = 'database:backup {--disk=backups : Object storage disk containing backups}';

    protected $description = 'Create a PostgreSQL backup and upload it to object storage';

    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->error('Database backups currently support the PostgreSQL production connection only.');

            return self::FAILURE;
        }

        $filename = 'quran-ai-ustoz-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(4)), 0, 8).'.sql';
        $localPath = storage_path('app/backups/'.$filename);
        $connection = config('database.connections.pgsql');

        if (! is_dir(dirname($localPath)) && ! mkdir(dirname($localPath), 0750, true)) {
            $this->error('Backup directory could not be created.');

            return self::FAILURE;
        }

        $process = Process::timeout((int) config('backup.timeout_seconds', 3600))
            ->env(['PGPASSWORD' => (string) $connection['password']])
            ->run([
                'pg_dump',
                '--no-owner',
                '--no-acl',
                '--file='.$localPath,
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                $connection['database'],
            ]);

        if ($process->failed()) {
            @unlink($localPath);
            $this->error('Database backup failed: '.$process->errorOutput());

            return self::FAILURE;
        }

        try {
            Storage::disk((string) $this->option('disk'))->put($filename, file_get_contents($localPath), [
                'mimetype' => 'application/sql',
            ]);
        } finally {
            @unlink($localPath);
        }

        $this->info("Backup stored as {$filename}.");

        return self::SUCCESS;
    }
}
