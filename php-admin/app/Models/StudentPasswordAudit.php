<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký thao tác trên mật khẩu học sinh. Tuyệt đối KHÔNG lưu plaintext hay
 * bản mã ở đây — chỉ ghi ai làm gì, với học sinh nào, lúc nào.
 */
class StudentPasswordAudit extends Model
{
    protected $fillable = [
        'student_id',
        'user_id',
        'action',
        'ip',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
