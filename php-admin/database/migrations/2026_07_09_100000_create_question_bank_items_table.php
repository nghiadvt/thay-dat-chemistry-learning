<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->longText('content');
            $table->longText('explanation')->nullable();
            $table->enum('answer_type', ['mc', 'essay', 'structured']);
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_index')->nullable();
            $table->text('correct_answer_normalized')->nullable();
            $table->string('input_mode', 32)->nullable();
            $table->json('template')->nullable();
            $table->json('correct_answer')->nullable();
            $table->integer('time_limit_seconds')->default(30);
            $table->unsignedSmallInteger('points')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_items');
    }
};
