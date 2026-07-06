<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'quiz_id',
        'content',
        'answer_type',
        'options',
        'correct_index',
        'correct_answer_normalized',
        'input_mode',
        'template',
        'correct_answer',
        'time_limit_seconds',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'template' => 'array',
            'correct_answer' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function sessionAnswers(): HasMany
    {
        return $this->hasMany(SessionAnswer::class);
    }
}
