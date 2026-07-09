<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminCsv;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\Tag;
use App\Services\AdminListCsvService;
use App\Services\QuizTagService;
use App\Support\AdminListCsvRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizController extends Controller
{
    use HandlesAdminCsv;

    public function __construct(
        private QuizTagService $quizTagService,
        private AdminListCsvService $csvService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $quizzes = $this->filteredQuery($request)->paginate(20)->withQueryString();

        return view('admin.quizzes.index', [
            'quizzes' => $quizzes,
            'games' => Game::orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'filterGameId' => $request->integer('game_id') ?: null,
            'filterTagId' => $request->filled('tag_id') ? $request->string('tag_id')->toString() : null,
            'search' => $search,
            'csvRegistry' => AdminListCsvRegistry::quiz(),
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
            'selectedQuizTagIds' => old('tag_ids', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuiz($request);

        $quiz = Quiz::create([
            ...$validated,
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
            'keyboard',
            'tags',
            'questions' => fn ($q) => $q->orderBy('sort_order')->with('sourceBankItem.tags'),
        ]);

        return view('admin.quizzes.show', [
            'quiz' => $quiz,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
            'bankTags' => Tag::query()->orderBy('name')->get(),
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

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();

        return redirect()->route('admin.quizzes.index')
            ->with('success', 'Đã xóa quiz và các câu hỏi liên quan.');
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
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $query = Quiz::query()
            ->with(['game', 'keyboard', 'tags'])
            ->withCount('questions')
            ->orderBy('sort_order');

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
