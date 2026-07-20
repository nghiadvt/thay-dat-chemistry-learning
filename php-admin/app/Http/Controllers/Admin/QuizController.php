<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminCsv;
use App\Http\Controllers\Admin\Concerns\HandlesGroups;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Group;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\Tag;
use App\Services\AdminListCsvService;
use App\Services\QuizTagService;
use App\Support\AdminListCsvRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizController extends Controller
{
    use HandlesAdminCsv;
    use HandlesGroups;

    public function __construct(
        private QuizTagService $quizTagService,
        private AdminListCsvService $csvService,
    ) {}

    /** Các tham số khiến trang chuyển từ dạng nhóm sang bảng phẳng. */
    private const FILTER_KEYS = ['q', 'game_id', 'tag_id', 'group_id'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $grouped = $this->isGroupedView($request, self::FILTER_KEYS);

        $data = [
            'games' => Game::orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'groups' => $this->groupsForScope(Group::SCOPE_QUIZ),
            'filterGroupId' => $this->currentGroupFilter($request),
            'filterGameId' => $request->integer('game_id') ?: null,
            'filterTagId' => $request->filled('tag_id') ? $request->string('tag_id')->toString() : null,
            'search' => $search,
            'csvRegistry' => AdminListCsvRegistry::quiz(),
            'grouped' => $grouped,
        ];

        if ($grouped) {
            $data['recent'] = $this->baseQuery()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get();
            $data['sections'] = $this->groupSections(Quiz::class, Group::SCOPE_QUIZ);
            $data['quizzes'] = null;
        } else {
            $data['quizzes'] = $this->filteredQuery($request)->paginate(20)->withQueryString();
        }

        return view('admin.quizzes.index', $data);
    }

    /**
     * Nội dung một nhóm, tải dần khi người dùng mở nhóm hoặc bấm «Xem thêm».
     */
    public function groupRows(Request $request): JsonResponse
    {
        $page = $this->groupRowsPage($this->baseQuery(), $request);

        $rowData = [
            'games' => Game::orderBy('name')->get(),
            'groups' => $this->groupsForScope(Group::SCOPE_QUIZ),
        ];

        $html = '';
        foreach ($page['items'] as $quiz) {
            $html .= view('admin.quizzes._row', [...$rowData, 'quiz' => $quiz])->render();
        }

        return response()->json([
            'html' => $html,
            'has_more' => $page['has_more'],
            'next_offset' => $page['next_offset'],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $columns = $this->csvService->resolveExportColumns('quiz', $this->csvColumnKeys($request));
        $rows = $this->filteredQuery($request)->get();

        return $this->csvService->streamExport(
            'quiz',
            $rows,
            $columns,
            'quiz-'.now()->format('Ymd-His').'.csv',
        );
    }

    public function importTemplate(Request $request): StreamedResponse
    {
        $columns = $this->csvService->resolveTemplateColumns('quiz', $this->csvColumnKeys($request));

        return $this->csvService->streamTemplate(
            'quiz',
            $columns,
            'quiz-mau.csv',
        );
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $this->csvService->importQuiz(
            $request->file('csv_file'),
            $this->csvService->resolveTemplateColumns('quiz', $this->csvColumnKeys($request)),
        );

        return $this->csvImportResponse($result, 'admin.quizzes.index', 'quiz');
    }

    public function create(): View
    {
        return view('admin.quizzes.form', [
            'quiz' => null,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
            'bankTags' => Tag::query()->orderBy('name')->get(),
            'groups' => $this->groupsForScope(Group::SCOPE_QUIZ),
            'selectedGroupId' => old('group_id'),
            'selectedQuizTagIds' => old('tag_ids', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuiz($request);

        $quiz = Quiz::create([
            ...$validated,
            'group_id' => $this->resolveGroupId($request, Group::SCOPE_QUIZ),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'show_explanation' => $request->boolean('show_explanation'),
            'shuffle_options' => $request->boolean('shuffle_options'),
        ]);

        $this->quizTagService->syncFromIds($quiz, $request->input('tag_ids', []));

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã tạo quiz. Thêm câu hỏi bên dưới.');
    }

    public function show(Quiz $quiz): View
    {
        $quiz->load([
            'game',
            'group',
            'keyboard',
            'tags',
            'questions' => fn ($q) => $q->orderBy('sort_order')->with('sourceBankItem.tags'),
        ]);

        return view('admin.quizzes.show', [
            'quiz' => $quiz,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
            'bankTags' => Tag::query()->orderBy('name')->get(),
            'groups' => $this->groupsForScope(Group::SCOPE_QUIZ),
            'selectedGroupId' => old('group_id', $quiz->group_id),
            'selectedQuizTagIds' => old('tag_ids', $quiz->tags->pluck('id')->all()),
        ]);
    }

    public function edit(Quiz $quiz): RedirectResponse
    {
        return redirect()->route('admin.quizzes.show', $quiz);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $validated = $this->validateQuiz($request);

        $quiz->update([
            ...$validated,
            'group_id' => $this->resolveGroupId($request, Group::SCOPE_QUIZ),
            'sort_order' => $validated['sort_order'] ?? 0,
            'show_explanation' => $request->boolean('show_explanation'),
            'shuffle_options' => $request->boolean('shuffle_options'),
        ]);

        if ($request->has('is_active')) {
            $quiz->update(['is_active' => $request->boolean('is_active')]);
        }

        $this->quizTagService->syncFromIds($quiz, $request->input('tag_ids', []));

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã cập nhật quiz.');
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse|JsonResponse
    {
        $gameId = $quiz->game_id;

        // Xóa mềm — model tự chụp lại tên + id game vào deleted_game_* trước khi gỡ FK.
        $quiz->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Đã xóa quiz «'.$quiz->name.'».',
                'game' => [
                    'id' => $gameId,
                    'quiz_count' => $gameId ? Quiz::where('game_id', $gameId)->count() : 0,
                ],
            ]);
        }

        return redirect()->route('admin.quizzes.index')
            ->with('success', 'Đã xóa quiz.');
    }

    /**
     * Chuyển nhanh quiz sang game khác. Dùng cho cả modal quiz ở trang danh sách
     * game (AJAX, trả JSON) lẫn ô chọn nhanh ở cột "Game" trên trang danh sách quiz
     * (form thường, quay lại trang cũ kèm thông báo).
     */
    public function moveGame(Request $request, Quiz $quiz): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')],
        ]);

        $fromGameId = $quiz->game_id;
        $toGameId = $validated['game_id'] !== null ? (int) $validated['game_id'] : null;

        if ($fromGameId === $toGameId) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Quiz đã thuộc game này.'], 422);
            }

            return back();
        }

        $quiz->update(['game_id' => $toGameId]);

        if (! $request->wantsJson()) {
            return back()->with('success', 'Đã chuyển quiz «'.$quiz->name.'» sang game mới.');
        }

        return response()->json([
            'message' => 'Đã chuyển quiz «'.$quiz->name.'» sang game mới.',
            'from' => [
                'id' => $fromGameId,
                'quiz_count' => $fromGameId ? Quiz::where('game_id', $fromGameId)->count() : 0,
            ],
            'to' => [
                'id' => $toGameId,
                'quiz_count' => $toGameId ? Quiz::where('game_id', $toGameId)->count() : 0,
            ],
        ]);
    }

    /**
     * Chuyển nhanh quiz sang nhóm khác từ ô chọn ở cột "Nhóm" trên trang danh sách quiz.
     */
    public function moveGroup(Request $request, Quiz $quiz): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => [
                'nullable',
                Rule::exists('content_groups', 'id')->where('scope', Group::SCOPE_QUIZ),
            ],
        ]);

        $quiz->update(['group_id' => $validated['group_id'] ?? null]);

        return back()->with('success', 'Đã cập nhật nhóm cho quiz «'.$quiz->name.'».');
    }

    public function toggleActive(Quiz $quiz): RedirectResponse
    {
        $quiz->update(['is_active' => ! $quiz->is_active]);

        return back()->with('success', $quiz->is_active ? 'Đã bật quiz.' : 'Đã tắt quiz.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'keyboard_id' => ['required', 'integer', Rule::exists('keyboards', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'grade' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
            'show_explanation' => ['nullable'],
            'shuffle_options' => ['nullable'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            ...$this->groupValidationRules(Group::SCOPE_QUIZ),
        ]);
    }

    private function baseQuery(): Builder
    {
        return Quiz::query()
            ->with(['game', 'group', 'keyboard', 'tags'])
            ->withCount('questions')
            ->orderBy('sort_order');
    }

    private function filteredQuery(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyGroupFilter($query, $request);

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        if ($request->filled('tag_id')) {
            if ($request->string('tag_id')->toString() === 'none') {
                $query->whereDoesntHave('tags');
            } else {
                $tagId = $request->integer('tag_id');
                $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
            }
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query;
    }
}
