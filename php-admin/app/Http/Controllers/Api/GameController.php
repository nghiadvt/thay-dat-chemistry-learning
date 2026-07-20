<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::query()
            ->withCount('quizzes')
            ->orderByDesc('updated_at')
            ->get();

        return $this->jsonSuccess(['games' => $games]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $game = Game::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return $this->jsonSuccess(['game' => $game], 201);
    }

    public function show(Game $game): JsonResponse
    {
        $game->load(['quizzes.keyboard', 'creator']);

        return $this->jsonSuccess(['game' => $game]);
    }

    public function update(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $game->update($validated);

        return $this->jsonSuccess(['game' => $game->fresh()]);
    }

    public function destroy(Game $game): JsonResponse
    {
        // RESTRICT on quizzes.game_id — block delete while quizzes still exist.
        if ($game->quizzes()->exists()) {
            return $this->jsonError(
                'Không thể xóa game còn quiz. Xóa hoặc chuyển quiz trước.',
                409
            );
        }

        // Chỉ chặn phòng đang chờ / đang chơi; phòng đã kết thúc giữ lại làm
        // lịch sử kèm snapshot tên game (xem Game::booted).
        if ($game->sessions()->where('status', '!=', 'ended')->exists()) {
            return $this->jsonError(
                'Không thể xóa game khi còn phòng đang chờ hoặc đang chơi. Kết thúc các phòng đó trước.',
                409
            );
        }

        $game->delete();

        return $this->jsonSuccess(['deleted' => true]);
    }
}
