<?php

namespace App\Services;

use App\Exceptions\StudentPasswordCipherException;

/**
 * Mã hóa 2 chiều mật khẩu học sinh để giáo viên xem lại được.
 *
 * Thiết kế:
 *  - Khóa thật nằm ở APP_KEY. Mỗi học sinh dùng một khóa dẫn xuất riêng bằng
 *    HKDF-SHA256 với info = "student-password:{student_code}". Rò rỉ DB mà
 *    không có APP_KEY thì bản mã vô dụng.
 *  - AES-256-GCM (AEAD) với nonce ngẫu nhiên, student_code đưa vào AAD. Nhờ vậy
 *    sai mã code hoặc bản mã bị sửa sẽ FAIL rõ ràng chứ không trả về rác —
 *    chính tính chất này làm cho chức năng "dò mã code" hoạt động tin cậy.
 *  - Nonce ngẫu nhiên => mã hóa cùng một mật khẩu 2 lần cho ra 2 bản mã khác
 *    nhau. Đây là hành vi đúng và không ảnh hưởng tới nghiệp vụ.
 *
 * LƯU Ý VẬN HÀNH: đổi hoặc mất APP_KEY = mất toàn bộ mật khẩu đã mã hóa
 * (bản hash để đăng nhập vẫn còn, chỉ mất khả năng xem lại).
 */
class StudentPasswordCipher
{
    private const PREFIX = 'HSP1.';

    private const CIPHER = 'aes-256-gcm';

    private const KEY_INFO_PREFIX = 'student-password:';

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    public function encrypt(string $studentCode, string $plain): string
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '') {
            throw StudentPasswordCipherException::emptyStudentCode();
        }

        if ($plain === '') {
            throw StudentPasswordCipherException::emptyPlaintext();
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            $this->deriveKey($studentCode),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $studentCode,
            self::TAG_BYTES
        );

        if ($ciphertext === false) {
            throw StudentPasswordCipherException::decryptionFailed();
        }

        return self::PREFIX.base64_encode($nonce.$tag.$ciphertext);
    }

    /**
     * @throws StudentPasswordCipherException khi mã code sai hoặc bản mã hỏng.
     */
    public function decrypt(string $studentCode, string $payload): string
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '') {
            throw StudentPasswordCipherException::emptyStudentCode();
        }

        [$nonce, $tag, $ciphertext] = $this->parse($payload);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->deriveKey($studentCode),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $studentCode
        );

        if ($plain === false) {
            throw StudentPasswordCipherException::decryptionFailed();
        }

        return $plain;
    }

    /**
     * Giải mã "mềm": trả về null thay vì ném exception. Dùng cho chức năng dò
     * mã code, nơi việc thất bại là kết quả bình thường của phép thử.
     */
    public function tryDecrypt(string $studentCode, string $payload): ?string
    {
        try {
            return $this->decrypt($studentCode, $payload);
        } catch (StudentPasswordCipherException) {
            return null;
        }
    }

    /**
     * Dò xem bản mã thuộc về mã code nào trong danh sách ứng viên.
     *
     * Đây là phương án thay thế khả thi cho việc "suy ra mã code từ password +
     * bản mã" — vốn bất khả thi về mặt mật mã học. Vì AEAD chỉ giải mã thành
     * công với đúng mã code, quét qua tập học sinh của giáo viên là đủ.
     *
     * @param  iterable<string>  $candidateCodes
     * @return array{code: string, password: string}|null
     */
    public function scan(string $payload, iterable $candidateCodes): ?array
    {
        // Kiểm tra định dạng một lần trước khi quét để không nuốt mất lỗi
        // "bản mã sai định dạng" thành "không tìm thấy học sinh".
        $this->parse($payload);

        foreach ($candidateCodes as $code) {
            $plain = $this->tryDecrypt((string) $code, $payload);

            if ($plain !== null) {
                return ['code' => (string) $code, 'password' => $plain];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [nonce, tag, ciphertext]
     */
    private function parse(string $payload): array
    {
        $payload = trim($payload);

        if (! str_starts_with($payload, self::PREFIX)) {
            throw StudentPasswordCipherException::malformedPayload();
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw StudentPasswordCipherException::malformedPayload();
        }

        return [
            substr($raw, 0, self::NONCE_BYTES),
            substr($raw, self::NONCE_BYTES, self::TAG_BYTES),
            substr($raw, self::NONCE_BYTES + self::TAG_BYTES),
        ];
    }

    private function deriveKey(string $studentCode): string
    {
        $appKey = (string) config('app.key');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false) {
                throw StudentPasswordCipherException::missingAppKey();
            }

            $appKey = $decoded;
        }

        if ($appKey === '') {
            throw StudentPasswordCipherException::missingAppKey();
        }

        return hash_hkdf('sha256', $appKey, 32, self::KEY_INFO_PREFIX.$studentCode);
    }
}
