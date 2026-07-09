<?php

namespace App\Support;

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
}
