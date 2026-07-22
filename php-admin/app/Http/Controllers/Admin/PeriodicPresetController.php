<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Element;
use App\Models\ElementCategory;
use App\Models\PeriodicPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodicPresetController extends Controller
{
    /** Trang phiên bản: card + thumbnail (mode như HS thường) + modal phóng to + menu 3 chấm. */
    public function index()
    {
        $presets = PeriodicPreset::orderByDesc('is_live')->orderByDesc('updated_at')->get();

        $categories = ElementCategory::orderBy('sort_order')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'color' => $c->color,
                'deep' => $c->deep_color,
            ])->values()->all();

        // Catalog dùng chung cho thumbnail + modal phóng to (mode «như HS thường»).
        $catalog = Element::orderBy('sort_order')->get()
            ->map(fn (Element $e) => [
                'id' => $e->id,
                'g' => (int) $e->group_no,
                'p' => (int) $e->period_no,
                'z' => (int) $e->z,
                'sym' => $e->symbol,
                'name_vi' => $e->name_vi,
                'mass' => $e->mass !== null ? (float) $e->mass : null,
                'cat' => $e->category_id,
            ])->all();

        // Cấu hình nháp mỗi phiên bản → trạng thái từng ô cho thumbnail.
        $states = [];
        foreach ($presets as $preset) {
            $states[$preset->id] = $preset->elements()
                ->get()
                ->mapWithKeys(fn (Element $e) => [$e->id => [
                    'lit' => (bool) $e->pivot->is_lit,
                    'vis' => (bool) $e->pivot->is_visible,
                    'pro' => (bool) $e->pivot->requires_pro,
                ]])->all();
        }

        return view('admin.periodic.index', compact('presets', 'categories', 'catalog', 'states'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $preset = PeriodicPreset::create([
            'name' => $data['name'],
            'created_by' => $request->user()?->id,
            'has_unpublished_changes' => true,
        ]);

        // Đính kèm toàn bộ catalog với cấu hình mặc định (hiện + sáng, không pro).
        $attach = Element::pluck('id')->mapWithKeys(fn ($id) => [
            $id => ['is_lit' => true, 'is_visible' => true, 'requires_pro' => false, 'sort_override' => null],
        ])->all();
        $preset->elements()->sync($attach);

        return redirect()
            ->route('admin.periodic.edit', $preset)
            ->with('success', 'Đã tạo phiên bản mới. Cấu hình rồi bấm Xuất bản để học sinh thấy.');
    }

    /** Workspace hợp nhất: bảng tương tác + popover + thao tác hàng loạt + xuất bản. */
    public function edit(PeriodicPreset $periodic)
    {
        $categories = ElementCategory::orderBy('sort_order')->get();

        $config = $periodic->elements()->get()
            ->mapWithKeys(fn (Element $e) => [$e->id => [
                'is_lit' => (bool) $e->pivot->is_lit,
                'is_visible' => (bool) $e->pivot->is_visible,
                'requires_pro' => (bool) $e->pivot->requires_pro,
                'sort_override' => $e->pivot->sort_override,
            ]])->all();

        $catalog = Element::orderBy('sort_order')->get()->map(fn (Element $e) => [
            'id' => $e->id,
            'z' => (int) $e->z,
            'symbol' => $e->symbol,
            'name_vi' => $e->name_vi,
            'name_en' => $e->name_en,
            'mass' => (float) $e->mass,
            'category_id' => $e->category_id,
            'phonetic' => $e->phonetic,
            'group_no' => (int) $e->group_no,
            'period_no' => (int) $e->period_no,
            'sound_url' => $e->sound_url,
            'sort_order' => (int) $e->sort_order,
        ])->all();

        $boot = [
            'preset' => [
                'id' => $periodic->id,
                'name' => $periodic->name,
                'is_live' => $periodic->is_live,
                'has_unpublished_changes' => $periodic->has_unpublished_changes,
            ],
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name,
                'color' => $c->color, 'deep' => $c->deep_color,
            ])->all(),
            'elements' => $catalog,
            'config' => $config,
            'urls' => [
                'saveConfig' => route('admin.periodic.config', $periodic),
                'publish' => route('admin.periodic.publish', $periodic),
                'element' => route('admin.elements.update', ['element' => 'ELID']),
                'elementSound' => route('admin.elements.sound', ['element' => 'ELID']),
                'categoryStore' => route('admin.element-categories.store'),
                'categoryUpdate' => route('admin.element-categories.update', ['category' => 'CATID']),
                'categoryDestroy' => route('admin.element-categories.destroy', ['category' => 'CATID']),
            ],
        ];

        return view('admin.periodic.edit', ['preset' => $periodic, 'boot' => $boot]);
    }

    /** Lưu cấu hình NHÁP (bulk). Không tới học sinh cho tới khi Xuất bản. */
    public function saveConfig(Request $request, PeriodicPreset $periodic): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'elements' => ['required', 'array'],
            'elements.*.id' => ['required', 'integer', 'exists:elements,id'],
            'elements.*.is_lit' => ['required', 'boolean'],
            'elements.*.is_visible' => ['required', 'boolean'],
            'elements.*.requires_pro' => ['required', 'boolean'],
            'elements.*.sort_override' => ['nullable', 'integer', 'min:0'],
        ]);

        $sync = [];
        foreach ($data['elements'] as $row) {
            $sync[$row['id']] = [
                'is_lit' => $row['is_lit'],
                'is_visible' => $row['is_visible'],
                'requires_pro' => $row['requires_pro'],
                'sort_override' => $row['sort_override'] ?? null,
            ];
        }

        DB::transaction(function () use ($periodic, $sync, $data) {
            $periodic->elements()->sync($sync);
            if (array_key_exists('name', $data)) {
                $periodic->name = $data['name'];
            }
            $periodic->has_unpublished_changes = true;
            $periodic->save();
        });

        return $this->jsonSuccess(['has_unpublished_changes' => true]);
    }

    /** Xuất bản: đóng băng snapshot, đặt làm bản live duy nhất. */
    public function publish(PeriodicPreset $periodic): RedirectResponse
    {
        $periodic->publish();

        return back()->with('success', 'Đã xuất bản «'.$periodic->name.'». Học sinh sẽ thấy phiên bản này.');
    }

    public function duplicate(PeriodicPreset $periodic): RedirectResponse
    {
        $copy = $periodic->replicate(['is_live', 'published_snapshot', 'published_at']);
        $copy->name = $periodic->name.' (bản sao)';
        $copy->is_live = false;
        $copy->published_snapshot = null;
        $copy->published_at = null;
        $copy->has_unpublished_changes = true;
        $copy->save();

        $rows = $periodic->elements()->get()->mapWithKeys(fn (Element $e) => [$e->id => [
            'is_lit' => (bool) $e->pivot->is_lit,
            'is_visible' => (bool) $e->pivot->is_visible,
            'requires_pro' => (bool) $e->pivot->requires_pro,
            'sort_override' => $e->pivot->sort_override,
        ]])->all();
        $copy->elements()->sync($rows);

        return redirect()->route('admin.periodic.edit', $copy)->with('success', 'Đã nhân bản phiên bản.');
    }

    public function destroy(PeriodicPreset $periodic): RedirectResponse
    {
        if ($periodic->is_live) {
            return back()->with('error', 'Không thể xóa phiên bản đang chiếu cho học sinh. Hãy xuất bản phiên bản khác trước.');
        }

        $periodic->delete();

        return redirect()->route('admin.periodic.index')->with('success', 'Đã xóa phiên bản.');
    }
}
