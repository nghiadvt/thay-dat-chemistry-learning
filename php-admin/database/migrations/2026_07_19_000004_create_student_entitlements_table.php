<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_entitlements', function (Blueprint $table) {
            $table->id();

            // Cấp cho một học sinh HOẶC cả lớp. Grant của học sinh ghi đè grant
            // của lớp khi cùng feature_key (xem EntitlementResolver).
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('student_classes')->cascadeOnDelete();

            // Khóa tính năng lấy từ FeatureRegistry — cố tình KHÔNG dùng cột
            // riêng cho từng game để thêm game mới không phải đổi schema.
            $table->string('feature_key', 64);
            $table->enum('access_level', ['none', 'free', 'full'])->default('full');

            // Ghi đè lẻ từng phần của phạm vi (vd chỉ mở thêm 10 nguyên tố).
            $table->json('scope')->nullable();

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = vĩnh viễn
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'feature_key']);
            $table->index(['class_id', 'feature_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_entitlements');
    }
};
