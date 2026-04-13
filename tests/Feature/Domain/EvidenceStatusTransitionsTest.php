<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Support\Str;

function createSubmissionForTransitionTest(SubmissionStatus $status): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-TR-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-TR-' . Str::upper(Str::random(6)),
        'name' => 'Materia TR ' . Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-TR-' . Str::upper(Str::random(8)),
        'description' => 'Item transition test',
        'requires_subject' => true,
        'active' => true,
    ]);

    $submission = EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => $status,
        'submitted_at' => $status === SubmissionStatus::SUBMITTED ? now() : null,
        'last_updated_at' => now(),
    ]);

    return compact('submission', 'teacher');
}

it('allows transition from draft to submitted', function () {
    $ctx = createSubmissionForTransitionTest(SubmissionStatus::DRAFT);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);
    $service->changeStatus($ctx['submission'], SubmissionStatus::SUBMITTED, $ctx['teacher'], 'Transicion valida');

    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::SUBMITTED);
    $this->assertDatabaseHas('evidence_status_history', [
        'submission_id' => $ctx['submission']->id,
        'old_status' => 'DRAFT',
        'new_status' => 'SUBMITTED',
    ]);
});

it('rejects transition from draft to approved', function () {
    $ctx = createSubmissionForTransitionTest(SubmissionStatus::DRAFT);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);

    expect(fn () => $service->changeStatus(
        $ctx['submission'],
        SubmissionStatus::APPROVED,
        $ctx['teacher'],
        'Transicion invalida'
    ))->toThrow(InvalidArgumentException::class);

    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
});

it('rejects transition from approved to submitted', function () {
    $ctx = createSubmissionForTransitionTest(SubmissionStatus::APPROVED);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);

    expect(fn () => $service->changeStatus(
        $ctx['submission'],
        SubmissionStatus::SUBMITTED,
        $ctx['teacher'],
        'Transicion invalida'
    ))->toThrow(InvalidArgumentException::class);

    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::APPROVED);
});

it('rejects transition from rejected to approved', function () {
    $ctx = createSubmissionForTransitionTest(SubmissionStatus::REJECTED);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);

    expect(fn () => $service->changeStatus(
        $ctx['submission'],
        SubmissionStatus::APPROVED,
        $ctx['teacher'],
        'Transicion invalida'
    ))->toThrow(InvalidArgumentException::class);

    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::REJECTED);
});
