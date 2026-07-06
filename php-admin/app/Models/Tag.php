<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_tag');
    }

    public static function findOrCreateFromName(string $name): self
    {
        $trimmed = trim($name);
        $slug = Str::slug($trimmed);
        if ($slug === '') {
            $slug = 'tag-'.substr(md5(mb_strtolower($trimmed)), 0, 12);
        }

        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => $trimmed]
        );
    }
}
