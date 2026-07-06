<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\Tag;
use App\Services\QuizTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function __construct(
        private QuizTagService $quizTagService,
    ) {}

    public function index(Request $request): View
    {
        $query = Quiz::query()
            ->with(['game', 'keyboard', 'tags'])
            ->withCount('questions')
            ->orderBy('sort_order');

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        if ($request->filled('tag_id')) {
            $tagId = $request->integer('tag_id');
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        return view('admin.quizzes.index', [
            'quizzes' => $query->get(),
            'games' => Game::orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'filterGameId' => $request->integer('game_id') ?: null,
            'filterTagId' => $request->integer('tag_id') ?: null,
        ]);
    }

    public function create(): View
    {
        return view('admin.quizzes.form', [
            'quiz' => null,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuiz($request);

        $quiz = Quiz::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->quizTagService->syncFromInput($quiz, $request->input('tags_input'));

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã tạo quiz. Thêm câu hỏi bên dưới.');
    }

    public function show(Quiz $quiz): View
    {
        $quiz->load(['game', 'keyboard', 'tags', 'questions' => fn ($q) => $q->orderBy('sort_order')]);

        return view('admin.quizzes.show', [
            'quiz' => $quiz,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
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
        ]);

        if ($request->has('is_active')) {
            $quiz->update(['is_active' => $request->boolean('is_active')]);
        }

        $this->quizTagService->syncFromInput($quiz, $request->input('tags_input'));

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
            'tags_input' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
