<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisRoomService
{
    private const TTL_SECONDS = 7200;

    public function createWaitingRoom(string $pin, int $gameId): void
    {
        $key = "room:{$pin}";
        $redis = Redis::connection('rooms');

        $redis->hSet($key, 'status', 'waiting');
        $redis->hSet($key, 'game_id', (string) $gameId);
        $redis->expire($key, self::TTL_SECONDS);
    }
}
