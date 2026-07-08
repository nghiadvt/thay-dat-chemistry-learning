<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\Tag;
use App\Services\QuestionBankTagService;
use App\Services\QuestionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    public function __construct(
        private QuestionValidator $questionValidator,
        private QuestionBankTagService $tagService,
    ) {}

    public function index(Request $request): View
    {
        $query = QuestionBankItem::query()
            ->with('tags')
            ->orderByDesc('updated_at');

        $this->applyTagFilter($query, $request);

        if ($request->filled('answer_type')) {
            $query->where('answer_type', $request->string('answer_type'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where('content', 'like', $term);
        }

        $filter = $this->resolveTagFilterParams($request);

        return view('admin.question-bank.index', [
            'items' => $query->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'filterTagIds' => $filter['tag_ids'],
            'filterTagNone' => $filter['tag_none'],
            'filterTagMatch' => $filter['tag_match'],
            'filterAnswerType' => $request->string('answer_type')->toString() ?: null,
            'filterQuery' => $request->string('q')->toString() ?: null,
        ]);
    }

    public function create(): View
    {
        return view('admin.question-bank.form', [
            'item' => null,
            'tags' => Tag::query()->orderBy('name')->get(),
            'selectedTagIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->prepareData($request);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $item = QuestionBankItem::create([
            ...$prepared,
            'time_limit_seconds' => $prepared['time_limit_seconds'] ?? 30,
            'points' => $prepared['points'] ?? 1,
            'is_active' => true,
        ]);

        $this->tagService->syncFromIds($item, $request->input('tag_ids', []));

        return redirect()->route('admin.question-bank.index')
            ->with('success', 'Đã thêm câu hỏi vào bộ.');
    }

    public function edit(QuestionBankItem $question_bank): View
    {
        $question_bank->load('tags');

        return view('admin.question-bank.form', [
            'item' => $question_bank,
            'tags' => Tag::query()->orderBy('name')->get(),
            'selectedTagIds' => old('tag_ids', $question_bank->tags->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, QuestionBankItem $question_bank): RedirectResponse
    {
        $data = $this->prepareData($request);

        try {
            $prepared = $this->questionValidator->validateAndPrepare($data, $question_bank);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $question_bank->update($prepared);
        $this->tagService->syncFromIds($question_bank, $request->input('tag_ids', []));

        return redirect()->route('admin.question-bank.index')
            ->with('success', 'Đã cập nhật câu hỏi trong bộ.');
    }

    public function updateTags(Request $request, QuestionBankItem $question_bank): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $this->tagService->syncFromIds($question_bank, $validated['tag_ids'] ?? []);
        $question_bank->load('tags');

        return response()->json([
            'success' => true,
            'data' => [
                'tag_ids' => $question_bank->tags->pluck('id'),
                'tags' => $question_bank->tags->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ]),
            ],
        ]);
    }

    public function bulkUpdateTags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:question_bank_items,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $itemIds = collect($validated['item_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $items = QuestionBankItem::query()->whereIn('id', $itemIds)->get();

        if ($items->count() !== $itemIds->count()) {
            return response()->json(['success' => false, 'error' => 'Danh sách câu hỏi không hợp lệ.'], 422);
        }

        foreach ($items as $item) {
            $this->tagService->syncFromIds($item, $validated['tag_ids'] ?? []);
        }

        return response()->json([
            'success' => true,
            'data' => ['updated' => $items->count()],
        ]);
    }

    public function destroy(QuestionBankItem $question_bank): RedirectResponse
    {
        $question_bank->delete();

        return redirect()->route('admin.question-bank.index')
            ->with('success', 'Đã xóa câu hỏi khỏi bộ.');
    }

    public function search(Request $request): JsonResponse
    {
        $quizId = $request->integer('quiz_id');
        $existingBankIds = [];
        if ($quizId > 0) {
            $existingBankIds = \App\Models\Question::query()
                ->where('quiz_id', $quizId)
                ->whereNotNull('source_bank_question_id')
                ->pluck('source_bank_question_id')
                ->all();
        }

        $query = QuestionBankItem::query()
            ->with('tags')
            ->where('is_active', true)
            ->orderByDesc('updated_at');

        $this->applyTagFilter($query, $request);

        if ($request->filled('answer_type')) {
            $query->where('answer_type', $request->string('answer_type'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where('content', 'like', $term);
        }

        $items = $query->limit(100)->get()->map(function (QuestionBankItem $item) use ($existingBankIds) {
            return [
                'id' => $item->id,
                'answer_type' => $item->answer_type,
                'answer_type_label' => $this->answerTypeLabel($item),
                'content_preview' => Str::limit(strip_tags($item->content), 120),
                'time_limit_seconds' => $item->time_limit_seconds,
                'points' => $item->points,
                'tags' => $item->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
                'already_in_quiz' => in_array($item->id, $existingBankIds, true),
            ];
        });

        return response()->json(['success' => true, 'data' => $items]);
    }

    private function answerTypeLabel(QuestionBankItem $item): string
    {
        return match ($item->answer_type) {
            'mc' => 'Trắc nghiệm',
            'structured' => match ($item->input_mode) {
                'balance' => 'Cân bằng hệ số',
                'blank' => 'Điền chỗ thiếu',
                'blank_balance' => 'Cân bằng + điền',
                'product' => 'Điền sản phẩm',
                default => 'Phương trình',
            },
            default => 'Tự luận',
        };
    }

    /**
     * @return array{tag_ids: list<int>, tag_none: bool, tag_match: 'and'|'or'}
     */
    private function resolveTagFilterParams(Request $request): array
    {
        $tagIds = collect($request->input('tag_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $tagNone = $request->boolean('tag_none');
        $tagMatch = $request->string('tag_match')->toString();
        if (! in_array($tagMatch, ['or', 'and'], true)) {
            $tagMatch = 'and';
        }

        if ($tagIds === [] && $request->filled('tag_id')) {
            $legacy = $request->string('tag_id')->toString();
            if ($legacy === 'none') {
                $tagNone = true;
            } elseif ($legacy !== '') {
                $tagIds = [(int) $legacy];
            }
        }

        return ['tag_ids' => $tagIds, 'tag_none' => $tagNone, 'tag_match' => $tagMatch];
    }

    private function applyTagFilter($query, Request $request): void
    {
        ['tag_ids' => $tagIds, 'tag_none' => $tagNone, 'tag_match' => $tagMatch] = $this->resolveTagFilterParams($request);

        if ($tagIds === [] && ! $tagNone) {
            return;
        }

        if ($tagNone && $tagIds === []) {
            $query->whereDoesntHave('tags');

            return;
        }

        if (! $tagNone && $tagIds !== []) {
            $this->applyTagIdsMatch($query, $tagIds, $tagMatch);

            return;
        }

        // tag_none = true và có tag: (chưa có tag) OR (khớp theo tag_match)
        $query->where(function ($q) use ($tagIds, $tagMatch) {
            $q->whereDoesntHave('tags')
                ->orWhere(function ($q2) use ($tagIds, $tagMatch) {
                    $this->applyTagIdsMatch($q2, $tagIds, $tagMatch);
                });
        });
    }

    /**
     * @param  list<int>  $tagIds
     */
    private function applyTagIdsMatch($query, array $tagIds, string $tagMatch): void
    {
        if ($tagMatch === 'or') {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));

            return;
        }

        foreach ($tagIds as $tagId) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }
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
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_answer_normalized' => ['nullable', 'string', 'max:10000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'input_mode' => ['nullable', 'string', 'max:32'],
            'template_json' => ['nullable', 'string'],
            'correct_answer_json' => ['nullable', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $data = [
            'content' => $validated['content'],
            'explanation' => $validated['explanation'] ?? null,
            'answer_type' => $validated['answer_type'],
            'time_limit_seconds' => $validated['time_limit_seconds'] ?? 30,
            'points' => $validated['points'] ?? 1,
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
}
