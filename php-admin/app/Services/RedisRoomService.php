<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Redis;

class RedisRoomService
{
    private const TTL_SECONDS = 7200;

    /**
     * @param  array<string, mixed>|null  $modeConfig
     */
    public function createWaitingRoom(
        string $pin,
        int $gameId,
        ?int $quizId = null,
        ?string $playModeSlug = null,
        ?array $modeConfig = null,
    ): void {
        $key = "room:{$pin}";
        $redis = Redis::connection('rooms');

        $redis->hSet($key, 'status', 'waiting');
        $redis->hSet($key, 'game_id', (string) $gameId);
        if ($quizId) {
            $redis->hSet($key, 'quiz_id', (string) $quizId);
        }
        if ($playModeSlug) {
            $redis->hSet($key, 'play_mode_slug', $playModeSlug);
        }
        if ($modeConfig) {
            $redis->hSet($key, 'mode_config', json_encode($modeConfig, JSON_UNESCAPED_UNICODE));
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

        $game = Game::with('playMode')->find($gameId);
        $playModeSlug = $game?->playMode?->slug ?? 'kahoot_sync';
        $modeConfig = $game?->resolvedModeConfig() ?? [];

        $this->createWaitingRoom($pin, $gameId, $quizId, $playModeSlug, $modeConfig);
    }

    /**
     * @return array{play_mode_slug: string, mode_config: array<string, mixed>}
     */
    public function resolvePlayModeForGame(int $gameId): array
    {
        $game = Game::with('playMode')->find($gameId);

        return [
            'play_mode_slug' => $game?->playMode?->slug ?? 'kahoot_sync',
            'mode_config' => $game?->resolvedModeConfig() ?? [],
        ];
    }
}
