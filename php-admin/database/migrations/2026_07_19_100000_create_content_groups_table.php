<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_groups', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('color', 7)->default('#2D46D6');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'slug']);
            $table->index(['scope', 'sort_order']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')
                ->constrained('content_groups')->nullOnDelete();
        });

        Schema::table('question_bank_items', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')
                ->constrained('content_groups')->nullOnDelete();
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')
                ->constrained('content_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['quizzes', 'question_bank_items', 'game_sessions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['group_id']);
                $table->dropColumn('group_id');
            });
        }

        Schema::dropIfExists('content_groups');
    }
};
