<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpsBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:backup {--name= : Optional backup label suffix} {--no-files : Skip backing up storage/app files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an operational backup snapshot for sqlite database and storage files';

    public function handle(): int
    {
        $startedAt = microtime(true);

        $snapshotName = $this->buildSnapshotName((string) $this->option('name'));
        $backupsPath = storage_path('app/backups');
        $snapshotPath = $backupsPath . DIRECTORY_SEPARATOR . $snapshotName;

        File::ensureDirectoryExists($snapshotPath);

        $includeFiles = !$this->option('no-files');

        Log::channel('operations')->info('backup.started', [
            'command' => $this->getName(),
            'snapshot' => $snapshotName,
            'include_files' => $includeFiles,
        ]);

        $manifest = [
            'snapshot' => $snapshotName,
            'created_at' => now()->toIso8601String(),
            'app_env' => app()->environment(),
            'database' => [
                'connection' => (string) config('database.default'),
                'driver' => null,
                'source' => null,
                'backup_file' => null,
            ],
            'files' => [
                'included' => $includeFiles,
                'source' => 'storage/app',
                'backup_path' => null,
            ],
        ];

        try {
            $databaseBackupPath = $this->backupDatabase($snapshotPath, $manifest);

            if ($databaseBackupPath === null) {
                $this->error('No se pudo resolver una base sqlite valida para backup.');
                Log::channel('operations')->error('backup.failed', [
                    'command' => $this->getName(),
                    'snapshot' => $snapshotName,
                    'reason' => 'sqlite_path_unresolved',
                    'duration_ms' => $this->elapsedMs($startedAt),
                ]);

                return self::FAILURE;
            }

            if ($includeFiles) {
                $filesBackupPath = $snapshotPath . DIRECTORY_SEPARATOR . 'storage_app';
                $this->copyDirectoryContents(storage_path('app'), $filesBackupPath, ['backups']);
                $manifest['files']['backup_path'] = 'storage_app';
            }

            File::put(
                $snapshotPath . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->info("Backup creado: {$snapshotName}");
            $this->line("DB: {$databaseBackupPath}");

            if ($includeFiles) {
                $this->line('Files: storage_app');
            }

            Log::channel('operations')->info('backup.completed', [
                'command' => $this->getName(),
                'snapshot' => $snapshotName,
                'database_backup' => $databaseBackupPath,
                'files_included' => $includeFiles,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::channel('operations')->error('backup.failed', [
                'command' => $this->getName(),
                'snapshot' => $snapshotName,
                'reason' => $exception->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            $this->error('Error durante backup: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function buildSnapshotName(string $rawName): string
    {
        $suffix = trim($rawName) !== '' ? Str::slug($rawName) : 'snapshot';

        return $suffix . '_' . now()->format('Ymd_His');
    }

    private function backupDatabase(string $snapshotPath, array &$manifest): ?string
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $manifest['database']['driver'] = $driver;

        if ($driver !== 'sqlite') {
            throw new \RuntimeException('La estrategia actual de backup automatico soporta solo sqlite.');
        }

        $sqlitePath = $this->resolveSqlitePath($connection);

        if ($sqlitePath === null || !File::exists($sqlitePath)) {
            return null;
        }

        $target = $snapshotPath . DIRECTORY_SEPARATOR . 'database.sqlite';
        File::copy($sqlitePath, $target);

        $manifest['database']['source'] = $sqlitePath;
        $manifest['database']['backup_file'] = 'database.sqlite';

        return 'database.sqlite';
    }

    private function resolveSqlitePath(string $connection): ?string
    {
        $configuredPath = config("database.connections.{$connection}.database");

        if (!is_string($configuredPath) || $configuredPath === '' || $configuredPath === ':memory:') {
            return null;
        }

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        return base_path($configuredPath);
    }

    private function copyDirectoryContents(string $source, string $destination, array $excludedTopLevel = []): void
    {
        File::ensureDirectoryExists($destination);

        foreach (File::directories($source) as $directory) {
            $name = basename($directory);

            if (in_array($name, $excludedTopLevel, true)) {
                continue;
            }

            $this->copyDirectoryContents(
                $directory,
                $destination . DIRECTORY_SEPARATOR . $name,
                $excludedTopLevel
            );
        }

        foreach (File::files($source) as $file) {
            File::copy($file->getPathname(), $destination . DIRECTORY_SEPARATOR . $file->getFilename());
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
