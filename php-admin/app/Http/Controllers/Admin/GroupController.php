<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $this->resolveScope($request->string('scope')->toString());

        $groups = Group::query()
            ->ofScope($scope)
            ->ordered()
            ->get();

        $counts = $groups->mapWithKeys(fn (Group $group) => [$group->id => $group->itemsCount()]);

        return view('admin.groups.index', [
            'scope' => $scope,
            'groups' => $groups,
            'counts' => $counts,
            'presetColors' => Group::PRESET_COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->resolveScope($request->string('scope')->toString());
        $validated = $this->validateGroup($request, $scope);

        Group::create([
            'scope' => $scope,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $scope),
            'color' => strtoupper($validated['color'] ?? Group::nextDefaultColor($scope)),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('admin.groups.index', ['scope' => $scope])
            ->with('success', 'Đã tạo nhóm.');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $validated = $this->validateGroup($request, $group->scope, $group);

        $group->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $group->scope, $group),
            'color' => strtoupper($validated['color'] ?? $group->color),
            'sort_order' => $validated['sort_order'] ?? $group->sort_order,
        ]);

        return redirect()
            ->route('admin.groups.index', ['scope' => $group->scope])
            ->with('success', 'Đã cập nhật nhóm.');
    }

    /**
     * Xóa nhóm. Các mục đang thuộc nhóm không bị xóa, chỉ trở về trạng thái chưa phân nhóm
     * (khóa ngoại đặt nullOnDelete).
     */
    public function destroy(Group $group): RedirectResponse
    {
        $scope = $group->scope;
        $count = $group->itemsCount();
        $group->delete();

        $message = $count > 0
            ? "Đã xóa nhóm. {$count} mục trong nhóm chuyển về «Chưa phân nhóm»."
            : 'Đã xóa nhóm.';

        return redirect()
            ->route('admin.groups.index', ['scope' => $scope])
            ->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGroup(Request $request, string $scope, ?Group $group = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('content_groups', 'name')
                    ->where('scope', $scope)
                    ->ignore($group?->id),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.unique' => 'Nhóm này đã tồn tại.',
        ]);
    }

    private function uniqueSlug(string $name, string $scope, ?Group $group = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'nhom-'.substr(md5(mb_strtolower($name)), 0, 12);
        }

        $slug = $base;
        $suffix = 2;
        while (
            Group::query()
                ->where('scope', $scope)
                ->where('slug', $slug)
                ->when($group, fn ($q) => $q->where('id', '!=', $group->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function resolveScope(string $scope): string
    {
        return array_key_exists($scope, Group::SCOPE_LABELS) ? $scope : Group::SCOPE_QUIZ;
    }
}
