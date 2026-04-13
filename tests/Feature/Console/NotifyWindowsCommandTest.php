<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
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
        'name' => 'SEM-NW-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-NW-' . Str::upper(Str::random(6)),
        'name' => 'Materia NW ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-NW-' . Str::upper(Str::random(8)),
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
