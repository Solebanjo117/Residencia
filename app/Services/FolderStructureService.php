<?php

namespace App\Services;

use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Support\Str;

class FolderStructureService
{
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
     * Ensures that the Docente folder exists inside the given Semester folder,
     * and automatically scaffolds standard subdirectories for the Teacher (Evidencias, Asesorias).
     */
    public function ensureTeacherFolder(Semester $semester, User $teacher): FolderNode
    {
        $semesterFolder = $this->ensureSemesterFolder($semester);

        $teacherFolder = FolderNode::firstOrCreate(
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

        // Auto-generate expected static sub-structure the first time Docente folder is created/verified
        $this->ensureSubdirectory($teacherFolder, 'Evidencias');
        $this->ensureSubdirectory($teacherFolder, 'Asesorias');

        return $teacherFolder;
    }

    /**
     * Generates the full detailed folder structure for a teacher within a semester.
     *
     * This creates the complete institutional directory tree including:
     *   - Semester root folder (via ensureSemesterFolder)
     *   - Teacher folder (named "{teacher}")
     *   - Standard subfolders: Horario, Instrumentaciones, Evaluacion Diagnostica,
     *     Evidencias de Asignaturas, Proyectos Individuales (with nested children)
     *
     * Every folder is created via firstOrCreate so calling this method multiple
     * times is safe and idempotent.
     */
    public function generateFullStructure(Semester $semester, User $teacher): FolderNode
    {
        // 1. Root Semester Folder (reuses ensureSemesterFolder so same node is shared)
        $semesterFolder = $this->ensureSemesterFolder($semester);
        $root = StorageRoot::find($semesterFolder->storage_root_id);

        // 2. Teacher Folder under semester
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

        // 3. Detailed subfolders structure
        $subfolders = [
            '0.HORARIO OFICIAL ' . $semester->name => [],
            '1.INSTRUMENTACIONES ' . $semester->name => [],
            '2.EVALUACION DIAGNOSTICA ' . $semester->name => [],
            '3.EVIDENCIAS DE ASIGNATURAS ' . $semester->name => [],
            '4.PROYECTOS INDIVIDUALES ' . $semester->name => [
                '4.1-CAPACITACION ' . $semester->name => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%', 'SOLICITUD'],
                '4.3-PROYECTOS DOCENTES-' . $semester->name => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%'],
                '4.4-MATERIAL DIDACTICO-' . $semester->name => ['SD2-AVANCE-50%', 'SD4-AVANCE-100%', 'SOLICITUD'],
            ],
        ];

        $this->createRecursiveFolders($subfolders, $teacherFolder, $root, $teacher, $semester);

        return $teacherFolder;
    }

    // ---------------------------------------------------------------
    //  Private helpers
    // ---------------------------------------------------------------

    private function ensureSubdirectory(FolderNode $parentFolder, string $name): FolderNode
    {
        return FolderNode::firstOrCreate(
            [
                'parent_id' => $parentFolder->id,
                'owner_user_id' => $parentFolder->owner_user_id,
                'semester_id' => $parentFolder->semester_id,
                'name' => $name,
            ],
            [
                'storage_root_id' => $parentFolder->storage_root_id,
                'relative_path' => $parentFolder->relative_path . '/' . Str::slug($name),
            ]
        );
    }

    /**
     * Recursively creates folder nodes from a nested associative array.
     *
     * Array format:
     *   - Associative key => array of children  (branch node)
     *   - Numeric key => string value            (leaf node)
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
}
