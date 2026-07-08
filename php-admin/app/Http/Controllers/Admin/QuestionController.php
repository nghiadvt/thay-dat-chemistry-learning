<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Tag;
use App\Services\QuestionBankCopyService;
use App\Services\QuestionBankSyncService;
use App\Services\QuestionBankTagService;
use App\Services\QuestionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionValidator $questionValidator,
        private QuestionBankCopyService $bankCopyService,
        private QuestionBankSyncService $bankSyncService,
        private QuestionBankTagService $bankTagService,
    ) {}

    public function create(Quiz $quiz): View
    {
        return view('admin.questions.form', [
            'quiz' => $quiz,
            'question' => null,
            'tags' => Tag::query()->orderBy('name')->get(),
            'selectedTagIds' => old('tag_ids', []),
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

        $question = $quiz->questions()->create([
            ...$prepared,
            'sort_order' => $prepared['sort_order'] ?? $this->bankCopyService->nextSortOrder($quiz),
            'time_limit_seconds' => $prepared['time_limit_seconds'] ?? 30,
            'points' => $prepared['points'] ?? 1,
            'is_active' => true,
        ]);

        $this->bankSyncService->syncToBank($question);
        $bankItem = $this->bankSyncService->ensureBankItem($question->fresh());
        $this->bankTagService->syncFromIds($bankItem, $request->input('tag_ids', []));

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã thêm câu hỏi.');
    }

    public function edit(Quiz $quiz, Question $question): View
    {
        $this->ensureBelongsToQuiz($quiz, $question);
        $question->load('sourceBankItem.tags');

        return view('admin.questions.form', [
            'quiz' => $quiz,
            'question' => $question,
            'tags' => Tag::query()->orderBy('name')->get(),
            'selectedTagIds' => old('tag_ids', $question->sourceBankItem?->tags->pluck('id')->all() ?? []),
        ]);
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
        $this->bankSyncService->syncToBank($question->fresh());
        $bankItem = $this->bankSyncService->ensureBankItem($question->fresh());
        $this->bankTagService->syncFromIds($bankItem, $request->input('tag_ids', []));

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

    public function updateTags(Request $request, Quiz $quiz, Question $question): JsonResponse
    {
        $this->ensureBelongsToQuiz($quiz, $question);

        $validated = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $bankItem = $this->bankSyncService->ensureBankItem($question);
        $this->bankTagService->syncFromIds($bankItem, $validated['tag_ids'] ?? []);

        $bankItem->load('tags');

        return response()->json([
            'success' => true,
            'data' => [
                'tag_ids' => $bankItem->tags->pluck('id'),
                'tags' => $bankItem->tags->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ]),
            ],
        ]);
    }

    public function fromBank(Request $request, Quiz $quiz): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'bank_ids' => ['required', 'array', 'min:1'],
            'bank_ids.*' => ['integer', 'exists:question_bank_items,id'],
        ]);

        $bankItems = QuestionBankItem::query()
            ->whereIn('id', $validated['bank_ids'])
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $sortOrder = $this->bankCopyService->nextSortOrder($quiz);
        $added = 0;

        foreach ($validated['bank_ids'] as $bankId) {
            $item = $bankItems->get((int) $bankId);
            if (! $item) {
                continue;
            }

            $exists = $quiz->questions()
                ->where('source_bank_question_id', $item->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->bankCopyService->copyToQuiz($item, $quiz, $sortOrder);
            $sortOrder++;
            $added++;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['added' => $added],
            ]);
        }

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', $added > 0 ? "Đã thêm {$added} câu hỏi từ bộ." : 'Không có câu hỏi mới được thêm.');
    }

    public function reorder(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $questionIds = collect($validated['order'])->map(fn ($id) => (int) $id)->values();
        $owned = $quiz->questions()->whereIn('id', $questionIds)->pluck('id');

        if ($owned->count() !== $questionIds->count()) {
            return response()->json(['success' => false, 'error' => 'Danh sách câu hỏi không hợp lệ.'], 422);
        }

        foreach ($questionIds as $index => $questionId) {
            Question::query()
                ->where('id', $questionId)
                ->where('quiz_id', $quiz->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function bulkUpdate(Request $request, Quiz $quiz): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'action' => ['nullable', 'in:update,delete'],
        ]);

        $wantsJson = $request->expectsJson() || $request->isJson();

        $questionIds = collect($validated['question_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $questions = $quiz->questions()->whereIn('id', $questionIds)->get();

        if ($questions->count() !== $questionIds->count()) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'error' => 'Danh sách câu hỏi không hợp lệ.'], 422);
            }

            return back()->withErrors(['bulk' => 'Danh sách câu hỏi không hợp lệ.']);
        }

        if (($validated['action'] ?? 'update') === 'delete') {
            $count = $questions->count();
            Question::query()->whereIn('id', $questionIds)->where('quiz_id', $quiz->id)->delete();

            if ($wantsJson) {
                return response()->json(['success' => true, 'data' => ['deleted' => $count]]);
            }

            return redirect()->route('admin.quizzes.show', $quiz)
                ->with('success', "Đã xóa {$count} câu hỏi.");
        }

        $updates = [];
        if ($request->has('time_limit_seconds') && $validated['time_limit_seconds'] !== null) {
            $updates['time_limit_seconds'] = (int) $validated['time_limit_seconds'];
        }
        if ($request->has('points') && $validated['points'] !== null) {
            $updates['points'] = (int) $validated['points'];
        }
        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }

        $hasTagUpdate = $request->has('tag_ids');

        if ($updates === [] && ! $hasTagUpdate) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'error' => 'Không có thay đổi nào.'], 422);
            }

            return back()->withErrors(['bulk' => 'Chọn ít nhất một thuộc tính để cập nhật.']);
        }

        if ($updates !== []) {
            Question::query()
                ->whereIn('id', $questionIds)
                ->where('quiz_id', $quiz->id)
                ->update($updates);
        }

        if ($hasTagUpdate) {
            foreach ($questions as $question) {
                $bankItem = $this->bankSyncService->ensureBankItem($question);
                $this->bankTagService->syncFromIds($bankItem, $validated['tag_ids'] ?? []);
            }
        }

        if ($wantsJson) {
            return response()->json(['success' => true, 'data' => ['updated' => $questions->count()]]);
        }

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', "Đã cập nhật {$questions->count()} câu hỏi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareData(Request $request): array
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'answer_type' => ['required', 'in:mc,essay,structured'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:10000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'input_mode' => ['nullable', 'string', 'max:32'],
            'template_json' => ['nullable', 'string'],
            'correct_answer_json' => ['nullable', 'string'],
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

        if ($validated['answer_type'] === 'structured') {
            $template = $this->decodeJsonField($request->input('template_json'), 'template');
            $correctAnswer = $this->decodeJsonField($request->input('correct_answer_json'), 'correct_answer');

            $data['input_mode'] = $validated['input_mode'] ?? 'balance';
            $data['template'] = $template;
            $data['correct_answer'] = $correctAnswer;
            $data['options'] = null;
            $data['correct_index'] = null;
            $data['correct_answer_normalized'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function decodeJsonField(?string $raw, string $field): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Dữ liệu JSON không hợp lệ.',
            ]);
        }

        return $decoded;
    }

    private function ensureBelongsToQuiz(Quiz $quiz, Question $question): void
    {
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }
    }
}
