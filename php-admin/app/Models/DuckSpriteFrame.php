<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuckSpriteFrame extends Model
{
    protected $fillable = [
        'duck_sprite_id',
        'path',
        'position',
    ];

    protected $appends = [
        'url',
    ];

    public function duckSprite(): BelongsTo
    {
        return $this->belongsTo(DuckSprite::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => asset('storage/'.$this->path));
    }
}
