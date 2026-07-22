<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElementCategory;
use App\Models\PeriodicPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Quản lý nhóm nguyên tố (Kim loại kiềm, Kiềm thổ…) từ legend cạnh bảng.
 * Nhóm dùng chung mọi phiên bản — đổi màu/tên nên đánh dấu các phiên bản dirty.
 */
class ElementCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:32'],
            'deep_color' => ['required', 'string', 'max:32'],
        ]);

        $slug = $this->uniqueSlug($data['name']);
        $category = ElementCategory::create([
            'slug' => $slug,
            'name' => $data['name'],
            'color' => $data['color'],
            'deep_color' => $data['deep_color'],
            'sort_order' => (int) ElementCategory::max('sort_order') + 1,
        ]);
        $this->markAllPresetsDirty();

        return $this->jsonSuccess(['category' => $this->serialize($category)]);
    }

    public function update(Request $request, ElementCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:32'],
            'deep_color' => ['required', 'string', 'max:32'],
        ]);

        $category->update($data);
        $this->markAllPresetsDirty();

        return $this->jsonSuccess(['category' => $this->serialize($category)]);
    }

    public function destroy(ElementCategory $category): JsonResponse
    {
        // FK nullOnDelete: nguyên tố thuộc nhóm này sẽ về không-nhóm.
        $id = $category->id;
        $category->delete();
        $this->markAllPresetsDirty();

        return $this->jsonSuccess(['id' => $id]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'nhom';
        $slug = $base;
        $i = 2;
        while (ElementCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function markAllPresetsDirty(): void
    {
        PeriodicPreset::query()->update(['has_unpublished_changes' => true]);
    }

    /** @return array<string, mixed> */
    private function serialize(ElementCategory $c): array
    {
        return [
            'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name,
            'color' => $c->color, 'deep' => $c->deep_color,
        ];
    }
}
