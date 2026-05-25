<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

it('dispatches pending window notifications to teachers using teacher_user_id', function () {
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-NW-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-NW-'.Str::upper(Str::random(6)),
        'name' => 'Materia NW '.Str::upper(Str::random(4)),
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-NW-'.Str::upper(Str::random(8)),
        'description' => 'Item notify windows test',
        'requires_subject' => true,
        'active' => true,
    ]);

    $window = SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    $schedule = NotificationSchedule::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    expect($schedule->fresh()->is_sent)->toBeTrue();
    $this->assertDatabaseHas('notifications', [
        'user_id' => $teacher->id,
        'type' => 'WINDOW_OPEN',
        'related_entity_type' => 'App\\Models\\SubmissionWindow',
        'related_entity_id' => $window->id,
        'is_read' => false,
    ]);
});

it('dispatches task due soon notifications four days before the window closes', function () {
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-DUE-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-DUE-'.Str::upper(Str::random(6)),
        'name' => 'Materia Due '.Str::upper(Str::random(4)),
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-DUE-'.Str::upper(Str::random(8)),
        'description' => 'Item due soon test',
        'requires_subject' => true,
        'active' => true,
    ]);

    $window = SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    $schedule = NotificationSchedule::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'TASK_DUE_SOON',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    expect($schedule->fresh()->is_sent)->toBeTrue();
    $this->assertDatabaseHas('notifications', [
        'user_id' => $teacher->id,
        'type' => 'TASK_DUE_SOON',
        'title' => 'Tarea por vencer',
        'related_entity_type' => 'App\\Models\\SubmissionWindow',
        'related_entity_id' => $window->id,
        'is_read' => false,
    ]);
});

it('does not dispatch task due soon notifications when the teacher already submitted', function () {
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-DUE-SUB-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-DUE-SUB-'.Str::upper(Str::random(6)),
        'name' => 'Materia Due Submitted '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-DUE-SUB-'.Str::upper(Str::random(8)),
        'description' => 'Item due soon submitted test',
        'requires_subject' => true,
        'active' => true,
    ]);

    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    $schedule = NotificationSchedule::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'TASK_DUE_SOON',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    expect($schedule->fresh()->is_sent)->toBeTrue();
    $this->assertDatabaseMissing('notifications', [
        'user_id' => $teacher->id,
        'type' => 'TASK_DUE_SOON',
    ]);
});

it('dispatches modality-specific window notifications only to matching teaching loads', function () {
    $ctx = createNotifyWindowsContext('MODALITY');
    $onlineTeacher = createNotifyWindowsTeacherWithLoad($ctx['semester'], TeachingLoad::MODALITY_EN_LINEA, 'ONLINE');
    $presentialTeacher = createNotifyWindowsTeacherWithLoad($ctx['semester'], TeachingLoad::MODALITY_PRESENCIAL, 'PRES');

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'modality' => TeachingLoad::MODALITY_EN_LINEA,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(6),
        'created_by_user_id' => $onlineTeacher['teacher']->id,
        'status' => 'ACTIVE',
    ]);

    $schedule = NotificationSchedule::create([
        'submission_window_id' => $window->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    expect($schedule->fresh()->is_sent)->toBeTrue();
    $this->assertDatabaseHas('notifications', [
        'user_id' => $onlineTeacher['teacher']->id,
        'type' => 'WINDOW_OPEN',
        'related_entity_id' => $window->id,
    ]);
    $this->assertDatabaseMissing('notifications', [
        'user_id' => $presentialTeacher['teacher']->id,
        'type' => 'WINDOW_OPEN',
    ]);
});

it('dispatches due soon notifications per pending teaching load even when the teacher submitted another load', function () {
    $ctx = createNotifyWindowsContext('LOAD-PENDING');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $firstSubject = Subject::create([
        'code' => 'SUBJ-LP-1-'.Str::upper(Str::random(5)),
        'name' => 'Materia carga entregada',
    ]);
    $secondSubject = Subject::create([
        'code' => 'SUBJ-LP-2-'.Str::upper(Str::random(5)),
        'name' => 'Materia carga pendiente',
    ]);

    $submittedLoad = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $firstSubject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
    ]);
    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $secondSubject->id,
        'group_code' => 'B',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
    ]);

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $submittedLoad->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    NotificationSchedule::create([
        'submission_window_id' => $window->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'TASK_DUE_SOON',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $teacher->id,
        'type' => 'TASK_DUE_SOON',
        'related_entity_id' => $window->id,
    ]);
});

it('stores task action context for a pending teaching load notification', function () {
    $ctx = createNotifyWindowsContext('CTX');
    $teacherLoad = createNotifyWindowsTeacherWithLoad($ctx['semester'], TeachingLoad::MODALITY_PRESENCIAL, 'CTX');

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $teacherLoad['teacher']->id,
        'status' => 'ACTIVE',
    ]);

    NotificationSchedule::create([
        'submission_window_id' => $window->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'TASK_DUE_SOON',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    $notification = Notification::query()
        ->where('user_id', $teacherLoad['teacher']->id)
        ->where('type', 'TASK_DUE_SOON')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->action_context)->toMatchArray([
            'semester_id' => $ctx['semester']->id,
            'teaching_load_id' => $teacherLoad['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'submission_window_id' => $window->id,
        ]);
});

it('creates separate due soon notifications for each pending teaching load of the same teacher', function () {
    $ctx = createNotifyWindowsContext('CTX-MULTI');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $firstSubject = Subject::create([
        'code' => 'SUBJ-CM-1-'.Str::upper(Str::random(5)),
        'name' => 'Materia pendiente uno',
    ]);
    $secondSubject = Subject::create([
        'code' => 'SUBJ-CM-2-'.Str::upper(Str::random(5)),
        'name' => 'Materia pendiente dos',
    ]);

    $firstLoad = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $firstSubject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
    ]);
    $secondLoad = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $secondSubject->id,
        'group_code' => 'B',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
    ]);

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    NotificationSchedule::create([
        'submission_window_id' => $window->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'TASK_DUE_SOON',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    $contexts = Notification::query()
        ->where('user_id', $teacher->id)
        ->where('type', 'TASK_DUE_SOON')
        ->get()
        ->pluck('action_context')
        ->all();

    expect($contexts)->toHaveCount(2)
        ->and(collect($contexts)->pluck('teaching_load_id')->sort()->values()->all())
        ->toBe([$firstLoad->id, $secondLoad->id]);
});

it('falls back to semester and evidence lookup for legacy schedules without a submission window id', function () {
    $ctx = createNotifyWindowsContext('LEGACY');
    $teacherLoad = createNotifyWindowsTeacherWithLoad($ctx['semester'], TeachingLoad::MODALITY_PRESENCIAL, 'LEG');

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'modality' => null,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $teacherLoad['teacher']->id,
        'status' => 'ACTIVE',
    ]);

    NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->subMinute(),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    $this->artisan('notify:windows')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $teacherLoad['teacher']->id,
        'type' => 'WINDOW_OPEN',
        'related_entity_id' => $window->id,
    ]);
});

function createNotifyWindowsContext(string $suffix): array
{
    $semester = Semester::create([
        'name' => 'SEM-NW-'.$suffix.'-'.Str::upper(Str::random(5)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id')
        ?? EvidenceCategory::create([
            'name' => 'CAT-NW-'.$suffix.'-'.Str::upper(Str::random(5)),
            'description' => 'Categoria notify windows',
        ])->id;

    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-NW-'.$suffix.'-'.Str::upper(Str::random(5)),
        'description' => 'Item notify windows '.$suffix,
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('semester', 'item');
}

function createNotifyWindowsTeacherWithLoad(Semester $semester, string $modality, string $suffix): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $subject = Subject::create([
        'code' => 'SUBJ-NW-'.$suffix.'-'.Str::upper(Str::random(5)),
        'name' => 'Materia '.$suffix,
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
        'modality' => $modality,
    ]);

    return compact('teacher', 'load');
}
