<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameResult extends Model
{
    protected $fillable = [
        'session_id',
        'student_id',
        'student_name',
        'player_token',
        'score',
        'rank',
        'finish_rank',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'session_id');
    }

    /** Null với lượt chơi ẩn danh (vào phòng bằng PIN mà không đăng nhập). */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
