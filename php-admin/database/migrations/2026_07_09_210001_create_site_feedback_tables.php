<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('page_url', 512);
            $table->string('page_title', 255)->nullable();
            $table->text('body');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['new', 'read', 'done'])->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('site_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_feedback_id')->constrained('site_feedback')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_feedback_attachments');
        Schema::dropIfExists('site_feedback');
    }
};
