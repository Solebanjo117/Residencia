<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeguimientoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Department
        $dept = Department::firstOrCreate(['name' => 'Sistemas y Computación']);

        // Assign existing users to department
        $users = User::all();
        foreach ($users as $user) {
            if (!$user->departments()->where('department_id', $dept->id)->exists()) {
                $user->departments()->attach($dept->id);
            }
        }

        // 2. Semester
        $semester = Semester::firstOrCreate(
            ['name' => 'ENE-JUN 2026'],
            [
                'start_date' => '2026-01-12',
                'end_date' => '2026-06-30',
                'status' => 'OPEN',
            ]
        );

        // 3. Subjects (matching the reference spreadsheet)
        $subjects = [
            ['code' => 'AED-1015', 'name' => 'DISEÑO ORGANIZACIONAL'],
            ['code' => 'GED-0922', 'name' => 'SISTEMAS DE INFORMACIÓN DE MERCADOTECNIA'],
            ['code' => 'AEF-1031', 'name' => 'FUNDAMENTOS DE BASE DE DATOS'],
            ['code' => 'LOH-0902', 'name' => 'BASE DE DATOS'],
            ['code' => 'AEC-1053', 'name' => 'PROBABILIDAD Y ESTADÍSTICA'],
            ['code' => 'SCD-1016', 'name' => 'LENGUAJES Y AUTÓMATAS II'],
            ['code' => 'AEB-1055', 'name' => 'PROGRAMACIÓN WEB'],
            ['code' => 'SCD-1021', 'name' => 'REDES DE COMPUTADORA'],
            ['code' => 'AEB-1082', 'name' => 'SOFTWARE DE APLICACIÓN EJECUTIVO'],
            ['code' => 'ACA-0909', 'name' => 'TALLER DE INVESTIGACIÓN I'],
            ['code' => 'ACA-0910', 'name' => 'TALLER DE INVESTIGACIÓN II'],
        ];

        foreach ($subjects as $s) {
            Subject::firstOrCreate(['code' => $s['code']], ['name' => $s['name']]);
        }

        // 4. Evidence Items (the 16 columns from the reference)
        $category = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->first();

        $evidenceColumns = [
            'INSTRUM',
            'EV.DIAGN',
            'SEG 01',
            'CALIF. PARCIALES',
            'SEG 02',
            'CALIF. PARCIALES 2',
            'SEG 03',
            'CALIF. PARCIALES 3',
            'SEG 04 FINAL',
            'CALIF. PARCIALES FINAL',
            'REPORTES EVIDENCIAS ASIGNATURAS',
            'HORARIO',
            'PROY IND',
            'REP FINAL',
            'ASESORIAS',
            'ACTAS FINALES',
        ];

        $itemIds = [];
        foreach ($evidenceColumns as $colName) {
            $item = EvidenceItem::firstOrCreate(
                ['name' => $colName],
                [
                    'category_id' => $category->id,
                    'description' => 'Evidencia: ' . $colName,
                    'requires_subject' => true,
                    'active' => true,
                ]
            );
            $itemIds[] = $item->id;
        }

        // 5. Evidence Requirements (link items to semester + department)
        foreach ($itemIds as $itemId) {
            EvidenceRequirement::firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'department_id' => $dept->id,
                    'evidence_item_id' => $itemId,
                ],
                [
                    'is_mandatory' => true,
                    'applies_condition' => null,
                ]
            );
        }

        // 6. Teaching Loads (docentes with subjects)
        $docentes = User::where('role_id', 1)->get(); // DOCENTE role

        if ($docentes->count() > 0) {
            $allSubjects = Subject::all();
            $subjectIndex = 0;

            foreach ($docentes as $docente) {
                // Assign 2-3 subjects per docente
                $count = min(3, $allSubjects->count() - $subjectIndex);
                for ($i = 0; $i < $count; $i++) {
                    $subject = $allSubjects[$subjectIndex % $allSubjects->count()];
                    TeachingLoad::firstOrCreate(
                        [
                            'teacher_user_id' => $docente->id,
                            'semester_id' => $semester->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'group_code' => 'IGEM-2009-' . ($subjectIndex + 201),
                            'hours_per_week' => rand(4, 8),
                        ]
                    );
                    $subjectIndex++;
                }
            }
        }

        // 7. Storage Root
        StorageRoot::firstOrCreate(
            ['name' => 'local_evidence'],
            [
                'base_path' => 'evidence',
                'is_active' => true,
            ]
        );
    }
}
