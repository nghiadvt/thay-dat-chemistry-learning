<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_grade_id')->constrained('practice_grades')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['practice_grade_id', 'slug']);
            $table->index(['practice_grade_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_topics');
    }
};
