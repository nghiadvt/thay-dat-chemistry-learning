<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cấu hình NHÁP của mỗi nguyên tố theo từng phiên bản. Đây là bảng khác
        // biệt giữa các phiên bản (active/ẩn/pro/thứ tự); tên/khối lượng… vẫn ở elements.
        Schema::create('periodic_preset_element', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('periodic_presets')->cascadeOnDelete();
            $table->foreignId('element_id')->constrained('elements')->cascadeOnDelete();
            $table->boolean('is_lit')->default(true);        // "sáng"/active — inactive vẫn hiển thị nhưng mờ
            $table->boolean('is_visible')->default(true);    // show/hide với học sinh
            $table->boolean('requires_pro')->default(false); // ô yêu cầu bản Pro
            $table->unsignedSmallInteger('sort_override')->nullable(); // null = theo elements.sort_order
            $table->timestamps();

            $table->unique(['preset_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodic_preset_element');
    }
};
