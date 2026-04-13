<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function configureOpsBackupSqlite(string $dbFilePath): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.driver' => 'sqlite',
        'database.connections.sqlite.database' => $dbFilePath,
    ]);
}

function resetOpsBackupSqliteConfig(): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.driver' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);
}

function cleanupOpsArtifacts(string $token): void
{
    File::delete(database_path("ops_backup_{$token}.sqlite"));
    File::deleteDirectory(storage_path('app/ops_backup_' . $token));

    if (!File::isDirectory(storage_path('app/backups'))) {
        return;
    }

    $backupDirectories = collect(File::directories(storage_path('app/backups')))
        ->filter(fn (string $path) => str_contains(basename($path), $token));

    foreach ($backupDirectories as $directory) {
        File::deleteDirectory($directory);
    }
}

it('creates a backup snapshot with sqlite and storage payload', function () {
    $token = Str::lower(Str::random(8));
    $dbFilePath = database_path("ops_backup_{$token}.sqlite");

    File::put($dbFilePath, 'db-original-' . $token);
    configureOpsBackupSqlite($dbFilePath);

    $storageRelativePath = 'ops_backup_' . $token . '/proof.txt';
    $storageAbsolutePath = storage_path('app/' . $storageRelativePath);
    File::ensureDirectoryExists(dirname($storageAbsolutePath));
    File::put($storageAbsolutePath, 'file-original-' . $token);

    $this->artisan('ops:backup --name=ops-test-' . $token)
        ->assertExitCode(0);

    $snapshotPath = collect(File::directories(storage_path('app/backups')))
        ->filter(fn (string $path) => str_contains(basename($path), 'ops-test-' . $token . '_'))
        ->sortDesc()
        ->first();

    expect($snapshotPath)->not->toBeNull();
    expect(File::exists($snapshotPath . '/manifest.json'))->toBeTrue();
    expect(File::exists($snapshotPath . '/database.sqlite'))->toBeTrue();
    expect(File::exists($snapshotPath . '/storage_app/' . $storageRelativePath))->toBeTrue();

    cleanupOpsArtifacts($token);
    resetOpsBackupSqliteConfig();
});

it('restores sqlite and storage payload from snapshot', function () {
    $token = Str::lower(Str::random(8));
    $dbFilePath = database_path("ops_backup_{$token}.sqlite");

    File::put($dbFilePath, 'db-v1-' . $token);
    configureOpsBackupSqlite($dbFilePath);

    $storageRelativePath = 'ops_backup_' . $token . '/proof.txt';
    $storageAbsolutePath = storage_path('app/' . $storageRelativePath);
    File::ensureDirectoryExists(dirname($storageAbsolutePath));
    File::put($storageAbsolutePath, 'file-v1-' . $token);

    $this->artisan('ops:backup --name=ops-test-' . $token)
        ->assertExitCode(0);

    $snapshotPath = collect(File::directories(storage_path('app/backups')))
        ->filter(fn (string $path) => str_contains(basename($path), 'ops-test-' . $token . '_'))
        ->sortDesc()
        ->first();

    expect($snapshotPath)->not->toBeNull();

    File::put($dbFilePath, 'db-v2-' . $token);
    File::put($storageAbsolutePath, 'file-v2-' . $token);

    $this->artisan('ops:restore ' . basename($snapshotPath) . ' --force')
        ->assertExitCode(0);

    expect(File::get($dbFilePath))->toBe('db-v1-' . $token);
    expect(File::get($storageAbsolutePath))->toBe('file-v1-' . $token);

    cleanupOpsArtifacts($token);
    resetOpsBackupSqliteConfig();
});
