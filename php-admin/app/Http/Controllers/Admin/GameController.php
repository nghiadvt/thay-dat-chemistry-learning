<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PlayMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $games = Game::query()
            ->with('playMode')
            ->withCount('quizzes')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.games.index', compact('games'));
    }

    public function create(): View
    {
        return view('admin.games.form', [
            'game' => null,
            'playModes' => PlayMode::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGame($request);

        Game::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã tạo game.');
    }

    public function edit(Game $game): View
    {
        return view('admin.games.form', [
            'game' => $game,
            'playModes' => PlayMode::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $validated = $this->validateGame($request);

        $game->update($validated);

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã cập nhật game.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        if ($game->quizzes()->exists()) {
            return back()->with('error', 'Không thể xóa game còn quiz. Xóa hoặc chuyển quiz trước.');
        }

        $game->delete();

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã xóa game.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGame(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'play_mode_id' => ['required', 'integer', Rule::exists('play_modes', 'id')->where('is_active', true)],
            'correct_delta' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'wrong_delta' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'target_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'podium_size' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $mode = PlayMode::findOrFail($validated['play_mode_id']);
        $modeConfig = null;

        if ($mode->slug === 'duck_race') {
            $modeConfig = [
                'scoring' => [
                    'correct_delta' => (int) ($validated['correct_delta'] ?? 3),
                    'wrong_delta' => (int) ($validated['wrong_delta'] ?? -5),
                    'allow_negative' => true,
                ],
                'win' => [
                    'target_score' => (int) ($validated['target_score'] ?? 30),
                    'podium_size' => (int) ($validated['podium_size'] ?? 3),
                ],
            ];
        }

        unset($validated['correct_delta'], $validated['wrong_delta'], $validated['target_score'], $validated['podium_size']);

        if ($modeConfig) {
            $validated['mode_config'] = $modeConfig;
        } else {
            $validated['mode_config'] = null;
        }

        return $validated;
    }
}
