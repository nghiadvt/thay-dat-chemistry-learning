<?php

namespace App\Services;

use App\Exceptions\StudentPasswordCipherException;
use App\Models\Student;
use App\Models\StudentPasswordAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Điểm vào DUY NHẤT để thay đổi mật khẩu học sinh.
 *
 * Lý do tồn tại: `students.password` (bcrypt hash) là thứ quyết định đăng nhập,
 * còn `students.password_encrypted` chỉ để giáo viên xem lại. Nếu chỉ ghi một
 * trong hai, giáo viên sẽ nhìn thấy một mật khẩu mà học sinh không đăng nhập
 * được — sai lệch âm thầm, rất khó phát hiện. Mọi thay đổi vì vậy đều đi qua
 * đây và luôn ghi cả hai trong cùng một transaction.
 */
class StudentPasswordService
{
    public function __construct(
        private StudentPasswordCipher $cipher,
        private StudentCredentials $credentials,
    ) {}

    /**
     * Đặt mật khẩu cho học sinh: ghi đồng thời hash + bản mã.
     */
    public function apply(
        Student $student,
        string $plain,
        ?User $actor = null,
        string $action = 'apply',
        ?string $ip = null,
    ): void {
        // Mã hóa trước khi mở transaction: nếu APP_KEY hỏng thì hỏng ngay,
        // không để mật khẩu bị đổi một nửa.
        $encrypted = $this->cipher->encrypt($student->student_code, $plain);

        DB::transaction(function () use ($student, $plain, $encrypted, $actor, $action, $ip) {
            $student->forceFill([
                'password' => $plain, // cast 'hashed' sẽ băm khi lưu
                'password_encrypted' => $encrypted,
                'password_updated_at' => now(),
            ])->save();

            $this->audit($student, $actor, $action, $ip);
        });
    }

    /**
     * Sinh mật khẩu mới ngẫu nhiên rồi áp dụng. Trả về plaintext để hiển thị
     * một lần cho giáo viên.
     */
    public function reset(Student $student, ?User $actor = null, ?string $ip = null): string
    {
        $plain = $this->credentials->generatePassword();

        $this->apply($student, $plain, $actor, 'reset', $ip);

        return $plain;
    }

    /**
     * Giải mã mật khẩu đang lưu của học sinh (có ghi nhật ký).
     *
     * @throws StudentPasswordCipherException
     */
    public function reveal(Student $student, ?User $actor = null, ?string $ip = null): string
    {
        if (blank($student->password_encrypted)) {
            throw new StudentPasswordCipherException(
                'Học sinh này chưa có bản mã mật khẩu. Hãy dùng chức năng đặt lại mật khẩu.'
            );
        }

        $plain = $this->cipher->decrypt($student->student_code, $student->password_encrypted);

        $this->audit($student, $actor, 'decrypt', $ip);

        return $plain;
    }

    private function audit(Student $student, ?User $actor, string $action, ?string $ip): void
    {
        StudentPasswordAudit::create([
            'student_id' => $student->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'ip' => $ip,
        ]);
    }
}
