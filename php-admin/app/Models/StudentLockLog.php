<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLockLog extends Model
{
    protected $fillable = [
        'student_id',
        'locked_at',
        'unlocked_at',
        'ip_address',
        'locked_by_teacher',
        'locked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'locked_by_teacher' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }
}
