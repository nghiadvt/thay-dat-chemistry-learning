<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lượt chơi một mình của học sinh (ngoài phòng live của giáo viên).
 *
 * Không dùng lại game_sessions vì bảng đó bắt buộc host_id + pin — vốn dành cho
 * phòng do giáo viên mở. Lượt solo không có host và không có PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('feature_key', 64); // khóa trong FeatureRegistry
            $table->string('label', 150)->nullable(); // tên đề/chủ đề để hiện trong thống kê
            $table->string('topic_slug', 100)->nullable();
            $table->string('grade_slug', 32)->nullable();

            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'feature_key', 'created_at']);
        });

        Schema::create('practice_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('practice_attempts')->cascadeOnDelete();
            $table->foreignId('question_bank_item_id')->nullable()
                ->constrained('question_bank_items')->nullOnDelete();

            // Một dòng cho MỖI câu được phát ra, kể cả câu chưa làm — để màn
            // thống kê vẽ được lưới ô đúng / sai / chưa làm.
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('answer_index')->nullable(); // null = chưa làm
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_attempt_answers');
        Schema::dropIfExists('practice_attempts');
    }
};
