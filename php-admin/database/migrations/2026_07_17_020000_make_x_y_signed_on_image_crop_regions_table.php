<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vùng khoanh giờ được phép kéo ra ngoài rìa ảnh (chỉ phần đè lên ảnh
     * mới được cắt) — x/y cần cho phép âm khi vùng lấn ra mép trái/trên.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE image_crop_regions MODIFY x INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE image_crop_regions MODIFY y INT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE image_crop_regions MODIFY x INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE image_crop_regions MODIFY y INT UNSIGNED NOT NULL DEFAULT 0');
    }
};
