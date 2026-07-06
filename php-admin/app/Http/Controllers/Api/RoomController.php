<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class RoomController extends Controller
{
    public function show(string $pin): JsonResponse
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            return $this->jsonError('PIN phải là 6 chữ số.', 422);
        }

        $redis = Redis::connection('rooms');
        $key = "room:{$pin}";

        if (! $redis->exists($key)) {
            return $this->jsonError('PIN không hợp lệ hoặc phòng đã hết hạn.', 404);
        }

        $status = $redis->hGet($key, 'status') ?: 'waiting';
        $gameId = (int) ($redis->hGet($key, 'game_id') ?: 0);
        $session = GameSession::query()
            ->where('pin', $pin)
            ->with('game:id,name')
            ->first();

        return $this->jsonSuccess([
            'pin' => $pin,
            'status' => $status,
            'game_id' => $gameId,
            'game_name' => $session?->game?->name,
            'session_id' => $session?->id,
        ]);
    }
}
