<?php

namespace App\Console\Commands;

use App\Models\EvidenceFile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CollapseFileVersions extends Command
{
    protected $signature = 'files:collapse-versions {--dry-run : Reporta duplicados sin borrar archivos ni registros} {--force : Ejecuta la limpieza}';

    protected $description = 'Elimina versiones antiguas de evidencias para dejar un solo archivo real vigente por cadena logica';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Usa --dry-run para revisar o --force para eliminar duplicados.');

            return self::FAILURE;
        }

        $groups = $this->duplicateGroups();
        $duplicateCount = $groups->sum(fn (Collection $group) => max(0, $group->count() - 1));

        $this->info('Duplicados detectados: '.$duplicateCount);

        if ($dryRun || $duplicateCount === 0) {
            Log::channel('operations')->info('files.collapse_versions.dry_run', [
                'duplicate_groups' => $groups->count(),
                'duplicate_files' => $duplicateCount,
            ]);

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($groups as $group) {
            $keep = $this->fileToKeep($group);
            $remove = $group->reject(fn (EvidenceFile $file) => $file->id === $keep->id);

            DB::transaction(function () use ($keep, $remove, &$deleted) {
                $keep->forceFill([
                    'previous_version_file_id' => null,
                    'root_file_id' => null,
                    'is_current_version' => true,
                ])->save();

                foreach ($remove as $file) {
                    EvidenceFile::query()
                        ->where('previous_version_file_id', $file->id)
                        ->update(['previous_version_file_id' => null]);
                    EvidenceFile::query()
                        ->where('root_file_id', $file->id)
                        ->update(['root_file_id' => null]);

                    if ($file->stored_relative_path && Storage::disk('local')->exists($file->stored_relative_path)) {
                        Storage::disk('local')->delete($file->stored_relative_path);
                    }

                    $file->forceDelete();
                    $deleted++;
                }
            });
        }

        $this->info('Versiones eliminadas: '.$deleted);

        Log::channel('operations')->info('files.collapse_versions.completed', [
            'duplicate_groups' => $groups->count(),
            'deleted_files' => $deleted,
        ]);

        return self::SUCCESS;
    }

    private function duplicateGroups(): Collection
    {
        return EvidenceFile::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (EvidenceFile $file) => $file->versionRootId())
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    private function fileToKeep(Collection $group): EvidenceFile
    {
        return $group
            ->sort(function (EvidenceFile $left, EvidenceFile $right) {
                $leftRank = [
                    (int) $left->is_current_version,
                    $left->uploaded_at?->getTimestamp() ?? 0,
                    $left->id,
                ];
                $rightRank = [
                    (int) $right->is_current_version,
                    $right->uploaded_at?->getTimestamp() ?? 0,
                    $right->id,
                ];

                return $rightRank <=> $leftRank;
            })
            ->first();
    }
}
