<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuestionValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionValidator $questionValidator,
    ) {}

    public function create(Quiz $quiz): View
    {
        return view('admin.questions.form', [
            'quiz' => $quiz,
            'question' => null,
        ]);
    }

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $this->prepareData($request);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $quiz->questions()->create([
            ...$prepared,
            'sort_order' => $prepared['sort_order'] ?? 0,
            'time_limit_seconds' => $prepared['time_limit_seconds'] ?? 30,
        ]);

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã thêm câu hỏi.');
    }

    public function edit(Quiz $quiz, Question $question): View
    {
        $this->ensureBelongsToQuiz($quiz, $question);

        return view('admin.questions.form', compact('quiz', 'question'));
    }

    public function update(Request $request, Quiz $quiz, Question $question): RedirectResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);

        $data = $this->prepareData($request);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($data, $question);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $question->update($prepared);

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã cập nhật câu hỏi.');
    }

    public function destroy(Quiz $quiz, Question $question): RedirectResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);
        $question->delete();

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã xóa câu hỏi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareData(Request $request): array
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'answer_type' => ['required', 'in:mc,formula,structured'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:255'],
            'input_mode' => ['nullable', 'string', 'max:32'],
            'options_text' => ['nullable', 'string'],
            'template_json' => ['nullable', 'string'],
            'correct_answer_json' => ['nullable', 'string'],
        ]);

        $data = [
            'content' => $validated['content'],
            'answer_type' => $validated['answer_type'],
            'time_limit_seconds' => $validated['time_limit_seconds'] ?? 30,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];

        if ($validated['answer_type'] === 'mc') {
            $options = array_values(array_filter(
                array_map('trim', explode("\n", $validated['options_text'] ?? '')),
                fn ($o) => $o !== ''
            ));
            $data['options'] = $options;
            $data['correct_index'] = (int) ($validated['correct_index'] ?? 0);
        }

        if ($validated['answer_type'] === 'formula') {
            $data['correct_answer_normalized'] = trim($validated['correct_answer_normalized'] ?? '');
        }

        if ($validated['answer_type'] === 'structured') {
            $data['input_mode'] = $validated['input_mode'] ?? '';
            $template = $this->parseJson($validated['template_json'] ?? '');
            $correctAnswer = $this->parseJson($validated['correct_answer_json'] ?? '');
            if ($template === null || $correctAnswer === null) {
                throw ValidationException::withMessages([
                    'template' => 'Template và correct_answer phải là JSON hợp lệ.',
                ]);
            }
            $data['template'] = $template;
            $data['correct_answer'] = $correctAnswer;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function ensureBelongsToQuiz(Quiz $quiz, Question $question): void
    {
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }
    }
}
