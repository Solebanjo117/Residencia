<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_nodes', function (Blueprint $table) {
            $table->string('icon_key', 40)->nullable()->after('semester_id');
            $table->string('color_key', 40)->nullable()->after('icon_key');
        });
    }

    public function down(): void
    {
        Schema::table('folder_nodes', function (Blueprint $table) {
            $table->dropColumn(['icon_key', 'color_key']);
        });
    }
};
