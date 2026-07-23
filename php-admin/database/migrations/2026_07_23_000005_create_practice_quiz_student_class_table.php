<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_quiz_student_class', function (Blueprint $table) {
            $table->foreignId('practice_quiz_id')->constrained('practice_quizzes')->cascadeOnDelete();
            $table->foreignId('student_class_id')->constrained('student_classes')->cascadeOnDelete();

            $table->primary(['practice_quiz_id', 'student_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_quiz_student_class');
    }
};
