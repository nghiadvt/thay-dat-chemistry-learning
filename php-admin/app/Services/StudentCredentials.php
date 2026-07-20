<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sinh mã code, tên đăng nhập và mật khẩu cho tài khoản học sinh.
 *
 * Bộ ký tự cố tình loại các cặp dễ nhìn nhầm khi giáo viên in phiếu phát cho
 * học sinh (O/0, l/1/I) và chỉ dùng ký tự đặc biệt quen thuộc, dễ gõ.
 */
class StudentCredentials
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const PASSWORD_LETTERS = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const PASSWORD_DIGITS = '23456789';

    private const PASSWORD_SPECIALS = '@#$%*';

    /**
     * Mã code bất biến, dùng làm ngữ cảnh khóa cho StudentPasswordCipher.
     * Tách khỏi username để giáo viên đổi tên đăng nhập không làm hỏng bản mã.
     */
    public function generateStudentCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = 'HS-'.$this->randomFrom(self::CODE_ALPHABET, 6);

            if (! Student::withTrashed()->where('student_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Không sinh được mã code học sinh duy nhất.');
    }

    /**
     * Sinh username dạng "{prefix}-01", "{prefix}-02"... và tự nhảy số khi trùng.
     */
    public function generateUsername(string $prefix, int $index): string
    {
        $base = Str::slug($prefix) ?: 'hs';
        $suffix = $index;

        for ($attempt = 0; $attempt < 500; $attempt++) {
            $username = $base.'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);

            if (! Student::withTrashed()->where('username', $username)->exists()) {
                return $username;
            }

            $suffix++;
        }

        throw new RuntimeException('Không sinh được tên đăng nhập duy nhất.');
    }

    /**
     * Mật khẩu ngẫu nhiên, đảm bảo luôn có ít nhất 1 chữ số và 1 ký tự đặc biệt.
     */
    public function generatePassword(int $length = 8): string
    {
        $length = max(6, min(8, $length));

        $chars = [
            $this->randomFrom(self::PASSWORD_DIGITS, 1),
            $this->randomFrom(self::PASSWORD_SPECIALS, 1),
        ];

        $pool = self::PASSWORD_LETTERS.self::PASSWORD_DIGITS.self::PASSWORD_SPECIALS;
        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $this->randomFrom($pool, 1);
        }

        // Xáo trộn bằng nguồn ngẫu nhiên an toàn thay vì shuffle() để vị trí của
        // chữ số / ký tự đặc biệt không đoán trước được.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private function randomFrom(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
