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
        Schema::create('evidence_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->unsignedSmallInteger('department_id')->nullable();
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->boolean('is_mandatory')->default(true);
            $table->json('applies_condition')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('department_id')->references('id')->on('departments');
            $table->unique(['semester_id', 'evidence_item_id', 'department_id'], 'uk_er_sem_item_dept');
        });

        Schema::create('submission_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->enum('status', ['ACTIVE','INACTIVE'])->default('ACTIVE');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['semester_id', 'evidence_item_id']);
        });

        Schema::create('storage_roots', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80)->unique();
            $table->string('base_path', 255);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('folder_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedSmallInteger('storage_root_id');
            $table->string('name', 160);
            $table->string('relative_path', 255);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('parent_id')->references('id')->on('folder_nodes')->nullOnDelete();
            $table->foreign('storage_root_id')->references('id')->on('storage_roots');
            $table->unique(['storage_root_id', 'relative_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folder_nodes');
        Schema::dropIfExists('storage_roots');
        Schema::dropIfExists('submission_windows');
        Schema::dropIfExists('evidence_requirements');
    }
};
