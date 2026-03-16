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
        // 1. academic_periods
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['PLANNED','ACTIVE','CLOSED'])->default('PLANNED');
            $table->timestamp('created_at')->useCurrent();
        });

        // 2. semesters
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN','CLOSED'])->default('OPEN');
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods');
        });

        // 3. subjects
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
        });

        // 4. teaching_loads
        Schema::create('teaching_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_user_id')->constrained('users');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->string('group_code', 40);
            $table->unsignedTinyInteger('hours_per_week')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['teacher_user_id', 'semester_id']);
            $table->index('semester_id');
            $table->index('subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_loads');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_periods');
    }
};
