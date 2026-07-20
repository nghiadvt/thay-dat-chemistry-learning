<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Chứng minh danh tính học sinh cho ws-server khi vào phòng chơi.
 *
 * Vì sao cần: ws-server là tiến trình Node riêng, không đọc được session của
 * Laravel. Nếu để student app tự gửi student_id thì học sinh sửa được payload và
 * gán lượt chơi của mình cho bạn khác — thống kê của giáo viên thành vô nghĩa.
 *
 * Cách làm: Laravel (nơi đã xác thực phiên) sinh một token ngẫu nhiên, ghi vào
 * Redis dùng chung với TTL ngắn. ws-server chỉ việc tra Redis để lấy student_id.
 * Token không đoán được và tự hết hạn nên không cần thêm khóa bí mật chung.
 *
 * Hợp đồng khóa Redis: `student_play_token:<token>` -> student_id
 * (kết nối 'rooms' đã cấu hình prefix rỗng để ws-server đọc đúng khóa).
 */
class StudentPlayToken
{
    public const KEY_PREFIX = 'student_play_token:';

    public const TTL_SECONDS = 120;

    public function issue(Student $student): string
    {
        $token = Str::random(48);

        $this->redis()->setex(self::KEY_PREFIX.$token, self::TTL_SECONDS, (string) $student->id);

        return $token;
    }

    /** Đọc student_id từ token; null nếu token sai hoặc đã hết hạn. */
    public function resolve(string $token): ?int
    {
        $value = $this->redis()->get(self::KEY_PREFIX.$token);

        return $value === null || $value === false ? null : (int) $value;
    }

    private function redis()
    {
        return Redis::connection('rooms');
    }
}
