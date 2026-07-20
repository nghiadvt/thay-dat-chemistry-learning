<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class Student extends Authenticatable
{
    use HasFactory, SoftDeletes;

    /** Số lần nhập sai tối đa trước khi khóa tài khoản. */
    public const MAX_FAILED_ATTEMPTS = 5;

    protected $fillable = [
        'teacher_id',
        'class_id',
        'student_code',
        'username',
        'display_name',
        'password',
        'avatar_path',
        'status',
    ];

    protected $hidden = [
        'password',
        'password_encrypted',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_updated_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * student_code là ngữ cảnh dẫn xuất khóa của StudentPasswordCipher — đổi nó
     * sẽ làm mọi password_encrypted đã lưu không giải mã được nữa. Chặn ngay ở
     * tầng model thay vì chỉ dựa vào validate ở form.
     */
    public function setStudentCodeAttribute(?string $value): void
    {
        $current = $this->attributes['student_code'] ?? null;

        if ($this->exists && $current !== null && $current !== $value) {
            throw new LogicException('student_code là bất biến sau khi tạo học sinh.');
        }

        $this->attributes['student_code'] = $value;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function studentClass(): BelongsTo
    {
        return $this->belongsTo(StudentClass::class, 'class_id');
    }

    public function passwordAudits(): HasMany
    {
        return $this->hasMany(StudentPasswordAudit::class);
    }

    /**
     * Giáo viên chỉ thấy học sinh của mình; admin thấy tất cả.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('teacher_id', $user->id);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->display_name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= Str::upper(Str::substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
