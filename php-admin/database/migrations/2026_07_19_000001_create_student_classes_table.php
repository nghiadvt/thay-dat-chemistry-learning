<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('grade', 20)->nullable();
            // Quyền mặc định áp cho mọi học sinh trong lớp; grant riêng của học
            // sinh sẽ ghi đè lên đây (xem EntitlementResolver ở phase phân quyền).
            $table->json('default_policy')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_classes');
    }
};
