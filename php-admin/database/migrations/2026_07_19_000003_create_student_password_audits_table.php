<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_password_audits', function (Blueprint $table) {
            $table->id();
            // Giữ lại dấu vết kể cả khi học sinh bị xóa => nullOnDelete.
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // encrypt | decrypt | apply | scan | reset
            $table->string('action', 16);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_password_audits');
    }
};
