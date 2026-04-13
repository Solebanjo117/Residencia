<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_reviews', function (Blueprint $table) {
            $table->string('stage', 20)->default('OFFICE')->after('decision');
        });

        DB::table('evidence_reviews')->update([
            'stage' => 'OFFICE',
        ]);
    }

    public function down(): void
    {
        Schema::table('evidence_reviews', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
