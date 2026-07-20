<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DuckSprite extends Model
{
    protected $fillable = [
        'name',
        'fps',
        'folder',
    ];

    protected function casts(): array
    {
        return [
            'fps' => 'integer',
        ];
    }

    public function frames(): HasMany
    {
        return $this->hasMany(DuckSpriteFrame::class)->orderBy('position');
    }

    /**
     * @return array<string, mixed>
     */
    public function toManagerPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fps' => $this->fps,
            'frames' => $this->frames->map(fn (DuckSpriteFrame $frame) => [
                'id' => $frame->id,
                'position' => $frame->position,
                'url' => $frame->url,
            ])->values()->all(),
        ];
    }
}
