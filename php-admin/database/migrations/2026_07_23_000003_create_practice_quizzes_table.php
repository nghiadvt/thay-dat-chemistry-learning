<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_topic_id')->constrained('practice_topics')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_pro')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['practice_topic_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_quizzes');
    }
};
