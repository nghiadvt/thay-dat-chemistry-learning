<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $query = Quiz::query()->with(['game', 'keyboard'])->withCount('questions')->orderBy('sort_order');

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        return view('admin.quizzes.index', [
            'quizzes' => $query->get(),
            'games' => Game::orderBy('name')->get(),
            'filterGameId' => $request->integer('game_id') ?: null,
        ]);
    }

    public function create(): View
    {
        return view('admin.quizzes.form', [
            'quiz' => null,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
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

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã tạo quiz. Thêm câu hỏi bên dưới.');
    }

    public function show(Quiz $quiz): View
    {
        $quiz->load(['game', 'keyboard', 'questions' => fn ($q) => $q->orderBy('sort_order')]);

        return view('admin.quizzes.show', compact('quiz'));
    }

    public function edit(Quiz $quiz): View
    {
        return view('admin.quizzes.form', [
            'quiz' => $quiz,
            'games' => Game::orderBy('name')->get(),
            'keyboards' => Keyboard::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $validated = $this->validateQuiz($request);

        $quiz->update([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Đã cập nhật quiz.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();

        return redirect()->route('admin.quizzes.index')
            ->with('success', 'Đã xóa quiz và các câu hỏi liên quan.');
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
        ]);
    }
}
