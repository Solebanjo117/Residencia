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
        Schema::table('users', function (Blueprint $table) {
            // Modify existing columns
            $table->string('name', 120)->change();
            $table->string('email', 160)->change();
            
            // Add new columns
            $table->unsignedTinyInteger('role_id')->after('password'); 
            $table->boolean('is_active')->default(true)->after('role_id');
            
            $table->foreign('role_id')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active']);
            
            $table->string('name', 255)->change();
            $table->string('email', 255)->change();
        });
    }
};
