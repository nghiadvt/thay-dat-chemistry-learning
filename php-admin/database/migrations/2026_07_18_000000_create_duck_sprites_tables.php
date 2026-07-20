<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duck_sprites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->unsignedTinyInteger('fps')->default(10);
            $table->string('folder', 255);
            $table->timestamps();
        });

        Schema::create('duck_sprite_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duck_sprite_id')->constrained('duck_sprites')->cascadeOnDelete();
            $table->string('path', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['duck_sprite_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duck_sprite_frames');
        Schema::dropIfExists('duck_sprites');
    }
};
