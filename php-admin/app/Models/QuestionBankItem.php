<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBankItem extends Model
{
    protected $fillable = [
        'content',
        'explanation',
        'answer_type',
        'options',
        'correct_index',
        'correct_answer_normalized',
        'input_mode',
        'template',
        'correct_answer',
        'time_limit_seconds',
        'points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'template' => 'array',
            'correct_answer' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_bank_tag');
    }

    public function quizCopies(): HasMany
    {
        return $this->hasMany(Question::class, 'source_bank_question_id');
    }
}
