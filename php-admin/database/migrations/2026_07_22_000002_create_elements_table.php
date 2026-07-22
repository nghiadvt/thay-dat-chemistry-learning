<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog nguyên tố GỐC dùng chung mọi phiên bản bảng. Đổi ở đây áp
        // dụng cho tất cả phiên bản; phần active/ẩn/pro/thứ tự nằm ở pivot.
        Schema::create('elements', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('z')->unique();     // số hiệu nguyên tử
            $table->string('symbol', 8);
            $table->string('name_vi');
            $table->string('name_en');
            $table->decimal('mass', 9, 4);
            $table->foreignId('category_id')->nullable()->constrained('element_categories')->nullOnDelete();
            $table->string('phonetic')->nullable();          // gợi ý đọc tiếng Việt (TTS)
            $table->unsignedTinyInteger('group_no');         // cột 1–18
            $table->unsignedTinyInteger('period_no');        // chu kỳ 1–7
            $table->string('sound_path')->nullable();        // file audio upload (ưu tiên hơn TTS)
            $table->unsignedSmallInteger('sort_order')->default(0); // thứ tự xuất hiện mặc định
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elements');
    }
};
