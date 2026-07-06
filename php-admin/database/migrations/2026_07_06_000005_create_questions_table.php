<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->longText('content');
            $table->enum('answer_type', ['mc', 'formula', 'structured']);
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_index')->nullable();
            $table->string('correct_answer_normalized', 255)->nullable();
            $table->string('input_mode', 32)->nullable();
            $table->json('template')->nullable();
            $table->json('correct_answer')->nullable();
            $table->integer('time_limit_seconds')->default(30);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
