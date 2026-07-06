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
        if (! in_array($answerType, ['mc', 'formula', 'structured'], true)) {
            throw ValidationException::withMessages([
                'answer_type' => 'answer_type phải là mc, formula hoặc structured.',
            ]);
        }

        if (isset($data['content'])) {
            $data['content'] = $this->htmlSanitizer->sanitize($data['content']);
        }

        match ($answerType) {
            'mc' => $this->validateMultipleChoice($data, $existing),
            'formula' => $this->validateFormula($data, $existing),
            'structured' => $this->validateStructured($data, $existing),
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
    private function validateFormula(array $data, ?Question $existing): void
    {
        $answer = $data['correct_answer_normalized'] ?? $existing?->correct_answer_normalized;
        if (empty($answer)) {
            throw ValidationException::withMessages([
                'correct_answer_normalized' => 'Câu công thức cần correct_answer_normalized.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateStructured(array $data, ?Question $existing): void
    {
        $inputMode = $data['input_mode'] ?? $existing?->input_mode;
        $template = $data['template'] ?? $existing?->template;
        $correctAnswer = $data['correct_answer'] ?? $existing?->correct_answer;

        if (empty($inputMode)) {
            throw ValidationException::withMessages([
                'input_mode' => 'Câu structured cần input_mode.',
            ]);
        }

        if (empty($template) || empty($correctAnswer)) {
            throw ValidationException::withMessages([
                'template' => 'Câu structured cần template và correct_answer.',
            ]);
        }
    }
}
