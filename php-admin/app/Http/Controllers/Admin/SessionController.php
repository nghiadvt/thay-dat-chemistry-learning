<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\PinGenerator;
use App\Services\RedisRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(
        private PinGenerator $pinGenerator,
        private RedisRoomService $redisRoomService,
    ) {}

    public function create(): View
    {
        $games = Game::query()
            ->withCount(['quizzes as active_quizzes_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $recentSessions = GameSession::query()
            ->with(['game:id,name', 'host:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.sessions.create', compact('games', 'recentSessions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
        ]);

        $game = Game::findOrFail($validated['game_id']);

        if ($game->quizzes()->where('is_active', true)->count() === 0) {
            return back()->with('error', 'Game chưa có quiz active. Thêm quiz trước khi tạo phòng.');
        }

        $pin = $this->pinGenerator->generateUniquePin();

        $session = GameSession::create([
            'pin' => $pin,
            'host_id' => Auth::id(),
            'game_id' => $game->id,
            'status' => 'waiting',
        ]);

        $this->redisRoomService->createWaitingRoom($pin, $game->id);

        return redirect()->route('admin.sessions.show', $session)
            ->with('success', 'Đã tạo phòng. Chia sẻ PIN cho học sinh.');
    }

    public function show(GameSession $session): View
    {
        $session->load(['game', 'host']);

        $joinUrl = url('/join/'.$session->pin);

        return view('admin.sessions.show', compact('session', 'joinUrl'));
    }
}
