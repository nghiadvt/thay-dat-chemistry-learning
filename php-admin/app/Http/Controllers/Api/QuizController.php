<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Quiz::query()->with(['game', 'keyboard'])->orderBy('sort_order');

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        return $this->jsonSuccess(['quizzes' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'keyboard_id' => ['required', 'integer', Rule::exists('keyboards', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'grade' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! Game::whereKey($validated['game_id'])->exists()) {
            return $this->jsonError('game_id không tồn tại.', 422);
        }

        if (! Keyboard::whereKey($validated['keyboard_id'])->exists()) {
            return $this->jsonError('keyboard_id không tồn tại.', 422);
        }

        $quiz = Quiz::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $quiz->load(['game', 'keyboard']);

        return $this->jsonSuccess(['quiz' => $quiz], 201);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz->load(['game', 'keyboard', 'questions']);

        return $this->jsonSuccess(['quiz' => $quiz]);
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['sometimes', 'required', 'integer', Rule::exists('games', 'id')],
            'keyboard_id' => ['sometimes', 'required', 'integer', Rule::exists('keyboards', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'grade' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $quiz->update($validated);

        return $this->jsonSuccess(['quiz' => $quiz->fresh()->load(['game', 'keyboard'])]);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $quiz->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }
}
