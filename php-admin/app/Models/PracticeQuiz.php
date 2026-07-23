<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PracticeQuiz extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'practice_topic_id',
        'name',
        'sort_order',
        'is_active',
        'requires_pro',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_pro' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PracticeTopic::class, 'practice_topic_id');
    }

    public function questionBankItems(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBankItem::class, 'practice_quiz_question_bank_item')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function studentClasses(): BelongsToMany
    {
        return $this->belongsToMany(StudentClass::class, 'practice_quiz_student_class');
    }
}
