<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('advisory_schedules', 'teacher_user_id')) {
            Schema::table('advisory_schedules', function (Blueprint $table) {
                $table->foreignId('teacher_user_id')->nullable()->after('id')->constrained('users');
            });

            DB::statement(
                'UPDATE advisory_schedules SET teacher_user_id = (SELECT teacher_user_id FROM teaching_loads WHERE teaching_loads.id = advisory_schedules.teaching_load_id) WHERE teacher_user_id IS NULL'
            );
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE advisory_schedules ALTER COLUMN teaching_load_id DROP NOT NULL');
            DB::statement('ALTER TABLE advisory_schedules ALTER COLUMN teacher_user_id SET NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE advisory_schedules MODIFY teaching_load_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE advisory_schedules MODIFY teacher_user_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        // Existing general schedules cannot be safely converted back to load-only rows.
    }
};
