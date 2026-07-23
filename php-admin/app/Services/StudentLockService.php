<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentLockLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Điểm vào DUY NHẤT để khóa/mở khóa tài khoản học sinh.
 *
 * Mỗi lần khóa được ghi thành một StudentLockLog (thời điểm, IP, ai khóa) để
 * sau này phân biệt được giáo viên chủ động khóa hay hệ thống tự khóa do nhập
 * sai mật khẩu quá số lần cho phép — và hiển thị đúng lý do cho học sinh khi
 * họ cố đăng nhập lại.
 */
class StudentLockService
{
    public function lock(Student $student, ?User $actor, ?string $ip, bool $byTeacher): void
    {
        DB::transaction(function () use ($student, $actor, $ip, $byTeacher) {
            $student->forceFill([
                'status' => 'locked',
                'locked_at' => now(),
            ])->save();

            StudentLockLog::create([
                'student_id' => $student->id,
                'locked_at' => now(),
                'ip_address' => $ip,
                'locked_by_teacher' => $byTeacher,
                'locked_by_user_id' => $byTeacher ? $actor?->id : null,
            ]);
        });
    }

    public function unlock(Student $student, ?string $ip): void
    {
        DB::transaction(function () use ($student, $ip) {
            $student->forceFill([
                'status' => 'active',
                'failed_attempts' => 0,
                'locked_at' => null,
            ])->save();

            $student->lockLogs()
                ->whereNull('unlocked_at')
                ->latest('locked_at')
                ->first()
                ?->forceFill(['unlocked_at' => now()])
                ->save();
        });
    }
}
