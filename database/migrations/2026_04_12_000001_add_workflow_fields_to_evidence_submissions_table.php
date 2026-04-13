<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_submissions', function (Blueprint $table) {
            $table->boolean('submitted_late')->default(false)->after('submitted_at');
            $table->dateTime('office_reviewed_at')->nullable()->after('submitted_late');
            $table->foreignId('office_reviewed_by_user_id')->nullable()->after('office_reviewed_at')->constrained('users');
            $table->dateTime('final_approved_at')->nullable()->after('office_reviewed_by_user_id');
            $table->foreignId('final_approved_by_user_id')->nullable()->after('final_approved_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('final_approved_by_user_id');
            $table->dropColumn('final_approved_at');
            $table->dropConstrainedForeignId('office_reviewed_by_user_id');
            $table->dropColumn('office_reviewed_at');
            $table->dropColumn('submitted_late');
        });
    }
};
