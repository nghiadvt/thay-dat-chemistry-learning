<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PracticeGrade extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(PracticeTopic::class)->orderBy('sort_order');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function uniqueSlug(string $name, ?self $ignore = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'khoi-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        $slug = $base;
        $suffix = 2;
        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
