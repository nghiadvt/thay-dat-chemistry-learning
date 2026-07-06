<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\PinGenerator;
use App\Services\RedisRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GameSessionController extends Controller
{
    public function __construct(
        private PinGenerator $pinGenerator,
        private RedisRoomService $redisRoomService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
        ]);

        $game = Game::findOrFail($validated['game_id']);

        if ($game->quizzes()->where('is_active', true)->count() === 0) {
            return $this->jsonError('Game chưa có quiz active để tạo phòng.', 422);
        }

        $pin = $this->pinGenerator->generateUniquePin();

        $session = GameSession::create([
            'pin' => $pin,
            'host_id' => Auth::id(),
            'game_id' => $game->id,
            'status' => 'waiting',
        ]);

        $this->redisRoomService->createWaitingRoom($pin, $game->id);

        return $this->jsonSuccess([
            'session' => $session->load('game'),
            'pin' => $pin,
        ], 201);
    }

    public function show(GameSession $session): JsonResponse
    {
        $session->load(['game', 'host']);

        return $this->jsonSuccess(['session' => $session]);
    }
}
