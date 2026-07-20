<?php

namespace Tests\Unit;

use App\Services\StudentCredentials;
use PHPUnit\Framework\TestCase;

class StudentCredentialsTest extends TestCase
{
    private StudentCredentials $credentials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credentials = new StudentCredentials;
    }

    public function test_password_has_requested_length_within_bounds(): void
    {
        $this->assertSame(8, strlen($this->credentials->generatePassword()));
        $this->assertSame(6, strlen($this->credentials->generatePassword(6)));
        // Ngoài khoảng 6-8 thì bị kẹp lại.
        $this->assertSame(6, strlen($this->credentials->generatePassword(3)));
        $this->assertSame(8, strlen($this->credentials->generatePassword(30)));
    }

    public function test_password_always_contains_a_digit_and_a_common_special_character(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = $this->credentials->generatePassword();

            $this->assertMatchesRegularExpression('/[2-9]/', $password, 'Thiếu chữ số: '.$password);
            $this->assertMatchesRegularExpression('/[@#$%*]/', $password, 'Thiếu ký tự đặc biệt: '.$password);
        }
    }

    public function test_password_avoids_visually_ambiguous_characters(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = $this->credentials->generatePassword();

            // 0/O/o, 1/l/I dễ đọc nhầm khi giáo viên phát phiếu giấy cho học sinh.
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $password, 'Có ký tự dễ nhầm: '.$password);
        }
    }

    public function test_generated_passwords_are_not_all_identical(): void
    {
        $samples = [];
        for ($i = 0; $i < 20; $i++) {
            $samples[] = $this->credentials->generatePassword();
        }

        $this->assertGreaterThan(15, count(array_unique($samples)));
    }
}
