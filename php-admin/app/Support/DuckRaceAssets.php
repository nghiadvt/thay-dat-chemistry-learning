<?php

namespace App\Support;

use App\Models\DuckSprite;

class DuckRaceAssets
{
    public static function spritesDirectory(): string
    {
        return public_path('htd-admin/assets/duck-race/ducks');
    }

    /**
     * @return list<string> paths relative to duck-race/, e.g. ducks/duck-blue.gif
     */
    public static function listSpritePaths(): array
    {
        $dir = self::spritesDirectory();
        if (! is_dir($dir)) {
            return ['ducks/duck-blue.gif'];
        }

        $files = array_merge(
            glob($dir.'/*.gif') ?: [],
            glob($dir.'/*.png') ?: [],
            glob($dir.'/*.webp') ?: [],
        );
        sort($files);

        $paths = array_values(array_map(
            static fn (string $path) => 'ducks/'.basename($path),
            $files,
        ));

        return $paths !== [] ? $paths : ['ducks/duck-blue.gif'];
    }

    public static function defaultSpriteUrl(): string
    {
        return asset('htd-admin/assets/duck-race/ducks/duck-blue.gif');
    }

    /**
     * Danh sách vịt cho mode_config.visual.duck_sprites. Ưu tiên vịt chuyển động
     * (frame-animation) quản lý trong DB — mỗi vịt là 1 token "db:{id}" mà client
     * tự resolve thành bộ frame qua GET /api/duck-sprites/public. Nếu chưa có vịt
     * nào trong DB thì fallback về GIF tĩnh cũ.
     *
     * @return list<string>
     */
    public static function listSpriteTokens(): array
    {
        $ids = DuckSprite::query()->orderBy('name')->pluck('id');

        if ($ids->isNotEmpty()) {
            return $ids->map(static fn (int $id) => "db:{$id}")->values()->all();
        }

        return self::listSpritePaths();
    }
}
