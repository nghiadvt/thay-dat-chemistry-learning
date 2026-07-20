<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi mã hóa/giải mã mật khẩu học sinh.
 *
 * Thông điệp của exception này được hiển thị thẳng cho giáo viên nên tuyệt đối
 * không được chứa plaintext, bản mã hay khóa.
 */
class StudentPasswordCipherException extends RuntimeException
{
    public static function missingAppKey(): self
    {
        return new self('APP_KEY chưa được cấu hình nên không thể mã hóa mật khẩu học sinh.');
    }

    public static function malformedPayload(): self
    {
        return new self('Chuỗi mã hóa không đúng định dạng.');
    }

    public static function decryptionFailed(): self
    {
        return new self('Không giải mã được: mã code học sinh không khớp hoặc chuỗi mã hóa đã bị sửa.');
    }

    public static function emptyPlaintext(): self
    {
        return new self('Mật khẩu cần mã hóa không được để trống.');
    }

    public static function emptyStudentCode(): self
    {
        return new self('Mã code học sinh không được để trống.');
    }
}
