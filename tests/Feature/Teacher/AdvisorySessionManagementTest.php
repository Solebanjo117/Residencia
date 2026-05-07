<?php

use App\Models\AdvisorySchedule;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

function createAdvisoryScheduleContext(): array
{
    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);
    $adminRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);

    $teacher = User::factory()->create(['role_id' => $teacherRole->id]);
    $otherTeacher = User::factory()->create(['role_id' => $teacherRole->id]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    $semester = Semester::create([
        'name' => 'SEM-ADV-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-ADV-'.Str::upper(Str::random(6)),
        'name' => 'Materia ADV '.Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $otherLoad = TeachingLoad::create([
        'teacher_user_id' => $otherTeacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'B',
        'hours_per_week' => 4,
    ]);

    return compact('teacher', 'otherTeacher', 'admin', 'semester', 'load', 'otherLoad');
}

it('allows docente to create edit and delete own weekly advisory schedule', function () {
    $ctx = createAdvisoryScheduleContext();

    $this
        ->from(route('docente.asesorias'))
        ->actingAs($ctx['teacher'])
        ->post(route('docente.asesorias.store'), [
            'teaching_load_id' => $ctx['load']->id,
            'day_of_week' => 1,
            'start_time' => '17:00',
            'end_time' => '18:00',
            'location' => 'Cubiculo 1',
        ])
        ->assertRedirect(route('docente.asesorias'));

    $schedule = AdvisorySchedule::query()
        ->where('teacher_user_id', $ctx['teacher']->id)
        ->firstOrFail();

    expect($schedule->teaching_load_id)->toBe($ctx['load']->id);
    expect($schedule->day_of_week)->toBe(1);

    $this
        ->from(route('docente.asesorias'))
        ->actingAs($ctx['teacher'])
        ->put(route('docente.asesorias.update', $schedule->id), [
            'teaching_load_id' => null,
            'day_of_week' => 3,
            'start_time' => '16:00',
            'end_time' => '17:00',
            'location' => 'Sala docente',
        ])
        ->assertRedirect(route('docente.asesorias'));

    $schedule->refresh();

    expect($schedule->teaching_load_id)->toBeNull();
    expect($schedule->day_of_week)->toBe(3);
    expect(substr((string) $schedule->start_time, 0, 5))->toBe('16:00');

    $this
        ->from(route('docente.asesorias'))
        ->actingAs($ctx['teacher'])
        ->delete(route('docente.asesorias.destroy', $schedule->id))
        ->assertRedirect(route('docente.asesorias'));

    $this->assertDatabaseMissing('advisory_schedules', [
        'id' => $schedule->id,
    ]);
});

it('forbids docente from editing another docentes advisory schedule', function () {
    $ctx = createAdvisoryScheduleContext();

    $schedule = AdvisorySchedule::create([
        'teacher_user_id' => $ctx['otherTeacher']->id,
        'teaching_load_id' => $ctx['otherLoad']->id,
        'semester_id' => $ctx['semester']->id,
        'day_of_week' => 2,
        'start_time' => '12:00',
        'end_time' => '13:00',
        'location' => null,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->put(route('docente.asesorias.update', $schedule->id), [
            'teaching_load_id' => null,
            'day_of_week' => 4,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => null,
        ])
        ->assertForbidden();

    expect($schedule->fresh()->day_of_week)->toBe(2);
});

it('allows administrative authority to manage teacher advisory schedules', function () {
    $ctx = createAdvisoryScheduleContext();

    $this
        ->from(route('asesorias.horarios'))
        ->actingAs($ctx['admin'])
        ->post(route('asesorias.horarios.store'), [
            'teacher_user_id' => $ctx['teacher']->id,
            'teaching_load_id' => null,
            'semester_id' => $ctx['semester']->id,
            'day_of_week' => 5,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'Coordinacion',
        ])
        ->assertRedirect(route('asesorias.horarios'));

    $schedule = AdvisorySchedule::query()
        ->where('teacher_user_id', $ctx['teacher']->id)
        ->whereNull('teaching_load_id')
        ->firstOrFail();

    $this
        ->from(route('asesorias.horarios'))
        ->actingAs($ctx['admin'])
        ->put(route('asesorias.horarios.update', $schedule->id), [
            'teacher_user_id' => $ctx['teacher']->id,
            'teaching_load_id' => $ctx['load']->id,
            'semester_id' => $ctx['semester']->id,
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '12:00',
            'location' => 'Aula 2',
        ])
        ->assertRedirect(route('asesorias.horarios'));

    $schedule->refresh();

    expect($schedule->teaching_load_id)->toBe($ctx['load']->id);
    expect($schedule->day_of_week)->toBe(1);

    $this
        ->from(route('asesorias.horarios'))
        ->actingAs($ctx['admin'])
        ->delete(route('asesorias.horarios.destroy', $schedule->id))
        ->assertRedirect(route('asesorias.horarios'));

    $this->assertDatabaseMissing('advisory_schedules', [
        'id' => $schedule->id,
    ]);
});
