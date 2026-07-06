<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('quiz_tag', function (Blueprint $table) {
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['quiz_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_tag');
        Schema::dropIfExists('tags');
    }
};
