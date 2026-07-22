<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('sides')->default(1);
            $table->decimal('frame_width_mm', 6, 2)->default(85.60);
            $table->decimal('frame_height_mm', 6, 2)->default(53.98);
            $table->string('front_baked_path', 255)->nullable();
            $table->string('back_baked_path', 255)->nullable();
            $table->json('layout');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_templates');
    }
};
