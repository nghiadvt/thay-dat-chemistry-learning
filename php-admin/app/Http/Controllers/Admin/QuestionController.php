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
            'points' => $prepared['points'] ?? 1,
            'is_active' => true,
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

    public function toggleActive(Quiz $quiz, Question $question): RedirectResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);
        $question->update(['is_active' => ! $question->is_active]);

        $label = $question->is_active ? 'đã bật' : 'đã tắt';

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', "Câu hỏi #{$question->sort_order} {$label}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareData(Request $request): array
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'answer_type' => ['required', 'in:mc,essay'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:10000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:500'],
        ]);

        $data = [
            'content' => $validated['content'],
            'explanation' => $validated['explanation'] ?? null,
            'answer_type' => $validated['answer_type'],
            'time_limit_seconds' => $validated['time_limit_seconds'] ?? 30,
            'points' => $validated['points'] ?? 1,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];

        if ($validated['answer_type'] === 'mc') {
            $options = array_values(array_filter(
                array_map('trim', $validated['options'] ?? []),
                fn ($o) => $o !== ''
            ));
            $data['options'] = $options;
            $data['correct_index'] = (int) ($validated['correct_index'] ?? 0);
            $data['correct_answer_normalized'] = null;
            $data['input_mode'] = null;
            $data['template'] = null;
            $data['correct_answer'] = null;
        }

        if ($validated['answer_type'] === 'essay') {
            $data['correct_answer_normalized'] = trim($validated['correct_answer_normalized'] ?? '');
            $data['options'] = null;
            $data['correct_index'] = null;
            $data['input_mode'] = null;
            $data['template'] = null;
            $data['correct_answer'] = null;
        }

        return $data;
    }

    private function ensureBelongsToQuiz(Quiz $quiz, Question $question): void
    {
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }
    }
}
