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
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 60);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->dateTime('at')->useCurrent();
            $table->json('metadata')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['NEW_ASSIGNMENT','WINDOW_OPEN','WINDOW_CLOSING','SUBMISSION_APPROVED','SUBMISSION_REJECTED','GENERAL']);
            $table->string('title', 160);
            $table->string('message', 500);
            $table->string('related_entity_type', 60)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('read_at')->nullable();
        });

        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->dateTime('notify_at');
            $table->enum('notification_type', ['WINDOW_OPEN','WINDOW_CLOSING']);
            $table->boolean('is_sent')->default(false);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('advisory_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_load_id')->constrained('teaching_loads');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->date('session_date');
            $table->string('topic', 255);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('advisory_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisory_session_id')->constrained('advisory_sessions')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('stored_relative_path', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->dateTime('uploaded_at')->useCurrent();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_files');
        Schema::dropIfExists('advisory_sessions');
        Schema::dropIfExists('notification_schedules');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_log');
    }
};
