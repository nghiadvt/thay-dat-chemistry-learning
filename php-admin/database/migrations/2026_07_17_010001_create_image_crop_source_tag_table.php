<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_crop_source_tag', function (Blueprint $table) {
            $table->foreignId('image_crop_source_id')->constrained('image_crop_sources')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['image_crop_source_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_crop_source_tag');
    }
};
