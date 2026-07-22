<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * Một phiên bản bảng tuần hoàn. Mô hình Nháp/Xuất bản:
 *   - Cấu hình NHÁP = quan hệ elements() (pivot). Sửa thoải mái, không tới học sinh.
 *   - Học sinh chỉ đọc published_snapshot của phiên bản is_live (đóng băng khi Xuất bản).
 */
class PeriodicPreset extends Model
{
    protected $fillable = [
        'name',
        'is_live',
        'published_snapshot',
        'published_at',
        'has_unpublished_changes',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'has_unpublished_changes' => 'boolean',
            'published_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function elements(): BelongsToMany
    {
        return $this->belongsToMany(Element::class, 'periodic_preset_element', 'preset_id', 'element_id')
            ->withPivot(['is_lit', 'is_visible', 'requires_pro', 'sort_override'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Dựng snapshot JSON học sinh sẽ đọc: danh mục nhóm + các nguyên tố (kèm cấu
     * hình theo phiên bản). Chỉ nguyên tố is_visible mới được đưa ra cho học sinh.
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(): array
    {
        $categories = ElementCategory::orderBy('sort_order')->get()
            ->mapWithKeys(fn (ElementCategory $c) => [
                $c->slug => ['label' => $c->name, 'color' => $c->color, 'deep' => $c->deep_color],
            ])->all();

        $elements = $this->elements()
            ->with('category')
            ->wherePivot('is_visible', true)
            ->get()
            ->map(function (Element $el) {
                $order = $el->pivot->sort_override ?? $el->sort_order;

                return [
                    'z' => (int) $el->z,
                    'symbol' => $el->symbol,
                    'nameVi' => $el->name_vi,
                    'nameEn' => $el->name_en,
                    'mass' => (float) $el->mass,
                    'category' => $el->category?->slug,
                    'phonetic' => $el->phonetic,
                    'soundUrl' => $el->sound_url,
                    'group' => (int) $el->group_no,
                    'period' => (int) $el->period_no,
                    'order' => (int) $order,
                    'isLit' => (bool) $el->pivot->is_lit,
                    'requiresPro' => (bool) $el->pivot->requires_pro,
                ];
            })
            ->sortBy('order')
            ->values()
            ->all();

        return ['categories' => $categories, 'elements' => $elements];
    }

    /**
     * Xuất bản phiên bản này: đóng băng snapshot, đặt làm bản live duy nhất.
     */
    public function publish(): void
    {
        DB::transaction(function () {
            static::where('id', '!=', $this->id)->where('is_live', true)
                ->update(['is_live' => false]);

            $this->forceFill([
                'published_snapshot' => $this->buildSnapshot(),
                'published_at' => now(),
                'is_live' => true,
                'has_unpublished_changes' => false,
            ])->save();
        });
    }

    public function markDirty(): void
    {
        if (! $this->has_unpublished_changes) {
            $this->forceFill(['has_unpublished_changes' => true])->save();
        }
    }
}
