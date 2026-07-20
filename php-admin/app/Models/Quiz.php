<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'game_id',
        'group_id',
        'keyboard_id',
        'name',
        'subject',
        'grade',
        'sort_order',
        'is_active',
        'show_explanation',
        'shuffle_options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_explanation' => 'boolean',
            'shuffle_options' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function keyboard(): BelongsTo
    {
        return $this->belongsTo(Keyboard::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'quiz_tag');
    }
}
