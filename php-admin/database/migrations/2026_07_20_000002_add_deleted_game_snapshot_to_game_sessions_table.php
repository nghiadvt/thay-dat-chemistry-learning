<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Snapshot game khi game bị xóa: lịch sử phòng chơi được giữ nguyên,
            // vẫn biết phòng từng chạy game nào dù game đã xóa hoặc đổi tên.
            $table->unsignedBigInteger('deleted_game_id')->nullable();
            $table->string('deleted_game_name')->nullable();
        });

        // Gỡ FK bắt buộc để xóa game không còn bị chặn bởi lịch sử phòng chơi.
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['deleted_game_id', 'deleted_game_name']);
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable(false)->change();
        });
    }
};
