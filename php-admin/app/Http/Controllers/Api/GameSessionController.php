<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Services\PinGenerator;
use App\Services\RedisRoomService;
use App\Services\SessionQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GameSessionController extends Controller
{
    public function __construct(
        private PinGenerator $pinGenerator,
        private RedisRoomService $redisRoomService,
        private SessionQrService $sessionQrService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'quiz_id' => ['required_without:game_id', 'integer', Rule::exists('quizzes', 'id')->where('is_active', true)],
            'game_id' => ['required_without:quiz_id', 'integer', Rule::exists('games', 'id')],
        ]);

        if (! empty($validated['quiz_id'])) {
            $quiz = Quiz::findOrFail($validated['quiz_id']);

            if ($quiz->questions()->where('is_active', true)->count() === 0) {
                return $this->jsonError('Quiz chưa có câu hỏi active để tạo phòng.', 422);
            }

            $gameId = $quiz->game_id;
            $quizId = $quiz->id;
        } else {
            $game = \App\Models\Game::findOrFail($validated['game_id']);

            if ($game->quizzes()->where('is_active', true)->count() === 0) {
                return $this->jsonError('Game chưa có quiz active để tạo phòng.', 422);
            }

            $gameId = $game->id;
            $quizId = null;
        }

        $pin = $this->pinGenerator->generateUniquePin();

        $session = GameSession::create([
            'pin' => $pin,
            'name' => $validated['name'] ?? ('Phòng '.$pin),
            'host_id' => Auth::id(),
            'game_id' => $gameId,
            'quiz_id' => $quizId,
            'status' => 'waiting',
            'is_active' => true,
        ]);

        $this->redisRoomService->createWaitingRoom($pin, $gameId, $quizId);

        try {
            $this->sessionQrService->ensureQr($session);
            $session->refresh();
        } catch (\Throwable) {
            // Session vẫn hợp lệ nếu QR tạm thời lỗi
        }

        return $this->jsonSuccess([
            'session' => $session->load(['game', 'quiz']),
            'pin' => $pin,
            'qr_url' => $session->qr_url,
        ], 201);
    }

    public function show(GameSession $session): JsonResponse
    {
        $session->load(['game', 'quiz', 'host']);

        return $this->jsonSuccess(['session' => $session]);
    }

    public function reset(GameSession $session): JsonResponse
    {
        if ($session->status !== 'ended') {
            return $this->jsonError('Chỉ có thể chơi lại phòng đã kết thúc (ended).', 422);
        }

        if (! $session->is_active) {
            return $this->jsonError('Bật phòng trước khi chơi lại.', 422);
        }

        if (! $session->quiz_id) {
            return $this->jsonError('Phòng không gắn quiz — không thể reset.', 422);
        }

        $session->update([
            'status' => 'waiting',
            'started_at' => null,
            'ended_at' => null,
        ]);

        $this->redisRoomService->resetRoomForReplay($session->pin, $session->game_id, $session->quiz_id);

        return $this->jsonSuccess([
            'session' => $session->fresh(['game', 'quiz', 'host']),
            'message' => 'Đã reset phòng — sẵn sàng chơi lại với cùng PIN.',
        ]);
    }
}
