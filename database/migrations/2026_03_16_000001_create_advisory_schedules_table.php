<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisory_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_load_id')->constrained('teaching_loads');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->unsignedTinyInteger('day_of_week'); // 1=Lunes, 2=Martes, ..., 5=Viernes
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location', 100)->nullable(); // aula o lugar
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisory_schedules');
    }
};
