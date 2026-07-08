<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class GameSession extends Model
{
    protected $fillable = [
        'pin',
        'qr_path',
        'name',
        'host_id',
        'game_id',
        'quiz_id',
        'status',
        'is_active',
        'started_at',
        'ended_at',
    ];

    protected $appends = [
        'qr_url',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function qrUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->qr_path || ! Storage::disk('public')->exists($this->qr_path)) {
                return null;
            }

            return asset('storage/'.$this->qr_path);
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class, 'session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SessionAnswer::class, 'session_id');
    }
}
