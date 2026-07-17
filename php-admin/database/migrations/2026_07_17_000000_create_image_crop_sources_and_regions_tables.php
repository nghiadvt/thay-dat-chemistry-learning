<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_crop_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('original_filename', 255)->nullable();
            $table->string('path', 255);
            $table->string('folder', 255);
            $table->timestamps();
        });

        Schema::create('image_crop_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_crop_source_id')->constrained('image_crop_sources')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->unsignedInteger('w')->default(0);
            $table->unsignedInteger('h')->default(0);
            $table->decimal('rotation', 6, 2)->default(0);
            $table->boolean('flipped')->default(false);
            $table->string('cropped_path', 255)->nullable();
            $table->timestamps();

            $table->index(['image_crop_source_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_crop_regions');
        Schema::dropIfExists('image_crop_sources');
    }
};
