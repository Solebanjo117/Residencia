<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_submissions', function (Blueprint $table) {
            $table->string('manual_ui_status', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_submissions', function (Blueprint $table) {
            $table->dropColumn('manual_ui_status');
        });
    }
};
