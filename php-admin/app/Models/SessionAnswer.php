<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAnswer extends Model
{
    protected $fillable = [
        'session_id',
        'question_id',
        'student_name',
        'answer_submitted',
        'is_correct',
        'score_earned',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_submitted' => 'array',
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
