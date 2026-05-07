<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE advisory_sessions ALTER COLUMN teaching_load_id DROP NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE advisory_sessions MODIFY teaching_load_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // General advisory sessions legitimately have no teaching load.
    }
};
