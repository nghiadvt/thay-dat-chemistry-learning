<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyboard extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}
