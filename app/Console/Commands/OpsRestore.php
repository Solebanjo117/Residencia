<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OpsRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:restore
                            {snapshot : Snapshot directory name under storage/app/backups}
                            {--only=all : Component to restore: all, db, files}
                            {--force : Restore without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore sqlite database and storage files from an operational backup snapshot';

    public function handle(): int
    {
        $startedAt = microtime(true);

        $snapshotName = trim((string) $this->argument('snapshot'));
        $snapshotPath = storage_path('app/backups') . DIRECTORY_SEPARATOR . $snapshotName;
        $only = strtolower((string) $this->option('only'));

        if (!in_array($only, ['all', 'db', 'files'], true)) {
            $this->error('El valor de --only debe ser all, db o files.');
            return self::FAILURE;
        }

        if (!File::isDirectory($snapshotPath)) {
            $this->error("Snapshot no encontrado: {$snapshotName}");
            return self::FAILURE;
        }

        $manifestPath = $snapshotPath . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!File::exists($manifestPath)) {
            $this->error('El snapshot no contiene manifest.json');
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                "Se restaurara el snapshot {$snapshotName} (only={$only}). Esta accion sobrescribe datos. Continuar?",
                false
            );

            if (!$confirmed) {
                $this->warn('Restauracion cancelada por el usuario.');
                return self::FAILURE;
            }
        }

        Log::channel('operations')->info('restore.started', [
            'command' => $this->getName(),
            'snapshot' => $snapshotName,
            'only' => $only,
        ]);

        try {
            if ($only !== 'files') {
                $this->restoreDatabase($snapshotPath);
            }

            if ($only !== 'db') {
                $this->restoreFiles($snapshotPath);
            }

            $this->info("Restauracion completada para snapshot {$snapshotName}");

            Log::channel('operations')->info('restore.completed', [
                'command' => $this->getName(),
                'snapshot' => $snapshotName,
                'only' => $only,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::channel('operations')->error('restore.failed', [
                'command' => $this->getName(),
                'snapshot' => $snapshotName,
                'only' => $only,
                'reason' => $exception->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            $this->error('Error durante restauracion: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function restoreDatabase(string $snapshotPath): void
    {
        $snapshotDbPath = $snapshotPath . DIRECTORY_SEPARATOR . 'database.sqlite';

        if (!File::exists($snapshotDbPath)) {
            throw new \RuntimeException('No existe database.sqlite en el snapshot.');
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            throw new \RuntimeException('La restauracion automatica soporta solo sqlite.');
        }

        $targetDbPath = $this->resolveSqlitePath($connection);
        if ($targetDbPath === null) {
            throw new \RuntimeException('No se pudo resolver el path sqlite de destino.');
        }

        File::ensureDirectoryExists(dirname($targetDbPath));

        if (File::exists($targetDbPath)) {
            File::copy($targetDbPath, $snapshotPath . DIRECTORY_SEPARATOR . 'pre_restore_current_database.sqlite');
        }

        File::copy($snapshotDbPath, $targetDbPath);
    }

    private function restoreFiles(string $snapshotPath): void
    {
        $snapshotFilesPath = $snapshotPath . DIRECTORY_SEPARATOR . 'storage_app';

        if (!File::isDirectory($snapshotFilesPath)) {
            throw new \RuntimeException('No existe storage_app en el snapshot.');
        }

        $targetStoragePath = storage_path('app');
        File::ensureDirectoryExists($targetStoragePath);

        // Keep backup history while restoring operational files.
        foreach (File::directories($targetStoragePath) as $directory) {
            if (basename($directory) === 'backups') {
                continue;
            }

            File::deleteDirectory($directory);
        }

        foreach (File::files($targetStoragePath) as $file) {
            File::delete($file->getPathname());
        }

        $this->copyDirectoryContents($snapshotFilesPath, $targetStoragePath);
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

    private function copyDirectoryContents(string $source, string $destination): void
    {
        File::ensureDirectoryExists($destination);

        foreach (File::directories($source) as $directory) {
            $name = basename($directory);
            $this->copyDirectoryContents($directory, $destination . DIRECTORY_SEPARATOR . $name);
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
