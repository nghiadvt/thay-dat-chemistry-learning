<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisRoomService
{
    private const TTL_SECONDS = 7200;

    public function createWaitingRoom(string $pin, int $gameId, ?int $quizId = null): void
    {
        $key = "room:{$pin}";
        $redis = Redis::connection('rooms');

        $redis->hSet($key, 'status', 'waiting');
        $redis->hSet($key, 'game_id', (string) $gameId);
        if ($quizId) {
            $redis->hSet($key, 'quiz_id', (string) $quizId);
        }
        $redis->expire($key, self::TTL_SECONDS);
    }

    public function destroyRoom(string $pin): void
    {
        Redis::connection('rooms')->del("room:{$pin}");
    }

    /**
     * Xóa toàn bộ state Redis của PIN và tạo lại phòng chờ (cùng PIN, phiên chơi mới).
     * Kết quả cũ vẫn nằm trong MySQL (game_results, session_answers).
     */
    public function resetRoomForReplay(string $pin, int $gameId, ?int $quizId = null): void
    {
        $redis = Redis::connection('rooms');
        $keys = $redis->keys("room:{$pin}*");

        if (! empty($keys)) {
            $redis->del(...$keys);
        }

        $this->createWaitingRoom($pin, $gameId, $quizId);
    }
}
