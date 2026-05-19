<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FolderManagerService
{
    public function __construct(
        private AuditService $auditService,
        private StorageService $storageService,
    ) {}

    public function createSubfolder(User $user, FolderNode $parent, string $name): FolderNode
    {
        $normalized = $this->normalizeFolderName($name);

        if ($normalized === '') {
            throw ValidationException::withMessages(['name' => 'El nombre de la carpeta no puede estar vacio.']);
        }

        if (mb_strlen($normalized) > 160) {
            throw ValidationException::withMessages(['name' => 'El nombre de la carpeta no puede exceder 160 caracteres.']);
        }

        if ($parent->storage_root_id === null || $parent->semester_id === null) {
            throw ValidationException::withMessages(['parent' => 'La carpeta padre no tiene semestre o raiz de almacenamiento.']);
        }

        $exists = FolderNode::query()
            ->where('parent_id', $parent->id)
            ->where('name', $normalized)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Ya existe una carpeta con ese nombre en esta ubicacion.']);
        }

        $relativePath = $parent->relative_path.'/'.$normalized;

        $folder = FolderNode::create([
            'parent_id' => $parent->id,
            'storage_root_id' => $parent->storage_root_id,
            'name' => $normalized,
            'relative_path' => $relativePath,
            'owner_user_id' => $parent->owner_user_id,
            'semester_id' => $parent->semester_id,
        ]);

        $this->auditService->log($user, 'CREATE_FOLDER', 'FolderNode', $folder->id, [
            'folder_id' => $folder->id,
            'name' => $normalized,
            'parent_id' => $parent->id,
            'relative_path' => $relativePath,
        ]);

        return $folder;
    }

    public function renameFolder(User $user, FolderNode $folder, string $newName): FolderNode
    {
        return $this->updateFolder($user, $folder, ['name' => $newName]);
    }

    public function updateFolder(User $user, FolderNode $folder, array $data): FolderNode
    {
        $normalized = $this->normalizeFolderName((string) ($data['name'] ?? ''));

        if ($normalized === '') {
            throw ValidationException::withMessages(['name' => 'El nombre de la carpeta no puede estar vacio.']);
        }

        if (mb_strlen($normalized) > 160) {
            throw ValidationException::withMessages(['name' => 'El nombre de la carpeta no puede exceder 160 caracteres.']);
        }

        if ($folder->parent_id === null) {
            throw ValidationException::withMessages(['folder' => 'No se puede renombrar la carpeta raiz de semestre.']);
        }

        $iconKey = $data['icon_key'] ?? null;
        $colorKey = $data['color_key'] ?? null;
        $siblingExists = FolderNode::query()
            ->where('parent_id', $folder->parent_id)
            ->where('id', '!=', $folder->id)
            ->where('name', $normalized)
            ->exists();

        if ($siblingExists) {
            throw ValidationException::withMessages(['name' => 'Ya existe una carpeta con ese nombre en esta ubicacion.']);
        }

        $oldName = $folder->name;
        $oldRelativePath = $folder->relative_path;
        $oldIconKey = $folder->icon_key;
        $oldColorKey = $folder->color_key;

        return DB::transaction(function () use ($folder, $normalized, $user, $oldName, $oldRelativePath, $oldIconKey, $oldColorKey, $iconKey, $colorKey) {
            $parent = $folder->parent;
            $newRelativePath = $parent->relative_path.'/'.$normalized;

            if ($normalized !== $oldName) {
                $this->updateBranchPaths($folder, $oldRelativePath, $newRelativePath);
                $this->moveBranchFiles($folder, $oldRelativePath, $newRelativePath);
            }

            $folder->update([
                'name' => $normalized,
                'relative_path' => $newRelativePath,
                'icon_key' => $iconKey,
                'color_key' => $colorKey,
            ]);

            $this->auditService->log($user, 'RENAME_FOLDER', 'FolderNode', $folder->id, [
                'folder_id' => $folder->id,
                'old_name' => $oldName,
                'new_name' => $normalized,
                'old_relative_path' => $oldRelativePath,
                'new_relative_path' => $newRelativePath,
                'old_icon_key' => $oldIconKey,
                'new_icon_key' => $iconKey,
                'old_color_key' => $oldColorKey,
                'new_color_key' => $colorKey,
            ]);

            return $folder->fresh();
        });
    }

    public function moveFolder(User $user, FolderNode $folder, FolderNode $target): FolderNode
    {
        if ($folder->parent_id === null) {
            throw ValidationException::withMessages(['folder' => 'No se puede mover la carpeta raiz de semestre.']);
        }

        if ($folder->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'No se puede mover una carpeta a si misma.']);
        }

        if ($this->isDescendantOf($folder, $target)) {
            throw ValidationException::withMessages(['target' => 'No se puede mover una carpeta dentro de una de sus subcarpetas.']);
        }

        if ($folder->storage_root_id !== $target->storage_root_id) {
            throw ValidationException::withMessages(['target' => 'La carpeta destino debe compartir la misma raiz de almacenamiento.']);
        }

        if ($folder->semester_id !== $target->semester_id) {
            throw ValidationException::withMessages(['target' => 'La carpeta destino debe pertencer al mismo semestre.']);
        }

        if ((int) $folder->owner_user_id !== (int) $target->owner_user_id) {
            throw ValidationException::withMessages(['target' => 'No se puede mover carpetas entre docentes.']);
        }

        $duplicateExists = FolderNode::query()
            ->where('parent_id', $target->id)
            ->where('name', $folder->name)
            ->where('id', '!=', $folder->id)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages(['target' => 'Ya existe una carpeta con el mismo nombre en la ubicacion destino.']);
        }

        $oldParentId = $folder->parent_id;
        $oldRelativePath = $folder->relative_path;
        $newRelativePath = $target->relative_path.'/'.$folder->name;

        return DB::transaction(function () use ($folder, $target, $user, $oldParentId, $oldRelativePath, $newRelativePath) {
            $this->updateBranchPaths($folder, $oldRelativePath, $newRelativePath);

            $this->moveBranchFiles($folder, $oldRelativePath, $newRelativePath);

            $folder->update([
                'parent_id' => $target->id,
                'relative_path' => $newRelativePath,
            ]);

            $this->auditService->log($user, 'MOVE_FOLDER', 'FolderNode', $folder->id, [
                'folder_id' => $folder->id,
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $target->id,
                'old_relative_path' => $oldRelativePath,
                'new_relative_path' => $newRelativePath,
            ]);

            return $folder->fresh();
        });
    }

    public function deleteFolder(User $user, FolderNode $folder): void
    {
        if ($folder->parent_id === null) {
            throw ValidationException::withMessages(['folder' => 'No se puede eliminar la carpeta raiz de semestre.']);
        }

        $childCount = FolderNode::query()->where('parent_id', $folder->id)->count();
        if ($childCount > 0) {
            throw ValidationException::withMessages(['folder' => 'No se puede eliminar una carpeta que contiene subcarpetas.']);
        }

        $fileCount = EvidenceFile::withTrashed()
            ->where('folder_node_id', $folder->id)
            ->count();
        if ($fileCount > 0) {
            throw ValidationException::withMessages(['folder' => 'No se puede eliminar una carpeta que contiene archivos.']);
        }

        $this->auditService->log($user, 'DELETE_FOLDER', 'FolderNode', $folder->id, [
            'folder_id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'relative_path' => $folder->relative_path,
        ]);

        $folder->delete();
    }

    public function moveFile(User $user, EvidenceFile $file, FolderNode $target): EvidenceFile
    {
        $sourceFolder = $file->folderNode;
        if (! $sourceFolder) {
            throw ValidationException::withMessages(['file' => 'El archivo no tiene carpeta asociada.']);
        }

        if ($sourceFolder->storage_root_id !== $target->storage_root_id) {
            throw ValidationException::withMessages(['target' => 'La carpeta destino debe compartir la misma raiz de almacenamiento.']);
        }

        if ($sourceFolder->semester_id !== $target->semester_id) {
            throw ValidationException::withMessages(['target' => 'La carpeta destino debe pertenecer al mismo semestre.']);
        }

        if ((int) $sourceFolder->owner_user_id !== (int) $target->owner_user_id) {
            throw ValidationException::withMessages(['target' => 'No se puede mover archivos entre docentes.']);
        }

        $sourceFolderId = $sourceFolder->id;
        $oldFolderPath = $sourceFolder->relative_path;
        $newFolderPath = $target->relative_path;
        $oldRelativePath = $file->stored_relative_path;
        $filename = basename($oldRelativePath);
        $newRelativePath = $newFolderPath.'/'.$filename;

        $this->storageService->assertEvidenceFilePath($file);

        return DB::transaction(function () use ($file, $target, $user, $sourceFolderId, $oldRelativePath, $newRelativePath, $newFolderPath) {
            $disk = Storage::disk('local');

            if ($disk->exists($oldRelativePath)) {
                $this->ensureDirectoryExists($disk, $newFolderPath);
                $moved = $disk->move($oldRelativePath, $newRelativePath);

                if (! $moved) {
                    throw new \RuntimeException('Error al mover el archivo fisico.');
                }
            }

            $file->update([
                'folder_node_id' => $target->id,
                'stored_relative_path' => $newRelativePath,
            ]);

            $refreshed = $file->fresh();
            if ($refreshed) {
                $this->storageService->assertEvidenceFilePath($refreshed);
            }

            $this->auditService->log($user, 'MOVE_FILE', 'EvidenceFile', $file->id, [
                'file_id' => $file->id,
                'file_name' => $file->file_name,
                'old_folder_node_id' => $sourceFolderId,
                'new_folder_node_id' => $target->id,
                'old_stored_relative_path' => $oldRelativePath,
                'new_stored_relative_path' => $newRelativePath,
            ]);

            return $file->fresh();
        });
    }

    private function normalizeFolderName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        $name = trim($name);

        if (str_contains($name, '/') || str_contains($name, '\\')) {
            return '';
        }

        if ($name === '.' || $name === '..') {
            return '';
        }

        return $name;
    }

    private function updateBranchPaths(FolderNode $folder, string $oldPrefix, string $newPrefix): void
    {
        $descendants = FolderNode::query()
            ->where('id', '!=', $folder->id)
            ->where(function ($query) use ($folder, $oldPrefix) {
                $query->where('parent_id', $folder->id)
                    ->orWhere('relative_path', 'like', $oldPrefix.'/%');
            })
            ->get();

        foreach ($descendants as $descendant) {
            $descendantOldPath = $descendant->relative_path;

            if (! str_starts_with($descendantOldPath, $oldPrefix.'/')) {
                continue;
            }

            $suffix = mb_substr($descendantOldPath, mb_strlen($oldPrefix));
            $descendantNewPath = $newPrefix.$suffix;

            $descendant->update(['relative_path' => $descendantNewPath]);
        }
    }

    private function moveBranchFiles(FolderNode $folder, string $oldPrefix, string $newPrefix): void
    {
        $disk = Storage::disk('local');

        $this->ensureDirectoryExists($disk, $newPrefix);

        $descendants = FolderNode::query()
            ->where('id', $folder->id)
            ->orWhere('relative_path', 'like', $newPrefix.'/%')
            ->get();

        foreach ($descendants as $descendant) {
            $descendantOldPath = $oldPrefix.mb_substr($descendant->relative_path, mb_strlen($newPrefix));

            $files = EvidenceFile::withTrashed()
                ->where('folder_node_id', $descendant->id)
                ->get();

            foreach ($files as $file) {
                $fileOldPath = $file->stored_relative_path;

                if (! str_starts_with($fileOldPath, $descendantOldPath.'/')) {
                    continue;
                }

                if (! $disk->exists($fileOldPath)) {
                    continue;
                }

                $suffix = mb_substr($fileOldPath, mb_strlen($descendantOldPath));
                $fileNewPath = $descendant->relative_path.$suffix;

                $moved = $disk->move($fileOldPath, $fileNewPath);

                if ($moved) {
                    $file->update(['stored_relative_path' => $fileNewPath]);
                }
            }
        }
    }

    private function isDescendantOf(FolderNode $folder, FolderNode $potentialAncestor): bool
    {
        $current = $potentialAncestor;
        $maxDepth = 50;

        while ($current && $maxDepth > 0) {
            if ($current->parent_id === null) {
                return false;
            }

            if ((int) $current->parent_id === (int) $folder->id) {
                return true;
            }

            $current = FolderNode::find($current->parent_id);
            $maxDepth--;
        }

        return false;
    }

    private function ensureDirectoryExists($disk, string $path): void
    {
        $fullPath = $disk->path($path);

        if (! is_dir($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }
    }
}
