<?php

namespace Tests\Unit;

use App\Services\KeyboardValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\KeyboardTestConfig;

class KeyboardValidatorTest extends TestCase
{
    private KeyboardValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new KeyboardValidator;
    }

    public function test_accepts_zero_digit_key_text(): void
    {
        $config = $this->validator->normalizeConfig(KeyboardTestConfig::minimalValid());

        $issues = $this->validator->validate($config);

        $this->assertNotContains('Phím trống ở hàng "Numbers"', $issues);
        $this->assertSame([], $issues);
    }

    public function test_accepts_full_numbers_row_including_zero(): void
    {
        $config = $this->validator->normalizeConfig(KeyboardTestConfig::minimalValid());

        $digitTexts = array_map(
            fn (array $key) => $key['text'],
            array_filter(
                $config['rows'][0]['keys'],
                fn (array $key) => ($key['type'] ?? 'normal') === 'normal'
            )
        );

        $this->assertContains('0', $digitTexts);
        $this->assertSame([], $this->validator->validate($config));
    }

    public function test_rejects_empty_string_on_normal_key(): void
    {
        $config = KeyboardTestConfig::minimalValid();
        $config['rows'][0]['keys'][0]['text'] = '';

        $issues = $this->validator->validate($this->validator->normalizeConfig($config));

        $this->assertContains('Phím trống ở hàng "Numbers"', $issues);
    }

    public function test_rejects_null_text_on_normal_key(): void
    {
        $config = KeyboardTestConfig::minimalValid();
        $config['rows'][0]['keys'][0]['text'] = null;

        $issues = $this->validator->validate($this->validator->normalizeConfig($config));

        $this->assertContains('Phím trống ở hàng "Numbers"', $issues);
    }

    public function test_rejects_missing_send_key(): void
    {
        $config = KeyboardTestConfig::minimalValid();
        $config['rows'][2]['keys'] = [KeyboardTestConfig::spaceKey()];

        $issues = $this->validator->validate($this->validator->normalizeConfig($config));

        $this->assertContains('Thiếu phím Send', $issues);
    }

    public function test_rejects_row_exceeding_max_units(): void
    {
        $config = KeyboardTestConfig::minimalValid();
        $config['rows'][0]['keys'] = array_fill(0, 11, KeyboardTestConfig::normalKey('1'));

        $issues = $this->validator->validate($this->validator->normalizeConfig($config));

        $this->assertTrue(
            count(array_filter($issues, fn (string $issue) => str_contains($issue, 'vượt 10 units'))) > 0
        );
    }
}
