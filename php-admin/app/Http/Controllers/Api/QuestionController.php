<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuestionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionValidator $questionValidator,
    ) {}

    public function index(Quiz $quiz): JsonResponse
    {
        $questions = $quiz->questions()->orderBy('sort_order')->get();

        return $this->jsonSuccess(['questions' => $questions]);
    }

    public function store(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'answer_type' => ['required', 'in:mc,essay'],
            'options' => ['nullable', 'array'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:255'],
            'input_mode' => ['nullable', 'string', 'max:32'],
            'template' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($validated);
        } catch (ValidationException $e) {
            return $this->jsonError($e->getMessage(), 422, ['fields' => $e->errors()]);
        }

        $question = $quiz->questions()->create([
            ...$prepared,
            'sort_order' => $prepared['sort_order'] ?? 0,
            'time_limit_seconds' => $prepared['time_limit_seconds'] ?? 30,
            'points' => $prepared['points'] ?? 1,
        ]);

        return $this->jsonSuccess(['question' => $question], 201);
    }

    public function show(Quiz $quiz, Question $question): JsonResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);

        return $this->jsonSuccess(['question' => $question]);
    }

    public function update(Request $request, Quiz $quiz, Question $question): JsonResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);

        $validated = $request->validate([
            'content' => ['sometimes', 'required', 'string'],
            'explanation' => ['nullable', 'string'],
            'answer_type' => ['sometimes', 'required', 'in:mc,essay'],
            'options' => ['nullable', 'array'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:255'],
            'input_mode' => ['nullable', 'string', 'max:32'],
            'template' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($validated, $question);
        } catch (ValidationException $e) {
            return $this->jsonError($e->getMessage(), 422, ['fields' => $e->errors()]);
        }

        $question->update($prepared);

        return $this->jsonSuccess(['question' => $question->fresh()]);
    }

    public function destroy(Quiz $quiz, Question $question): JsonResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);
        $question->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }

    private function ensureBelongsToQuiz(Quiz $quiz, Question $question): void
    {
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }
    }
}
