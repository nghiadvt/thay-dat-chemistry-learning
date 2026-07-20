<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần giáo viên cấp quyền cho học sinh (hoặc cả lớp) trên một tính năng.
 */
class StudentEntitlement extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'feature_key',
        'access_level',
        'scope',
        'granted_by',
        'starts_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentClass(): BelongsTo
    {
        return $this->belongsTo(StudentClass::class, 'class_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Còn hiệu lực: chưa bị thu hồi, đã tới ngày bắt đầu, chưa quá hạn.
     */
    public function scopeEffective(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    public function isEffective(): bool
    {
        $now = now();

        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->lte($now)) {
            return false;
        }

        return true;
    }

    /** Số ngày còn lại; null nếu vĩnh viễn. */
    public function daysRemaining(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInHours($this->expires_at, false) / 24));
    }
}
