<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class SeguimientoSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(['name' => 'Sistemas y Computacion']);

        $this->attachUsersToDepartment($department);

        $academicPeriod = AcademicPeriod::updateOrCreate(
            ['code' => $this->currentPeriodCode()],
            [
                'name' => $this->currentPeriodName(),
                'start_date' => $this->currentPeriodStartDate(),
                'end_date' => $this->currentPeriodEndDate(),
                'status' => 'ACTIVE',
            ]
        );

        Semester::query()
            ->where('status', 'OPEN')
            ->where('name', '!=', $this->currentPeriodName())
            ->update(['status' => 'CLOSED']);

        $semester = Semester::updateOrCreate(
            ['name' => $this->currentPeriodName()],
            [
                'start_date' => $this->currentPeriodStartDate(),
                'end_date' => $this->currentPeriodEndDate(),
                'status' => 'OPEN',
                'academic_period_id' => $academicPeriod->id,
            ]
        );

        $subjects = $this->seedSubjects();
        $items = $this->seedEvidenceItems();
        $this->seedRequirements($semester, $department, $items);
        $this->seedTeachingLoads($semester, $subjects);
        $this->seedSubmissionWindows($semester, $items);
        $this->seedStorageRoot();
    }

    private function attachUsersToDepartment(Department $department): void
    {
        User::query()->each(function (User $user) use ($department) {
            $user->departments()->syncWithoutDetaching([$department->id]);
        });
    }

    private function seedSubjects(): array
    {
        $subjects = [
            ['code' => 'AED-1015', 'name' => 'DISENO ORGANIZACIONAL'],
            ['code' => 'GED-0922', 'name' => 'SISTEMAS DE INFORMACION DE MERCADOTECNIA'],
            ['code' => 'AEF-1031', 'name' => 'FUNDAMENTOS DE BASE DE DATOS'],
            ['code' => 'LOH-0902', 'name' => 'BASE DE DATOS'],
            ['code' => 'AEC-1053', 'name' => 'PROBABILIDAD Y ESTADISTICA'],
            ['code' => 'SCD-1016', 'name' => 'LENGUAJES Y AUTOMATAS II'],
            ['code' => 'AEB-1055', 'name' => 'PROGRAMACION WEB'],
            ['code' => 'SCD-1021', 'name' => 'REDES DE COMPUTADORA'],
            ['code' => 'AEB-1082', 'name' => 'SOFTWARE DE APLICACION EJECUTIVO'],
            ['code' => 'ACA-0909', 'name' => 'TALLER DE INVESTIGACION I'],
            ['code' => 'ACA-0910', 'name' => 'TALLER DE INVESTIGACION II'],
            ['code' => 'ONL-1001', 'name' => 'ADMINISTRACION DIGITAL EN LINEA'],
            ['code' => 'ONL-1002', 'name' => 'GESTION DE PROYECTOS EN LINEA'],
            ['code' => 'ONL-1003', 'name' => 'DESARROLLO WEB EN LINEA'],
        ];

        return collect($subjects)
            ->map(fn (array $subject) => Subject::updateOrCreate(
                ['code' => $subject['code']],
                ['name' => $subject['name']]
            ))
            ->all();
    }

    private function seedEvidenceItems(): array
    {
        $category = EvidenceCategory::firstOrCreate(
            ['name' => 'I_CARGA_ACADEMICA'],
            ['description' => 'Evidencias relacionadas a la carga academica']
        );

        $evidenceColumns = [
            'HORARIO',
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
            'REP FINAL',
            'ASESORIAS',
            'ACTAS FINALES',
        ];

        return collect($evidenceColumns)
            ->map(fn (string $name) => EvidenceItem::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'name' => $name,
                ],
                [
                    'description' => 'Evidencia: '.$name,
                    'requires_subject' => true,
                    'active' => true,
                ]
            ))
            ->all();
    }

    private function seedRequirements(Semester $semester, Department $department, array $items): void
    {
        foreach ($items as $item) {
            EvidenceRequirement::firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'department_id' => $department->id,
                    'evidence_item_id' => $item->id,
                ],
                [
                    'is_mandatory' => true,
                    'applies_condition' => null,
                ]
            );
        }
    }

    private function seedTeachingLoads(Semester $semester, array $subjects): void
    {
        $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
        if (! $docenteRoleId || empty($subjects)) {
            return;
        }

        $docentes = User::where('role_id', $docenteRoleId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($docentes->isEmpty()) {
            return;
        }

        $subjectIndex = 0;

        foreach ($docentes as $docente) {
            for ($i = 0; $i < min(3, count($subjects)); $i++) {
                $subject = $subjects[$subjectIndex % count($subjects)];

                TeachingLoad::firstOrCreate(
                    [
                        'teacher_user_id' => $docente->id,
                        'semester_id' => $semester->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'group_code' => 'IGEM-2009-'.(201 + $subjectIndex),
                        'hours_per_week' => 4 + ($subjectIndex % 5),
                        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
                    ]
                );

                $subjectIndex++;
            }
        }

        $onlineSubjects = collect($subjects)
            ->filter(fn (Subject $subject) => str_starts_with($subject->code, 'ONL-'))
            ->values();

        if ($onlineSubjects->isEmpty()) {
            return;
        }

        foreach ($onlineSubjects as $index => $subject) {
            $docente = $docentes[$index % $docentes->count()];

            TeachingLoad::firstOrCreate(
                [
                    'teacher_user_id' => $docente->id,
                    'semester_id' => $semester->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'group_code' => 'ONLINE-'.($index + 1),
                    'hours_per_week' => 4,
                    'modality' => TeachingLoad::MODALITY_EN_LINEA,
                ]
            );
        }
    }

    private function seedSubmissionWindows(Semester $semester, array $items): void
    {
        $creator = User::whereHas('role', fn ($query) => $query->where('name', Role::JEFE_OFICINA))
            ->orWhereHas('role', fn ($query) => $query->where('name', Role::JEFE_DEPTO))
            ->first();

        if (! $creator) {
            return;
        }

        foreach ($items as $item) {
            [$opensAt, $closesAt] = $this->submissionWindowBounds($semester, $item->name);

            SubmissionWindow::updateOrCreate(
                [
                    'semester_id' => $semester->id,
                    'evidence_item_id' => $item->id,
                ],
                [
                    'opens_at' => $opensAt,
                    'closes_at' => $closesAt,
                    'created_by_user_id' => $creator->id,
                    'status' => 'ACTIVE',
                ]
            );

            SubmissionWindow::updateOrCreate(
                [
                    'semester_id' => $semester->id,
                    'evidence_item_id' => $item->id,
                    'modality' => TeachingLoad::MODALITY_EN_LINEA,
                ],
                [
                    'opens_at' => $opensAt,
                    'closes_at' => $closesAt,
                    'created_by_user_id' => $creator->id,
                    'status' => 'ACTIVE',
                ]
            );
        }
    }

    private function submissionWindowBounds(Semester $semester, string $itemName): array
    {
        $stage = $this->stageForWindow($itemName);
        $start = CarbonImmutable::parse($semester->start_date)->startOfDay();
        $end = CarbonImmutable::parse($semester->end_date)->endOfDay();
        $sd1Close = $this->semesterCut($start, $end, 0.25);
        $sd2Close = $this->semesterCut($start, $end, 0.50);
        $sd3Close = $this->semesterCut($start, $end, 0.75);

        return match ($stage) {
            0 => [$start, $sd2Close],
            10 => [$start, $sd1Close],
            20 => [$sd1Close->addSecond(), $sd2Close],
            30 => [$sd2Close->addSecond(), $sd3Close],
            40 => [$sd3Close->addSecond(), $end],
            default => [$sd2Close->addSecond(), $end],
        };
    }

    private function semesterCut(CarbonImmutable $start, CarbonImmutable $end, float $ratio): CarbonImmutable
    {
        $seconds = max(0, $start->diffInSeconds($end));

        return $start->addSeconds((int) floor($seconds * $ratio))->endOfDay();
    }

    private function stageForWindow(string $itemName): int
    {
        $name = str($itemName)->ascii()->upper()->toString();

        if (str_contains($name, 'INSTRUM') || str_contains($name, 'DIAGN')) {
            return 0;
        }

        if (str_contains($name, 'HORARIO')) {
            return 10;
        }

        if (str_contains($name, 'SEG 04') || str_contains($name, 'SD4')) {
            return 40;
        }

        if (str_contains($name, 'SEG 03') || str_contains($name, 'SD3') || str_contains($name, 'PARCIALES 3')) {
            return 30;
        }

        if (
            str_contains($name, 'SEG 02')
            || str_contains($name, 'SD2')
            || str_contains($name, 'PARCIALES 2')
        ) {
            return 20;
        }

        return 10;
    }

    private function seedStorageRoot(): void
    {
        StorageRoot::updateOrCreate(
            ['name' => 'local_evidence'],
            [
                'base_path' => 'evidence',
                'is_active' => true,
            ]
        );
    }

    private function currentPeriodName(): string
    {
        $year = now()->year;

        return now()->month <= 6 ? "ENE-JUN {$year}" : "AGO-DIC {$year}";
    }

    private function currentPeriodCode(): string
    {
        $year = now()->year;

        return now()->month <= 6 ? "EJ{$year}" : "AD{$year}";
    }

    private function currentPeriodStartDate(): string
    {
        $year = now()->year;

        return now()->month <= 6 ? "{$year}-01-01" : "{$year}-08-01";
    }

    private function currentPeriodEndDate(): string
    {
        $year = now()->year;

        return now()->month <= 6 ? "{$year}-06-30" : "{$year}-12-31";
    }
}
