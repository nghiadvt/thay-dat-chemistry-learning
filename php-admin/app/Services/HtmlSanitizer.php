<?php

namespace App\Services;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><video><source><figure><figcaption><table><thead><tbody><tr><th><td><blockquote><pre><code><oembed><span><div>';

    public function sanitize(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        return preg_replace('/\s(on\w+|javascript:)=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    }
}
