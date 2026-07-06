<?php

namespace App\Services;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><ul><ol><li><img><video><source>';

    public function sanitize(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        return preg_replace('/\s(on\w+|javascript:)=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    }
}
