<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evidence_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('teacher_user_id')->constrained('users');
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->foreignId('teaching_load_id')->constrained('teaching_loads');
            $table->enum('status', ['DRAFT','SUBMITTED','APPROVED','REJECTED','NA','NE'])->default('DRAFT');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamp('last_updated_at')->useCurrent()->useCurrentOnUpdate(); 
            
            $table->unique(['semester_id', 'teacher_user_id', 'evidence_item_id', 'teaching_load_id'], 'uk_es_unique');
        });

        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('evidence_submissions')->cascadeOnDelete();
            $table->foreignId('folder_node_id')->constrained('folder_nodes');
            $table->string('file_name', 255);
            $table->string('stored_relative_path', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->dateTime('uploaded_at')->useCurrent();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->dateTime('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users');
        });

        Schema::create('evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('evidence_submissions')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->constrained('users');
            $table->enum('decision', ['APPROVE','REJECT']);
            $table->string('comments', 500)->nullable();
            $table->dateTime('reviewed_at')->useCurrent();
        });

        Schema::create('evidence_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('evidence_submissions')->cascadeOnDelete();
            $table->enum('old_status', ['DRAFT','SUBMITTED','APPROVED','REJECTED','NA','NE']);
            $table->enum('new_status', ['DRAFT','SUBMITTED','APPROVED','REJECTED','NA','NE']);
            $table->foreignId('changed_by_user_id')->constrained('users');
            $table->string('change_reason', 500)->nullable();
            $table->dateTime('changed_at')->useCurrent();
        });

        Schema::create('resubmission_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('evidence_submissions')->cascadeOnDelete();
            $table->foreignId('unlocked_by_user_id')->constrained('users');
            $table->dateTime('unlocked_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->string('reason', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resubmission_unlocks');
        Schema::dropIfExists('evidence_status_history');
        Schema::dropIfExists('evidence_reviews');
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('evidence_submissions');
    }
};
