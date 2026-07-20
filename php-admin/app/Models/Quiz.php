<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use SoftDeletes;

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

    protected static function booted(): void
    {
        // Xóa mềm: chụp lại tên + id game rồi gỡ FK, để game vẫn xóa/đổi tên được
        // mà quiz đã xóa vẫn biết mình từng thuộc game nào.
        static::deleting(function (self $quiz): void {
            if ($quiz->isForceDeleting()) {
                return;
            }

            $quiz->forceFill([
                'deleted_game_id' => $quiz->game_id,
                'deleted_game_name' => $quiz->game()->value('name'),
                'game_id' => null,
            ])->saveQuietly();
        });
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
