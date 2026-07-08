<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Validation\ValidationException;

class QuestionValidator
{
    private const STRUCTURED_INPUT_MODES = ['balance', 'blank', 'blank_balance', 'product'];

    private const TEMPLATE_PART_TYPES = ['txt', 'chem', 'coef', 'blank'];

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
        if (! in_array($answerType, ['mc', 'essay', 'structured'], true)) {
            throw ValidationException::withMessages([
                'answer_type' => 'answer_type phải là mc, essay hoặc structured.',
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
    private function validateEssay(array $data, ?Question $existing): void
    {
        $answer = $data['correct_answer_normalized'] ?? $existing?->correct_answer_normalized;
        if (empty(trim((string) $answer))) {
            throw ValidationException::withMessages([
                'correct_answer_normalized' => 'Câu tự luận cần đáp án mẫu.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateStructured(array $data, ?Question $existing): void
    {
        $inputMode = $data['input_mode'] ?? $existing?->input_mode;
        if (! in_array($inputMode, self::STRUCTURED_INPUT_MODES, true)) {
            throw ValidationException::withMessages([
                'input_mode' => 'Chế độ phương trình không hợp lệ.',
            ]);
        }

        $template = $data['template'] ?? $existing?->template;
        if (! is_array($template) || $template === []) {
            throw ValidationException::withMessages([
                'template' => 'Cần ít nhất một phần tử trong phương trình.',
            ]);
        }

        $slots = $this->extractTemplateSlots($template);
        if ($slots['coef'] === [] && $slots['blank'] === []) {
            throw ValidationException::withMessages([
                'template' => 'Phương trình cần ít nhất một ô hệ số hoặc ô điền.',
            ]);
        }

        $correctAnswer = $data['correct_answer'] ?? $existing?->correct_answer;
        if (! is_array($correctAnswer)) {
            throw ValidationException::withMessages([
                'correct_answer' => 'Thiếu đáp án cho các ô.',
            ]);
        }

        foreach ($slots['coef'] as $id) {
            $value = trim((string) ($correctAnswer['coef'][$id] ?? ''));
            if ($value === '' || ! preg_match('/^[0-9]+$/', $value)) {
                throw ValidationException::withMessages([
                    'correct_answer' => "Hệ số «{$id}» cần đáp án là số nguyên dương.",
                ]);
            }
        }

        foreach ($slots['blank'] as $id) {
            $value = trim((string) ($correctAnswer['blank'][$id] ?? ''));
            if ($value === '') {
                throw ValidationException::withMessages([
                    'correct_answer' => "Ô điền «{$id}» cần đáp án công thức.",
                ]);
            }
        }

        if ($inputMode === 'balance' && $slots['blank'] !== []) {
            throw ValidationException::withMessages([
                'template' => 'Chế độ «Cân bằng hệ số» không dùng ô điền công thức.',
            ]);
        }

        if ($inputMode === 'blank' && $slots['coef'] !== []) {
            throw ValidationException::withMessages([
                'template' => 'Chế độ «Điền chỗ thiếu» không dùng ô hệ số.',
            ]);
        }

        if ($inputMode === 'product' && ($slots['coef'] !== [] || count($slots['blank']) !== 1)) {
            throw ValidationException::withMessages([
                'template' => 'Chế độ «Điền sản phẩm» cần đúng một ô điền, không có hệ số.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $template
     * @return array{coef: list<string>, blank: list<string>}
     */
    private function extractTemplateSlots(array $template): array
    {
        $coef = [];
        $blank = [];
        $seen = [];

        foreach ($template as $index => $part) {
            if (! is_array($part)) {
                throw ValidationException::withMessages([
                    'template' => "Phần tử template #{$index} không hợp lệ.",
                ]);
            }

            $type = $part['t'] ?? null;
            if (! in_array($type, self::TEMPLATE_PART_TYPES, true)) {
                throw ValidationException::withMessages([
                    'template' => "Loại phần tử «{$type}» không được hỗ trợ.",
                ]);
            }

            if (in_array($type, ['coef', 'blank'], true)) {
                $id = $part['id'] ?? null;
                if (! is_string($id) || $id === '' || isset($seen[$id])) {
                    throw ValidationException::withMessages([
                        'template' => 'Mỗi ô hệ số/điền cần id duy nhất.',
                    ]);
                }
                $seen[$id] = true;
                if ($type === 'coef') {
                    $coef[] = $id;
                } else {
                    $blank[] = $id;
                }
            }

            if ($type === 'txt' && ! isset($part['text'])) {
                throw ValidationException::withMessages([
                    'template' => 'Phần ký hiệu cần có text.',
                ]);
            }

            if ($type === 'chem' && empty(trim((string) ($part['text'] ?? '')))) {
                throw ValidationException::withMessages([
                    'template' => 'Phần chất cố định cần có công thức.',
                ]);
            }
        }

        return ['coef' => $coef, 'blank' => $blank];
    }
}
