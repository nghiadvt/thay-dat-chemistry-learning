<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionBankItem;

class QuestionBankSyncService
{
    /**
     * @return array<string, mixed>
     */
    public function attributesFromQuestion(Question $question): array
    {
        return [
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
            'points' => $question->points,
            'is_active' => $question->is_active,
        ];
    }

    public function ensureBankItem(Question $question): QuestionBankItem
    {
        if ($question->source_bank_question_id) {
            $item = QuestionBankItem::find($question->source_bank_question_id);
            if ($item) {
                return $item;
            }
        }

        $item = QuestionBankItem::create($this->attributesFromQuestion($question));
        $question->update(['source_bank_question_id' => $item->id]);

        return $item;
    }

    public function syncToBank(Question $question): void
    {
        $item = $this->ensureBankItem($question);
        $item->update($this->attributesFromQuestion($question));
    }
}
