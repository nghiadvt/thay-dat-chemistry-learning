<?php

namespace App\Support;

class AssetVersion
{
    /**
     * URL asset public kèm ?v={filemtime} để cache-bust theo nội dung file.
     * Dùng qua Blade directive @vasset('css/admin.css').
     */
    public static function url(string $path): string
    {
        $abs = public_path(ltrim($path, '/'));
        $version = is_file($abs) ? (string) filemtime($abs) : (string) time();

        return asset($path).'?v='.$version;
    }
}
