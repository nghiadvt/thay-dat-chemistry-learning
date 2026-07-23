<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PracticeTopic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'practice_grade_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(PracticeGrade::class, 'practice_grade_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(PracticeQuiz::class)->orderBy('sort_order');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function uniqueSlug(string $name, int $gradeId, ?self $ignore = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'chu-de-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        $slug = $base;
        $suffix = 2;
        while (
            static::query()
                ->where('practice_grade_id', $gradeId)
                ->where('slug', $slug)
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
