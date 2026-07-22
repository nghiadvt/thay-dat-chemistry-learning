<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CardTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'name',
        'sides',
        'frame_width_mm',
        'frame_height_mm',
        'front_baked_path',
        'back_baked_path',
        'layout',
    ];

    protected function casts(): array
    {
        return [
            'sides' => 'integer',
            'frame_width_mm' => 'decimal:2',
            'frame_height_mm' => 'decimal:2',
            'layout' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('teacher_id', $user->id);
    }

    public function getFrontBakedUrlAttribute(): ?string
    {
        return $this->storageUrl($this->front_baked_path);
    }

    public function getBackBakedUrlAttribute(): ?string
    {
        return $this->storageUrl($this->back_baked_path);
    }

    /**
     * Layout mặc định khi tạo template mới.
     *
     * @return array<string, mixed>
     */
    public static function defaultLayout(): array
    {
        return [
            'front' => [
                'imageLayers' => [],
                'elements' => [],
            ],
            'back' => [
                'imageLayers' => [],
                'elements' => [],
            ],
            'a4' => [
                'marginMm' => 8,
                'gapMm' => 4,
                'cardWidthMm' => 54,
            ],
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
