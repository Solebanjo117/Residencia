<?php

namespace App\Services;

use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\TeachingLoad;
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
    public function generateFullStructure(Semester $semester, User $teacher): FolderNode
    {
        $semesterFolder = $this->ensureSemesterFolder($semester);
        $root = StorageRoot::find($semesterFolder->storage_root_id);

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

            // Evidence subfolders inside each materia
            $subfolders = [
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

            $this->createRecursiveFolders($subfolders, $materiaFolder, $root, $teacher, $semester);
        }

        return $teacherFolder;
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
}
