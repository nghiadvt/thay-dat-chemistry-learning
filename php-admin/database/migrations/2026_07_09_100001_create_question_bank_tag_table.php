<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_tag', function (Blueprint $table) {
            $table->foreignId('question_bank_item_id')->constrained('question_bank_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['question_bank_item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_tag');
    }
};
