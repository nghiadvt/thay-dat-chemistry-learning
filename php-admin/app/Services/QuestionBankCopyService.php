<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\Quiz;

class QuestionBankCopyService
{
    /**
     * @return array<string, mixed>
     */
    public function bankAttributes(QuestionBankItem $item): array
    {
        return [
            'content' => $item->content,
            'explanation' => $item->explanation,
            'answer_type' => $item->answer_type,
            'options' => $item->options,
            'correct_index' => $item->correct_index,
            'correct_answer_normalized' => $item->correct_answer_normalized,
            'input_mode' => $item->input_mode,
            'template' => $item->template,
            'correct_answer' => $item->correct_answer,
            'time_limit_seconds' => $item->time_limit_seconds,
            'points' => $item->points,
            'is_active' => $item->is_active,
            'source_bank_question_id' => $item->id,
        ];
    }

    public function copyToQuiz(QuestionBankItem $item, Quiz $quiz, int $sortOrder): Question
    {
        return $quiz->questions()->create([
            ...$this->bankAttributes($item),
            'sort_order' => $sortOrder,
        ]);
    }

    public function nextSortOrder(Quiz $quiz): int
    {
        $max = $quiz->questions()->max('sort_order');

        return $max !== null ? ((int) $max) + 1 : 0;
    }
}
