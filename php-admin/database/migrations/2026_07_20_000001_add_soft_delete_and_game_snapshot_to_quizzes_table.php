<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->softDeletes();
            // Snapshot game tại thời điểm xóa quiz: game có thể bị đổi tên
            // hoặc xóa hẳn sau đó, cột này giữ lại dấu vết quiz từng thuộc game nào.
            $table->unsignedBigInteger('deleted_game_id')->nullable();
            $table->string('deleted_game_name')->nullable();
        });

        // Quiz đã xóa mềm không được giữ FK tới games, nếu không sẽ chặn xóa game.
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['deleted_game_id', 'deleted_game_name']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable(false)->change();
        });
    }
};
