<?php

namespace App\Services;

use App\Models\Game;

class GamePlayModeResolver
{
    public function __construct(
        private RedisRoomService $redisRoomService,
    ) {}

    /**
     * @return array{play_mode_slug: string, mode_config: array<string, mixed>}
     */
    public function forGameId(int $gameId): array
    {
        return $this->redisRoomService->resolvePlayModeForGame($gameId);
    }

    /**
     * @return array{play_mode_slug: string, mode_config: array<string, mixed>}
     */
    public function forGame(?Game $game): array
    {
        if (! $game) {
            return [
                'play_mode_slug' => 'kahoot_sync',
                'mode_config' => [],
            ];
        }

        $game->loadMissing('playMode');

        return [
            'play_mode_slug' => $game->playMode?->slug ?? 'kahoot_sync',
            'mode_config' => $game->resolvedModeConfig(),
        ];
    }
}
