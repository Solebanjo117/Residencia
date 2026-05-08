<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_load_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->constrained('users');
            $table->string('decision', 20);
            $table->text('comments')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();

            $table->index(['teaching_load_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_load_reviews');
    }
};
