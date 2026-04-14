<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $activeSemesterId = DB::table('semesters')
            ->where('status', 'OPEN')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->value('id');

        if (!$activeSemesterId) {
            return;
        }

        DB::table('semesters')
            ->where('status', 'OPEN')
            ->where('id', '!=', $activeSemesterId)
            ->update(['status' => 'CLOSED']);
    }

    public function down(): void
    {
        // No-op: previous multiple active semesters cannot be restored safely.
    }
};
