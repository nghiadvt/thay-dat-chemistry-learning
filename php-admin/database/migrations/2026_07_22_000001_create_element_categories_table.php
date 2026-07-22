<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('element_categories', function (Blueprint $table) {
            $table->id();
            // slug ổn định (kiem, kiem-tho, chuyen-tiep…) — student client map màu theo slug.
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('color', 32);        // màu ô
            $table->string('deep_color', 32);   // màu đậm (viền/nhấn)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('element_categories');
    }
};
