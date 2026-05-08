<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_loads', function (Blueprint $table) {
            $table->string('modality', 30)->default('PRESENCIAL')->after('hours_per_week');
        });

        Schema::table('submission_windows', function (Blueprint $table) {
            $table->string('modality', 30)->nullable()->after('evidence_item_id');
            $table->index(['semester_id', 'evidence_item_id', 'modality'], 'idx_windows_sem_item_modality');
        });
    }

    public function down(): void
    {
        Schema::table('submission_windows', function (Blueprint $table) {
            $table->dropIndex('idx_windows_sem_item_modality');
            $table->dropColumn('modality');
        });

        Schema::table('teaching_loads', function (Blueprint $table) {
            $table->dropColumn('modality');
        });
    }
};
