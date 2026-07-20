<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lượt học sinh tự luyện (không qua phòng live của giáo viên).
 */
class PracticeAttempt extends Model
{
    protected $fillable = [
        'student_id',
        'feature_key',
        'label',
        'topic_slug',
        'grade_slug',
        'total_questions',
        'correct_count',
        'score',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PracticeAttemptAnswer::class, 'attempt_id')->orderBy('position');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** Tỉ lệ đúng theo phần trăm, dùng để tô màu viền record ở màn thống kê. */
    public function accuracyPercent(): int
    {
        if ($this->total_questions <= 0) {
            return 0;
        }

        return (int) round($this->correct_count / $this->total_questions * 100);
    }

    /**
     * Xếp loại theo tỉ lệ đúng — 3 mức dùng cho màu viền:
     * duoi-kha (<65%), kha (65–79%), gioi (>=80%).
     */
    public function grade(): string
    {
        $percent = $this->accuracyPercent();

        return match (true) {
            $percent >= 80 => 'gioi',
            $percent >= 65 => 'kha',
            default => 'duoi-kha',
        };
    }
}
