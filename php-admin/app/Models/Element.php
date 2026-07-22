<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Nguyên tố trong catalog gốc. Thông tin ở đây (tên/khối lượng/loại/sound)
 * dùng chung cho mọi phiên bản bảng.
 */
class Element extends Model
{
    protected $fillable = [
        'z',
        'symbol',
        'name_vi',
        'name_en',
        'mass',
        'category_id',
        'phonetic',
        'group_no',
        'period_no',
        'sound_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'mass' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ElementCategory::class, 'category_id');
    }

    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(PeriodicPreset::class, 'periodic_preset_element', 'element_id', 'preset_id')
            ->withPivot(['is_lit', 'is_visible', 'requires_pro', 'sort_override'])
            ->withTimestamps();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function soundUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->sound_path || ! Storage::disk('public')->exists($this->sound_path)) {
                return null;
            }

            return asset('storage/'.$this->sound_path);
        });
    }
}
