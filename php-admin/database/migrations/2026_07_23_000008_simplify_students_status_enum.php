<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'disabled' chỉ được set thủ công qua form sửa học sinh và không có ý nghĩa
 * riêng biệt với 'locked' — gộp về 'locked' (được coi là giáo viên chủ động
 * khóa, xem StudentLockLog) để trạng thái tài khoản chỉ còn active/locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')->where('status', 'disabled')->update(['status' => 'locked']);

        DB::statement("ALTER TABLE students MODIFY status ENUM('active', 'locked') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY status ENUM('active', 'locked', 'disabled') NOT NULL DEFAULT 'active'");
    }
};
