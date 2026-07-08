<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayMode extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'student_ui',
        'host_ui',
        'banner_path',
        'default_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function bannerUrl(): ?string
    {
        if (! $this->banner_path) {
            return null;
        }

        return asset('storage/'.$this->banner_path);
    }

    /**
     * Merge game-level overrides onto play mode defaults.
     *
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, mixed>
     */
    public function resolvedConfig(?array $overrides = null): array
    {
        $base = $this->default_config ?? [];

        if (empty($overrides)) {
            return $base;
        }

        return array_replace_recursive($base, $overrides);
    }
}
