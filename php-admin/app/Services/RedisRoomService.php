<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Redis;

class RedisRoomService
{
    private const TTL_SECONDS = 7200;

    /**
     * Non-blocking key lookup (SCAN) — avoids blocking the whole Redis instance
     * that KEYS causes once the keyspace grows.
     *
     * @return list<string>
     */
    private function scanKeys($redis, string $pattern): array
    {
        $keys = [];
        $cursor = 0;

        do {
            [$cursor, $batch] = $redis->scan($cursor, ['match' => $pattern, 'count' => 1000]);
            $keys = array_merge($keys, $batch);
        } while ((string) $cursor !== '0');

        return array_values(array_unique($keys));
    }

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
        $this->purgeRoom($pin);
    }

    /**
     * Xóa toàn bộ state Redis của PIN (room, players, leaderboard, submitted, …).
     */
    public function purgeRoom(string $pin): void
    {
        $redis = Redis::connection('rooms');

        $roomKeys = $this->scanKeys($redis, "room:{$pin}*");
        if (! empty($roomKeys)) {
            $redis->del(...$roomKeys);
        }

        $redis->del("leaderboard:{$pin}");

        $submittedKeys = $this->scanKeys($redis, "submitted:{$pin}:*");
        if (! empty($submittedKeys)) {
            $redis->del(...$submittedKeys);
        }
    }

    /**
     * Đổi PIN: xóa Redis cũ, tạo phòng chờ mới với PIN mới.
     */
    public function migrateRoomPin(
        string $oldPin,
        string $newPin,
        int $gameId,
        ?int $quizId = null,
        ?string $playModeSlug = null,
        ?array $modeConfig = null,
    ): void {
        $this->purgeRoom($oldPin);
        $this->createWaitingRoom($newPin, $gameId, $quizId, $playModeSlug, $modeConfig);
    }

    /**
     * Xóa toàn bộ state Redis của PIN và tạo lại phòng chờ (cùng PIN, phiên chơi mới).
     * Kết quả cũ vẫn nằm trong MySQL (game_results, session_answers).
     */
    public function resetRoomForReplay(string $pin, int $gameId, ?int $quizId = null): void
    {
        $redis = Redis::connection('rooms');
        $keys = $this->scanKeys($redis, "room:{$pin}*");

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
