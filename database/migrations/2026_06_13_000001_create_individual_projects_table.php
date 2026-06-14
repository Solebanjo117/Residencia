<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 255);
            $table->foreignId('folder_node_id')->nullable()->constrained('folder_nodes')->nullOnDelete();
            $table->unsignedBigInteger('docx_file_id')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_comment', 1000)->nullable();
            $table->timestamps();

            $table->index(['semester_id', 'teacher_user_id']);
            $table->index(['status', 'type']);
        });

        Schema::create('individual_project_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_project_id')->constrained('individual_projects')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 16);
            $table->string('comments', 1000)->nullable();
            $table->dateTime('reviewed_at')->useCurrent();

            $table->index(['individual_project_id', 'reviewed_at']);
        });

        Schema::table('evidence_files', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        Schema::table('evidence_files', function (Blueprint $table) {
            $table->foreignId('submission_id')
                ->nullable()
                ->change();

            $table->foreignId('individual_project_id')
                ->nullable()
                ->after('submission_id')
                ->constrained('individual_projects')
                ->nullOnDelete();

            $table->foreign('submission_id')
                ->references('id')
                ->on('evidence_submissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evidence_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('individual_project_id');
            $table->dropForeign(['submission_id']);
        });

        Schema::table('evidence_files', function (Blueprint $table) {
            $table->foreignId('submission_id')
                ->nullable(false)
                ->change();

            $table->foreign('submission_id')
                ->references('id')
                ->on('evidence_submissions')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('individual_project_reviews');
        Schema::dropIfExists('individual_projects');
    }
};
