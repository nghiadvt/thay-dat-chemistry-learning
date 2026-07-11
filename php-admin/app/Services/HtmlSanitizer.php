<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => allowed attributes (empty = none) */
    private const ALLOWED_TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'h5' => [], 'h6' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height'],
        'video' => ['src', 'controls', 'width', 'height', 'poster'],
        'source' => ['src', 'type'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'oembed' => ['url'],
        'span' => [], 'div' => [],
    ];

    /** Tags whose entire subtree (including text) must be dropped, not just unwrapped. */
    private const STRIP_SUBTREE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'button', 'input', 'noscript'];

    private const URL_ATTRS = ['href', 'src', 'poster'];

    private const SAFE_URL_PATTERN = '/^(https?:\/\/|\/|#|mailto:)/i';

    private const SAFE_IMAGE_DATA_PATTERN = '/^data:image\/(png|jpe?g|gif|webp);base64,/i';

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__htd_sanitize_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__htd_sanitize_root__');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $inner = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! isset(self::ALLOWED_TAGS[$tag])) {
                if (in_array($tag, self::STRIP_SUBTREE, true)) {
                    $node->removeChild($child);
                } else {
                    // Unknown but harmless tag (e.g. copy-pasted <font>, <center>) —
                    // drop the wrapper, keep its already-cleaned children.
                    $this->cleanChildren($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
                continue;
            }

            $this->cleanAttributes($child, $tag);
            $this->cleanChildren($child);
        }
    }

    private function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_TAGS[$tag];
        $names = [];
        foreach ($el->attributes as $attr) {
            $names[] = $attr->name;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (! in_array($lower, $allowed, true)) {
                $el->removeAttribute($name);
                continue;
            }

            if (in_array($lower, self::URL_ATTRS, true) && ! $this->isSafeUrl($el->getAttribute($name), $lower)) {
                $el->removeAttribute($name);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('target')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(string $value, string $attr): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($attr === 'src' && preg_match(self::SAFE_IMAGE_DATA_PATTERN, $value)) {
            return true;
        }

        return (bool) preg_match(self::SAFE_URL_PATTERN, $value);
    }
}
