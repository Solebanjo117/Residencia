<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('format_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->enum('status', ['ACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('current_format_publication_file_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('evidence_item_id');
        });

        Schema::create('format_publication_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('format_publication_id')->constrained('format_publications')->cascadeOnDelete();
            $table->string('file_name', 160);
            $table->string('stored_relative_path', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('file_hash', 64)->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index(['format_publication_id', 'is_current']);
            $table->unique('stored_relative_path');
        });

        Schema::table('format_publications', function (Blueprint $table) {
            $table->foreign('current_format_publication_file_id', 'fp_current_file_fk')
                ->references('id')
                ->on('format_publication_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('format_publications', function (Blueprint $table) {
            $table->dropForeign('fp_current_file_fk');
        });

        Schema::dropIfExists('format_publication_files');
        Schema::dropIfExists('format_publications');
    }
};

