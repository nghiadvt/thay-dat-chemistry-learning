<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dùng chung cho các trang có phân nhóm (quiz, bộ câu hỏi, phòng chơi).
 * Mỗi trang có danh sách nhóm riêng, phân biệt bằng scope.
 */
trait HandlesGroups
{
    /**
     * Quy tắc validate cho ô chọn nhóm trong form tạo/sửa.
     *
     * @return array<string, mixed>
     */
    protected function groupValidationRules(string $scope): array
    {
        return [
            'group_id' => [
                'nullable',
                Rule::exists('content_groups', 'id')->where('scope', $scope),
            ],
            'new_group_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Xác định nhóm từ request: ưu tiên tên nhóm mới nhập tay, nếu không thì lấy nhóm đã chọn.
     */
    protected function resolveGroupId(Request $request, string $scope): ?int
    {
        $newName = trim((string) $request->input('new_group_name', ''));
        if ($newName !== '') {
            return Group::findOrCreateFromName($newName, $scope)->id;
        }

        $groupId = $request->input('group_id');

        return ($groupId === null || $groupId === '') ? null : (int) $groupId;
    }

    /**
     * Áp bộ lọc nhóm vào query. `group_id=none` lọc các mục chưa phân nhóm.
     */
    protected function applyGroupFilter(Builder $query, Request $request): Builder
    {
        if (! $request->filled('group_id')) {
            return $query;
        }

        if ($request->string('group_id')->toString() === 'none') {
            return $query->whereNull('group_id');
        }

        return $query->where('group_id', $request->integer('group_id'));
    }

    /**
     * Giá trị bộ lọc nhóm hiện tại để view render lại (chuỗi id hoặc 'none').
     */
    protected function currentGroupFilter(Request $request): ?string
    {
        return $request->filled('group_id') ? $request->string('group_id')->toString() : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Group>
     */
    protected function groupsForScope(string $scope)
    {
        return Group::query()->ofScope($scope)->ordered()->get();
    }

    /** Số record hiển thị ở mục «Thao tác gần đây». */
    protected const RECENT_LIMIT = 8;

    /** Số record tải mỗi lần khi mở một nhóm. */
    protected const GROUP_PAGE_SIZE = 20;

    /**
     * Trang chỉ hiện dạng nhóm khi người dùng chưa tìm kiếm/lọc gì.
     * Khi đang lọc thì quay về bảng phẳng để thấy kết quả ngay.
     *
     * @param  list<string>  $filterKeys
     */
    protected function isGroupedView(Request $request, array $filterKeys): bool
    {
        foreach ($filterKeys as $key) {
            $value = $request->input($key);
            if ($value !== null && $value !== '' && $value !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Danh sách các mục gập/mở: từng nhóm của scope, cộng «Khác» cho record chưa phân nhóm.
     * Chỉ trả về số đếm — nội dung từng nhóm tải sau khi người dùng bấm mở.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return list<array{key: string, label: string, count: int, color: ?string, text_color: ?string}>
     */
    protected function groupSections(string $modelClass, string $scope): array
    {
        $counts = $modelClass::query()
            ->select('group_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('group_id')
            ->pluck('aggregate', 'group_id');

        $sections = [];

        foreach ($this->groupsForScope($scope) as $group) {
            $sections[] = [
                'key' => (string) $group->id,
                'label' => $group->name,
                'count' => (int) ($counts[$group->id] ?? 0),
                'color' => $group->color,
                'text_color' => $group->text_color,
            ];
        }

        $ungrouped = (int) ($counts[''] ?? $counts[null] ?? 0);
        if ($ungrouped > 0) {
            $sections[] = [
                'key' => 'none',
                'label' => 'Khác',
                'count' => $ungrouped,
                'color' => null,
                'text_color' => null,
            ];
        }

        return $sections;
    }

    /**
     * Lấy một "trang" record bên trong nhóm, sắp xếp theo lần thao tác gần nhất.
     * Dùng cho endpoint tải dần khi mở nhóm hoặc bấm «Xem thêm».
     *
     * @return array{items: \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>, has_more: bool, next_offset: int}
     */
    protected function groupRowsPage(Builder $query, Request $request, string $recencyColumn = 'updated_at'): array
    {
        $groupKey = (string) $request->input('group_id', '');
        if ($groupKey === 'none') {
            $query->whereNull('group_id');
        } else {
            $query->where('group_id', (int) $groupKey);
        }

        $offset = max(0, $request->integer('offset'));
        $limit = self::GROUP_PAGE_SIZE;

        // Lấy dư 1 bản ghi để biết còn dữ liệu phía sau hay không, khỏi phải count() riêng.
        $items = $query
            ->reorder()
            ->orderByDesc($recencyColumn)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $items->count() > $limit;

        return [
            'items' => $hasMore ? $items->take($limit) : $items,
            'has_more' => $hasMore,
            'next_offset' => $offset + $limit,
        ];
    }
}
