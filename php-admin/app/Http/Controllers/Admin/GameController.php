<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PlayMode;
use App\Support\DuckRaceAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(Request $request): View
    {
        $query = Game::query()
            ->with('playMode')
            ->withCount('quizzes')
            ->orderByDesc('updated_at');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($request->filled('play_mode_id')) {
            $query->where('play_mode_id', $request->integer('play_mode_id'));
        }

        $games = $query->paginate(12)->withQueryString();
        $playModes = PlayMode::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.games.index', compact('games', 'search', 'playModes'));
    }

    /**
     * Demo game "Đấu Trường Hóa Học" — dữ liệu giả lập bằng JS,
     * chưa có play mode / bảng riêng trong database.
     */
    public function battleDemo(): View
    {
        return view('admin.games.battle-demo');
    }

    /**
     * Demo game "Săn Rồng Hóa Học" — boss raid, dữ liệu giả lập bằng JS,
     * chưa có play mode / bảng riêng trong database.
     */
    public function dragonDemo(): View
    {
        return view('admin.games.dragon-demo');
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
            'track_start_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'track_end_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lane_top_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lane_bottom_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'duck_sprite_px' => ['nullable', 'integer', 'min:32', 'max:128'],
            'duck_swim_ms' => ['nullable', 'integer', 'min:400', 'max:3000'],
        ]);

        $mode = PlayMode::findOrFail($validated['play_mode_id']);
        $modeConfig = null;

        if ($mode->slug === 'duck_race') {
            $startPct = round((float) ($validated['track_start_pct'] ?? 20), 1);
            $endPct = round((float) ($validated['track_end_pct'] ?? 90), 1);
            if ($endPct - $startPct < 2) {
                $endPct = min(100, $startPct + 2);
            }

            $laneTopPct = round((float) ($validated['lane_top_pct'] ?? 50), 1);
            $laneBottomPct = round((float) ($validated['lane_bottom_pct'] ?? 92), 1);
            if ($laneBottomPct - $laneTopPct < 5) {
                $laneBottomPct = min(100, $laneTopPct + 5);
            }

            $targetScore = (int) ($validated['target_score'] ?? 30);
            $modeConfig = [
                'scoring' => [
                    'correct_delta' => (int) ($validated['correct_delta'] ?? 3),
                    'wrong_delta' => (int) ($validated['wrong_delta'] ?? -5),
                    'allow_negative' => true,
                ],
                'win' => [
                    'target_score' => $targetScore,
                    'podium_size' => (int) ($validated['podium_size'] ?? 3),
                ],
                'visual' => [
                    'track_steps' => $targetScore,
                    'track_bounds' => [
                        'start_pct' => $startPct,
                        'end_pct' => $endPct,
                    ],
                    'lane_bounds' => [
                        'top_pct' => $laneTopPct,
                        'bottom_pct' => $laneBottomPct,
                    ],
                    'duck_sprite_px' => (int) ($validated['duck_sprite_px'] ?? 64),
                    'duck_swim_ms' => (int) ($validated['duck_swim_ms'] ?? 1150),
                    'duck_sprites' => DuckRaceAssets::listSpritePaths(),
                ],
            ];
        }

        unset(
            $validated['correct_delta'],
            $validated['wrong_delta'],
            $validated['target_score'],
            $validated['podium_size'],
            $validated['track_start_pct'],
            $validated['track_end_pct'],
            $validated['lane_top_pct'],
            $validated['lane_bottom_pct'],
            $validated['duck_sprite_px'],
            $validated['duck_swim_ms'],
        );

        if ($modeConfig) {
            $validated['mode_config'] = $modeConfig;
        } else {
            $validated['mode_config'] = null;
        }

        return $validated;
    }
}
