<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->timestamp('locked_at');
            $table->timestamp('unlocked_at')->nullable();
            $table->string('ip_address', 45)->nullable();

            // true = giáo viên/admin chủ động khóa; false = hệ thống tự khóa do
            // học sinh nhập sai mật khẩu quá Student::MAX_FAILED_ATTEMPTS lần.
            $table->boolean('locked_by_teacher');
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['student_id', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lock_logs');
    }
};
