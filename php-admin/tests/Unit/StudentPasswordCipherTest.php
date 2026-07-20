<?php

namespace Tests\Unit;

use App\Exceptions\StudentPasswordCipherException;
use App\Services\StudentPasswordCipher;
use Tests\TestCase;

class StudentPasswordCipherTest extends TestCase
{
    private StudentPasswordCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cipher = new StudentPasswordCipher;
    }

    public function test_round_trip_returns_original_password(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $this->assertSame('Kh0a@2026', $this->cipher->decrypt('HS-ABC123', $payload));
    }

    public function test_wrong_student_code_fails_loudly_instead_of_returning_garbage(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->decrypt('HS-XYZ789', $payload);
    }

    public function test_tampered_ciphertext_is_rejected(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        // Đổi 1 ký tự trong phần base64 -> AEAD phải phát hiện.
        $tampered = substr($payload, 0, -2).(str_ends_with($payload, 'A=') ? 'B=' : 'A=');

        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->decrypt('HS-ABC123', $tampered);
    }

    public function test_same_password_encrypts_to_different_payloads_but_both_decrypt(): void
    {
        $first = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');
        $second = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $this->assertNotSame($first, $second, 'Nonce ngẫu nhiên phải cho ra bản mã khác nhau.');
        $this->assertSame('Kh0a@2026', $this->cipher->decrypt('HS-ABC123', $first));
        $this->assertSame('Kh0a@2026', $this->cipher->decrypt('HS-ABC123', $second));
    }

    public function test_handles_unicode_and_long_passwords(): void
    {
        $password = 'Mật khẩu dài của học sinh @#$%* 2026 — ăn Ắ Ệ ỹ';

        $payload = $this->cipher->encrypt('HS-ABC123', $password);

        $this->assertSame($password, $this->cipher->decrypt('HS-ABC123', $payload));
    }

    public function test_rejects_empty_inputs(): void
    {
        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->encrypt('HS-ABC123', '');
    }

    public function test_rejects_empty_student_code(): void
    {
        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->encrypt('', 'Kh0a@2026');
    }

    public function test_malformed_payload_is_rejected_cleanly(): void
    {
        foreach (['', 'khong-phai-ma-hoa', 'HSP1.@@@', 'HSP1.'.base64_encode('short')] as $bad) {
            try {
                $this->cipher->decrypt('HS-ABC123', $bad);
                $this->fail('Phải ném exception với payload: '.$bad);
            } catch (StudentPasswordCipherException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_changing_app_key_makes_payload_undecryptable(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        config(['app.key' => 'base64:'.base64_encode(hash('sha256', 'mot-khoa-khac', true))]);

        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->decrypt('HS-ABC123', $payload);
    }

    public function test_try_decrypt_returns_null_instead_of_throwing(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $this->assertNull($this->cipher->tryDecrypt('HS-SAI999', $payload));
        $this->assertSame('Kh0a@2026', $this->cipher->tryDecrypt('HS-ABC123', $payload));
    }

    public function test_scan_finds_the_owning_student_code(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $result = $this->cipher->scan($payload, ['HS-AAA111', 'HS-BBB222', 'HS-ABC123', 'HS-CCC333']);

        $this->assertSame('HS-ABC123', $result['code']);
        $this->assertSame('Kh0a@2026', $result['password']);
    }

    public function test_scan_returns_null_when_no_candidate_matches(): void
    {
        $payload = $this->cipher->encrypt('HS-ABC123', 'Kh0a@2026');

        $this->assertNull($this->cipher->scan($payload, ['HS-AAA111', 'HS-BBB222']));
    }

    public function test_scan_rejects_malformed_payload_rather_than_reporting_not_found(): void
    {
        $this->expectException(StudentPasswordCipherException::class);
        $this->cipher->scan('khong-phai-ma-hoa', ['HS-AAA111']);
    }
}
