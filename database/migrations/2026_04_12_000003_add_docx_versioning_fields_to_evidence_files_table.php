<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_files', function (Blueprint $table) {
            $table->foreignId('previous_version_file_id')
                ->nullable()
                ->after('submission_id')
                ->constrained('evidence_files')
                ->nullOnDelete();

            $table->foreignId('root_file_id')
                ->nullable()
                ->after('previous_version_file_id')
                ->constrained('evidence_files')
                ->nullOnDelete();

            $table->dateTime('last_edited_at')
                ->nullable()
                ->after('uploaded_at');

            $table->foreignId('last_edited_by_user_id')
                ->nullable()
                ->after('last_edited_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('editor_source', 32)
                ->nullable()
                ->after('last_edited_by_user_id');

            $table->json('editor_meta')
                ->nullable()
                ->after('editor_source');

            $table->boolean('is_current_version')
                ->default(true)
                ->after('editor_meta');

            $table->index(['folder_node_id', 'is_current_version'], 'evidence_files_folder_current_idx');
            $table->index(['submission_id', 'is_current_version'], 'evidence_files_submission_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_files', function (Blueprint $table) {
            $table->dropIndex('evidence_files_folder_current_idx');
            $table->dropIndex('evidence_files_submission_current_idx');
            $table->dropConstrainedForeignId('previous_version_file_id');
            $table->dropConstrainedForeignId('root_file_id');
            $table->dropConstrainedForeignId('last_edited_by_user_id');
            $table->dropColumn([
                'last_edited_at',
                'editor_source',
                'editor_meta',
                'is_current_version',
            ]);
        });
    }
};
