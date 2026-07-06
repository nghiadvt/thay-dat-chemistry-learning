<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions');
            $table->string('student_name', 20);
            $table->json('answer_submitted')->nullable();
            $table->boolean('is_correct');
            $table->integer('score_earned')->default(0);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['session_id', 'question_id', 'student_name'], 'session_question_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_answers');
    }
};
