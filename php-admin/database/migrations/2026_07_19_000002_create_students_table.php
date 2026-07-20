<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('student_classes')->nullOnDelete();

            // student_code là ngữ cảnh dẫn xuất khóa của StudentPasswordCipher:
            // đổi code => mọi password_encrypted cũ không giải mã được nữa,
            // nên code là BẤT BIẾN sau khi tạo (Student::$immutableCode chặn ghi).
            $table->string('student_code', 32)->unique();
            $table->string('username', 64)->unique();
            $table->string('display_name', 100);

            // password = bcrypt hash, là nguồn sự thật duy nhất cho việc đăng nhập.
            // password_encrypted = bản mã 2 chiều, CHỈ để giáo viên xem lại.
            // Hai trường này phải luôn được ghi cùng nhau trong 1 transaction.
            $table->string('password');
            $table->text('password_encrypted')->nullable();
            $table->timestamp('password_updated_at')->nullable();

            $table->string('avatar_path', 255)->nullable();
            $table->enum('status', ['active', 'locked', 'disabled'])->default('active');
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'class_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
