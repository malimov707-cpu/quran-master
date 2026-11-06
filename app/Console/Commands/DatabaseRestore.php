<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseRestore extends Command
{
    protected $signature = 'database:restore
        {backup : Backup filename}
        {--disk=backups : Object storage disk containing backups}
        {--confirm= : Set to RESTORE to authorize destructive restoration}';

    protected $description = 'Restore a PostgreSQL backup from object storage';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'RESTORE') {
            $this->error('Pass --confirm=RESTORE to authorize database restoration.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'pgsql') {
            $this->error('Database restore currently supports the PostgreSQL production connection only.');

            return self::FAILURE;
        }

        $disk = (string) $this->option('disk');
        $filename = basename((string) $this->argument('backup'));

        if (! Storage::disk($disk)->exists($filename)) {
            $this->error("Backup not found: {$filename}");

            return self::FAILURE;
        }

        $localPath = storage_path('app/backups/'.$filename);
        if (! is_dir(dirname($localPath)) && ! mkdir(dirname($localPath), 0750, true)) {
            $this->error('Restore directory could not be created.');

            return self::FAILURE;
        }

        file_put_contents($localPath, Storage::disk($disk)->get($filename));
        $connection = config('database.connections.pgsql');

        $process = Process::timeout((int) config('backup.timeout_seconds', 3600))
            ->env(['PGPASSWORD' => (string) $connection['password']])
            ->run([
                'psql',
                '--set=ON_ERROR_STOP=1',
                '--file='.$localPath,
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
            ]);

        @unlink($localPath);

        if ($process->failed()) {
            $this->error('Database restore failed: '.$process->errorOutput());

            return self::FAILURE;
        }

        $this->info("Database restored from {$filename}.");

        return self::SUCCESS;
    }
}
