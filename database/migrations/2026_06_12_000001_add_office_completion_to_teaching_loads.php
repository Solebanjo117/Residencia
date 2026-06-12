<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_loads', function (Blueprint $table) {
            $table->string('office_completion_status', 20)->nullable()->after('modality');
            $table->foreignId('office_completion_reviewed_by_user_id')
                ->nullable()
                ->after('office_completion_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('office_completion_reviewed_at')->nullable()->after('office_completion_reviewed_by_user_id');
            $table->text('office_completion_comments')->nullable()->after('office_completion_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_loads', function (Blueprint $table) {
            $table->dropForeign(['office_completion_reviewed_by_user_id']);
            $table->dropColumn([
                'office_completion_status',
                'office_completion_reviewed_by_user_id',
                'office_completion_reviewed_at',
                'office_completion_comments',
            ]);
        });
    }
};
