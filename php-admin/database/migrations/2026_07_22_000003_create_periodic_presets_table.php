<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Một "phiên bản bảng" (giáo viên gọi là phiên bản). Mô hình Nháp/Xuất
        // bản: cấu hình nháp nằm ở pivot periodic_preset_element; học sinh CHỈ
        // đọc published_snapshot (đóng băng lúc Xuất bản) của phiên bản is_live.
        Schema::create('periodic_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_live')->default(false);            // chỉ 1 phiên bản true
            $table->json('published_snapshot')->nullable();        // ảnh chụp dữ liệu lúc xuất bản
            $table->timestamp('published_at')->nullable();
            $table->boolean('has_unpublished_changes')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_live');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodic_presets');
    }
};
