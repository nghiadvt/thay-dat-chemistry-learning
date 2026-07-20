<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_bank_item_id',
        'position',
        'answer_index',
        'is_correct',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PracticeAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }

    /** Trạng thái ô trong lưới thống kê: dung | sai | chua-lam. */
    public function status(): string
    {
        if ($this->answer_index === null) {
            return 'chua-lam';
        }

        return $this->is_correct ? 'dung' : 'sai';
    }
}
