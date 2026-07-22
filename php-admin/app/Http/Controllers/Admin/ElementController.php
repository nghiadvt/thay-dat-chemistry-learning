<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Element;
use App\Models\PeriodicPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sửa dữ liệu GỐC của nguyên tố (tên/khối lượng/loại/sound). Đổi ở đây áp dụng
 * cho MỌI phiên bản — nên đánh dấu tất cả phiên bản là "có thay đổi chưa xuất bản".
 */
class ElementController extends Controller
{
    public function update(Request $request, Element $element): JsonResponse
    {
        $data = $request->validate([
            'name_vi' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'mass' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:element_categories,id'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $element->update($data);
        $this->markAllPresetsDirty();

        return $this->jsonSuccess(['element' => $this->serialize($element->fresh('category'))]);
    }

    public function uploadSound(Request $request, Element $element): JsonResponse
    {
        $request->validate([
            'sound' => ['required', 'file', 'max:5120', // 5MB
                'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm'],
        ]);

        $ext = $request->file('sound')->getClientOriginalExtension() ?: 'mp3';
        $path = 'elements/'.Str::slug($element->symbol.'-'.$element->z).'-'.now()->format('YmdHis').'.'.$ext;
        Storage::disk('public')->put($path, file_get_contents($request->file('sound')->getRealPath()));

        if ($element->sound_path && $element->sound_path !== $path) {
            Storage::disk('public')->delete($element->sound_path);
        }
        $element->update(['sound_path' => $path]);
        $this->markAllPresetsDirty();

        return $this->jsonSuccess(['sound_url' => $element->fresh()->sound_url]);
    }

    public function deleteSound(Element $element): JsonResponse
    {
        if ($element->sound_path) {
            Storage::disk('public')->delete($element->sound_path);
            $element->update(['sound_path' => null]);
            $this->markAllPresetsDirty();
        }

        return $this->jsonSuccess(['sound_url' => null]);
    }

    private function markAllPresetsDirty(): void
    {
        PeriodicPreset::query()->update(['has_unpublished_changes' => true]);
    }

    /** @return array<string, mixed> */
    private function serialize(Element $e): array
    {
        return [
            'id' => $e->id,
            'z' => (int) $e->z,
            'symbol' => $e->symbol,
            'name_vi' => $e->name_vi,
            'name_en' => $e->name_en,
            'mass' => (float) $e->mass,
            'category_id' => $e->category_id,
            'phonetic' => $e->phonetic,
            'sort_order' => (int) $e->sort_order,
            'sound_url' => $e->sound_url,
        ];
    }
}
