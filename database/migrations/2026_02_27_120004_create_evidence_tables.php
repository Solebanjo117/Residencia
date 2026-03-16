<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evidence_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();
        });

        DB::table('evidence_categories')->insert([
            ['name' => 'I_CARGA_ACADEMICA', 'description' => 'Evidencias relacionadas a la carga académica'],
            ['name' => 'II_APOYO_DOCENCIA', 'description' => 'Actividades de apoyo a la docencia'],
            ['name' => 'III_CARGO_ADMIN', 'description' => 'Actividades/cargo administrativo'],
        ]);

        Schema::create('evidence_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('category_id');
            $table->string('name', 140);
            $table->string('description', 255)->nullable();
            $table->boolean('requires_subject')->default(true);
            $table->boolean('active')->default(true);
            
            $table->foreign('category_id')->references('id')->on('evidence_categories');
            $table->unique(['category_id', 'name']);
        });

        Schema::create('evidence_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140)->unique();
            $table->string('template_url', 255)->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::create('evidence_item_formats', function (Blueprint $table) {
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->foreignId('format_id')->constrained('evidence_formats');
            $table->primary(['evidence_item_id', 'format_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_item_formats');
        Schema::dropIfExists('evidence_formats');
        Schema::dropIfExists('evidence_items');
        Schema::dropIfExists('evidence_categories');
    }
};
