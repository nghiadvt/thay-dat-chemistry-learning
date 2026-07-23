<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_quiz_question_bank_item', function (Blueprint $table) {
            $table->foreignId('practice_quiz_id')->constrained('practice_quizzes')->cascadeOnDelete();
            $table->foreignId('question_bank_item_id')->constrained('question_bank_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['practice_quiz_id', 'question_bank_item_id']);
            $table->index(['practice_quiz_id', 'sort_order'], 'pqqbi_quiz_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_quiz_question_bank_item');
    }
};
