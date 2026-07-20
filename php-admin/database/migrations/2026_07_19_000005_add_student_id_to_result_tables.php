<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nối kết quả chơi vào tài khoản học sinh.
 *
 * Cột để nullable vì lượt chơi ẩn danh qua PIN (không đăng nhập) vẫn phải chạy
 * như cũ — khi đó chỉ có student_name như trước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('session_id')
                ->constrained('students')->nullOnDelete();
            $table->index(['student_id', 'created_at']);
        });

        Schema::table('session_answers', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('session_id')
                ->constrained('students')->nullOnDelete();
            $table->index(['student_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'created_at']);
            $table->dropConstrainedForeignId('student_id');
        });

        Schema::table('session_answers', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'session_id']);
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
