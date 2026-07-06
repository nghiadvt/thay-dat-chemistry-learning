<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Validation\ValidationException;

class QuestionValidator
{
    public function __construct(
        private HtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateAndPrepare(array $data, ?Question $existing = null): array
    {
        $answerType = $data['answer_type'] ?? $existing?->answer_type;
        if (! in_array($answerType, ['mc', 'essay'], true)) {
            throw ValidationException::withMessages([
                'answer_type' => 'answer_type phải là mc hoặc essay.',
            ]);
        }

        if (isset($data['content'])) {
            $data['content'] = $this->htmlSanitizer->sanitize($data['content']);
        }

        if (array_key_exists('explanation', $data)) {
            if ($data['explanation'] === null || $data['explanation'] === '') {
                $data['explanation'] = null;
            } else {
                $data['explanation'] = $this->htmlSanitizer->sanitize($data['explanation']);
            }
        }

        if (isset($data['points'])) {
            $data['points'] = max(1, min(100, (int) $data['points']));
        }

        match ($answerType) {
            'mc' => $this->validateMultipleChoice($data, $existing),
            'essay' => $this->validateEssay($data, $existing),
        };

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateMultipleChoice(array $data, ?Question $existing): void
    {
        $options = $data['options'] ?? $existing?->options;
        $correctIndex = $data['correct_index'] ?? $existing?->correct_index;

        if (! is_array($options) || count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'Câu trắc nghiệm cần ít nhất 2 đáp án.',
            ]);
        }

        if ($correctIndex === null || $correctIndex < 0 || $correctIndex >= count($options)) {
            throw ValidationException::withMessages([
                'correct_index' => 'correct_index không hợp lệ.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateEssay(array $data, ?Question $existing): void
    {
        $answer = $data['correct_answer_normalized'] ?? $existing?->correct_answer_normalized;
        if (empty(trim((string) $answer))) {
            throw ValidationException::withMessages([
                'correct_answer_normalized' => 'Câu tự luận cần đáp án mẫu.',
            ]);
        }
    }
}
