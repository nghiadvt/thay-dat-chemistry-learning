<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $preset = ['#2D46D6', '#059669', '#DC2626', '#D97706', '#7C3AED', '#0891B2', '#DB2777'];
        $tagIds = DB::table('tags')->orderBy('id')->pluck('id');
        foreach ($tagIds as $i => $tagId) {
            DB::table('tags')->where('id', $tagId)->update([
                'color' => $preset[$i % count($preset)],
            ]);
        }

        $questions = DB::table('questions')->whereNull('source_bank_question_id')->get();
        $now = now();

        foreach ($questions as $question) {
            $bankId = DB::table('question_bank_items')->insertGetId([
                'content' => $question->content,
                'explanation' => $question->explanation,
                'answer_type' => $question->answer_type,
                'options' => $question->options,
                'correct_index' => $question->correct_index,
                'correct_answer_normalized' => $question->correct_answer_normalized,
                'input_mode' => $question->input_mode,
                'template' => $question->template,
                'correct_answer' => $question->correct_answer,
                'time_limit_seconds' => $question->time_limit_seconds,
                'points' => $question->points ?? 1,
                'is_active' => $question->is_active ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('questions')->where('id', $question->id)->update([
                'source_bank_question_id' => $bankId,
            ]);
        }
    }

    public function down(): void
    {
        // Không xóa bank items đã backfill — chỉ bỏ liên kết nếu cần rollback thủ công.
    }
};
