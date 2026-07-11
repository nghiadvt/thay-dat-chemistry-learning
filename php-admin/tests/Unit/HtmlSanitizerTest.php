<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_keeps_plain_content_and_vietnamese_text(): void
    {
        $result = $this->sanitizer->sanitize('<p>Phản ứng: H2O + CO2</p>');

        $this->assertSame('<p>Phản ứng: H2O + CO2</p>', $result);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $result = $this->sanitizer->sanitize('<img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $result);
    }

    public function test_strips_javascript_href(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">click</a>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_removes_script_tag_and_its_content(): void
    {
        $result = $this->sanitizer->sanitize('<p>hello</p><script>alert(1)</script>');

        $this->assertSame('<p>hello</p>', $result);
    }

    public function test_removes_unknown_tag_without_dropping_safe_children(): void
    {
        $result = $this->sanitizer->sanitize('<svg onload="alert(1)"><b>bold</b></svg>');

        $this->assertStringNotContainsString('svg', $result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringContainsString('<b>bold</b>', $result);
    }

    public function test_strips_style_attribute(): void
    {
        $result = $this->sanitizer->sanitize('<div style="background:url(javascript:alert(1))">x</div>');

        $this->assertSame('<div>x</div>', $result);
    }

    public function test_allows_safe_image_data_uri(): void
    {
        $src = 'data:image/png;base64,iVBORw0KGgo=';
        $result = $this->sanitizer->sanitize('<img src="'.$src.'">');

        $this->assertStringContainsString($src, $result);
    }

    public function test_blocks_non_image_data_uri(): void
    {
        $result = $this->sanitizer->sanitize('<img src="data:text/html;base64,PHNjcmlwdD4=">');

        $this->assertStringNotContainsString('data:text/html', $result);
    }

    public function test_forces_safe_rel_on_links_with_target(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com" target="_blank">link</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }
}
