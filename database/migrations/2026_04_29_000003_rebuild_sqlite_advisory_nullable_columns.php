<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            $this->rebuildAdvisorySessions();
            $this->rebuildAdvisorySchedules();
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // General advisories and schedules require nullable teaching_load_id.
    }

    private function rebuildAdvisorySessions(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advisory_sessions_rebuild (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                teaching_load_id INTEGER NULL,
                semester_id INTEGER NOT NULL,
                session_date DATE NOT NULL,
                topic VARCHAR NOT NULL,
                duration_minutes INTEGER NULL,
                notes VARCHAR NULL,
                created_by_user_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teaching_load_id) REFERENCES teaching_loads(id),
                FOREIGN KEY (semester_id) REFERENCES semesters(id),
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO advisory_sessions_rebuild (
                id,
                teaching_load_id,
                semester_id,
                session_date,
                topic,
                duration_minutes,
                notes,
                created_by_user_id,
                created_at
            )
            SELECT
                id,
                teaching_load_id,
                semester_id,
                session_date,
                topic,
                duration_minutes,
                notes,
                created_by_user_id,
                created_at
            FROM advisory_sessions
        SQL);

        DB::statement('DROP TABLE advisory_sessions');
        DB::statement('ALTER TABLE advisory_sessions_rebuild RENAME TO advisory_sessions');
    }

    private function rebuildAdvisorySchedules(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advisory_schedules_rebuild (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                teacher_user_id INTEGER NOT NULL,
                teaching_load_id INTEGER NULL,
                semester_id INTEGER NOT NULL,
                day_of_week INTEGER NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                location VARCHAR NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_user_id) REFERENCES users(id),
                FOREIGN KEY (teaching_load_id) REFERENCES teaching_loads(id),
                FOREIGN KEY (semester_id) REFERENCES semesters(id)
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO advisory_schedules_rebuild (
                id,
                teacher_user_id,
                teaching_load_id,
                semester_id,
                day_of_week,
                start_time,
                end_time,
                location,
                created_at
            )
            SELECT
                id,
                COALESCE(
                    teacher_user_id,
                    (
                        SELECT teaching_loads.teacher_user_id
                        FROM teaching_loads
                        WHERE teaching_loads.id = advisory_schedules.teaching_load_id
                    )
                ) AS teacher_user_id,
                teaching_load_id,
                semester_id,
                day_of_week,
                start_time,
                end_time,
                location,
                created_at
            FROM advisory_schedules
        SQL);

        DB::statement('DROP TABLE advisory_schedules');
        DB::statement('ALTER TABLE advisory_schedules_rebuild RENAME TO advisory_schedules');
    }
};
