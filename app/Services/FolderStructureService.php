<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FolderStructureService
{
    private const DEFAULT_SUBFOLDER_STRUCTURE = [
        '0.HORARIO OFICIAL' => [],
        '1.INSTRUMENTACIONES' => [],
        '2.EVALUACION DIAGNOSTICA' => [],
        '3.EVIDENCIAS DE ASIGNATURAS' => [],
        '4.PROYECTOS INDIVIDUALES' => [
            '4.1-CAPACITACION' => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%', 'SOLICITUD'],
            '4.3-PROYECTOS DOCENTES' => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%'],
            '4.4-MATERIAL DIDACTICO' => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%', 'SOLICITUD'],
        ],
    ];

    /**
     * Ensures that the base folder for a Semester exists.
     */
    public function ensureSemesterFolder(Semester $semester): FolderNode
    {
        $storageRoot = StorageRoot::where('is_active', true)->first();
        if (!$storageRoot) {
            $storageRoot = StorageRoot::create([
                'name' => 'Root Storage',
                'base_path' => 'storage_root',
                'is_active' => true,
            ]);
        }

        $folder = FolderNode::firstOrCreate(
            [
                'semester_id' => $semester->id,
                'parent_id' => null,
            ],
            [
                'storage_root_id' => $storageRoot->id,
                'name' => $semester->name,
                'relative_path' => Str::slug($semester->name),
            ]
        );

        return $folder;
    }

    /**
     * Ensures that the Docente folder exists inside the given Semester folder.
     */
    public function ensureTeacherFolder(Semester $semester, User $teacher): FolderNode
    {
        $semesterFolder = $this->ensureSemesterFolder($semester);

        return FolderNode::firstOrCreate(
            [
                'parent_id' => $semesterFolder->id,
                'owner_user_id' => $teacher->id,
                'semester_id' => $semester->id,
            ],
            [
                'storage_root_id' => $semesterFolder->storage_root_id,
                'name' => $teacher->name,
                'relative_path' => $semesterFolder->relative_path . '/' . Str::slug($teacher->name),
            ]
        );
    }

    /**
     * Generates the full folder structure for a teacher within a semester.
     *
     * Hierarchy: SEMESTRE → DOCENTE → MATERIA → evidence subfolders
     *
     * Each subject (materia) the teacher is assigned gets its own folder
     * with evidence category subfolders inside.
     */
    public function generateFullStructure(Semester $semester, User $teacher, ?array $allowedPermissionKeys = null): FolderNode
    {
        $semesterFolder = $this->ensureSemesterFolder($semester);
        $root = StorageRoot::find($semesterFolder->storage_root_id);

        $effectivePermissionKeys = $allowedPermissionKeys ?? $this->resolveTeacherFolderPermissionKeys($teacher);
        $normalizedPermissionKeys = $this->normalizeFolderPermissionKeys($effectivePermissionKeys);
        $allowedSet = array_fill_keys($normalizedPermissionKeys, true);
        $subfolderStructure = $this->filterStructureByPermissionKeys(self::DEFAULT_SUBFOLDER_STRUCTURE, $allowedSet);

        $teacherFolder = FolderNode::firstOrCreate(
            [
                'parent_id' => $semesterFolder->id,
                'owner_user_id' => $teacher->id,
                'semester_id' => $semester->id,
            ],
            [
                'storage_root_id' => $root->id,
                'name' => $teacher->name,
                'relative_path' => $semesterFolder->relative_path . '/' . Str::slug($teacher->name),
            ]
        );

        // Get all subjects assigned to this teacher in this semester
        $loads = TeachingLoad::with('subject')
            ->where('teacher_user_id', $teacher->id)
            ->where('semester_id', $semester->id)
            ->get();

        foreach ($loads as $load) {
            $subjectName = $load->subject->name;

            // Create MATERIA folder under teacher
            $materiaFolder = FolderNode::firstOrCreate([
                'storage_root_id' => $root->id,
                'parent_id' => $teacherFolder->id,
                'name' => $subjectName,
            ], [
                'relative_path' => $teacherFolder->relative_path . '/' . Str::slug($subjectName),
                'owner_user_id' => $teacher->id,
                'semester_id' => $semester->id,
            ]);

            if (!empty($subfolderStructure)) {
                $this->createRecursiveFolders($subfolderStructure, $materiaFolder, $root, $teacher, $semester);
            }
        }

        return $teacherFolder;
    }

    /**
     * Rebuilds a teacher structure in a semester like the seeder strategy:
     * delete teacher-owned nodes + attached files, then recreate.
     */
    public function regenerateTeacherStructure(Semester $semester, User $teacher, ?array $allowedPermissionKeys = null): FolderNode
    {
        return DB::transaction(function () use ($semester, $teacher, $allowedPermissionKeys) {
            $teacherNodeIds = FolderNode::query()
                ->where('semester_id', $semester->id)
                ->where('owner_user_id', $teacher->id)
                ->pluck('id')
                ->all();

            if (!empty($teacherNodeIds)) {
                EvidenceFile::withTrashed()
                    ->whereIn('folder_node_id', $teacherNodeIds)
                    ->forceDelete();

                FolderNode::query()
                    ->whereIn('id', $teacherNodeIds)
                    ->orderByRaw('LENGTH(relative_path) DESC')
                    ->delete();
            }

            return $this->generateFullStructure($semester, $teacher, $allowedPermissionKeys);
        });
    }

    /**
     * Returns a flat catalog used by admin checkboxes.
     * Each key is a stable path-like identifier inside MATERIA.
     */
    public function folderPermissionCatalog(): array
    {
        $catalog = [];
        $this->flattenPermissionCatalog(self::DEFAULT_SUBFOLDER_STRUCTURE, '', 0, null, $catalog);

        return $catalog;
    }

    public function allFolderPermissionKeys(): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['key'],
            $this->folderPermissionCatalog()
        ));
    }

    /**
     * Returns effective keys for a teacher. Null means "all enabled" for backward compatibility.
     */
    public function resolveTeacherFolderPermissionKeys(User $teacher): array
    {
        $allKeys = $this->allFolderPermissionKeys();
        $configured = $teacher->folder_permission_keys;

        if (!is_array($configured)) {
            return $allKeys;
        }

        return $this->normalizeFolderPermissionKeys($configured);
    }

    /**
     * Ensures keys are valid, unique and include required ancestors.
     */
    public function normalizeFolderPermissionKeys(array $requestedKeys): array
    {
        $allKeys = $this->allFolderPermissionKeys();
        $allowedSet = array_fill_keys($allKeys, true);

        $selected = array_values(array_unique(array_filter($requestedKeys, fn ($value) => is_string($value) && isset($allowedSet[$value]))));

        if (empty($selected)) {
            return [];
        }

        $selectedSet = array_fill_keys($selected, true);

        foreach ($selected as $key) {
            $parts = explode('/', $key);

            while (count($parts) > 1) {
                array_pop($parts);
                $parentKey = implode('/', $parts);

                if (isset($allowedSet[$parentKey])) {
                    $selectedSet[$parentKey] = true;
                }
            }
        }

        $ordered = [];
        foreach ($allKeys as $key) {
            if (isset($selectedSet[$key])) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    // ---------------------------------------------------------------
    //  Private helpers
    // ---------------------------------------------------------------

    /**
     * Recursively creates folder nodes from a nested associative array.
     */
    private function createRecursiveFolders(array $structure, FolderNode $parent, StorageRoot $root, User $owner, Semester $semester): void
    {
        foreach ($structure as $key => $value) {
            $folderName = is_numeric($key) ? $value : $key;
            $children = is_array($value) ? $value : [];

            $node = FolderNode::firstOrCreate([
                'storage_root_id' => $root->id,
                'name' => $folderName,
                'parent_id' => $parent->id,
            ], [
                'relative_path' => $parent->relative_path . '/' . $folderName,
                'owner_user_id' => $owner->id,
                'semester_id' => $semester->id,
            ]);

            if (!empty($children)) {
                $this->createRecursiveFolders($children, $node, $root, $owner, $semester);
            }
        }
    }

    private function flattenPermissionCatalog(
        array $structure,
        string $prefix,
        int $depth,
        ?string $parentKey,
        array &$catalog
    ): void {
        foreach ($structure as $key => $value) {
            $folderName = is_numeric($key) ? (string) $value : (string) $key;
            $children = is_array($value) ? $value : [];
            $nodeKey = $prefix === '' ? $folderName : $prefix . '/' . $folderName;

            $catalog[] = [
                'key' => $nodeKey,
                'label' => $folderName,
                'depth' => $depth,
                'parent_key' => $parentKey,
            ];

            if (!empty($children)) {
                $this->flattenPermissionCatalog($children, $nodeKey, $depth + 1, $nodeKey, $catalog);
            }
        }
    }

    private function filterStructureByPermissionKeys(array $structure, array $allowedSet, string $prefix = ''): array
    {
        $filtered = [];

        foreach ($structure as $key => $value) {
            $folderName = is_numeric($key) ? (string) $value : (string) $key;
            $nodeKey = $prefix === '' ? $folderName : $prefix . '/' . $folderName;
            $children = is_array($value) ? $value : [];
            $filteredChildren = !empty($children)
                ? $this->filterStructureByPermissionKeys($children, $allowedSet, $nodeKey)
                : [];

            $isAllowed = isset($allowedSet[$nodeKey]);

            if (!$isAllowed && empty($filteredChildren)) {
                continue;
            }

            if (is_numeric($key)) {
                $filtered[] = $folderName;
                continue;
            }

            $filtered[$folderName] = $filteredChildren;
        }

        return $filtered;
    }
}
