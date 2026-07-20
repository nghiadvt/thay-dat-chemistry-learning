{{-- Một hàng câu hỏi trong bộ. Dùng cả ở bảng phẳng lẫn khi tải dần nội dung nhóm. --}}
<tr data-bank-item-id="{{ $item->id }}">
    <td class="admin-col-select" data-col="select">
        <input type="checkbox" class="qb-row-check" value="{{ $item->id }}" aria-label="Chọn câu hỏi">
    </td>
    <td data-col="type">@php
        echo match ($item->answer_type) {
            'mc' => 'Trắc nghiệm',
            'structured' => match ($item->input_mode) {
                'balance' => 'Cân bằng hệ số',
                'blank' => 'Điền chỗ thiếu',
                'blank_balance' => 'Cân bằng + điền',
                'product' => 'Điền sản phẩm',
                default => 'Phương trình',
            },
            default => 'Tự luận',
        };
    @endphp</td>
    <td data-col="group">
        @include('admin.partials.group-chip', [
            'group' => $item->group,
            'link' => $item->group ? route('admin.question-bank.index', ['group_id' => $item->group_id]) : null,
        ])
    </td>
    <td data-col="tags" class="qq-tag-cell">
        @include('admin.partials.question-tags-cell', [
            'tags' => $tags,
            'selectedTags' => $item->tags,
            'updateUrl' => route('admin.question-bank.update-tags', $item),
            'itemId' => $item->id,
        ])
    </td>
    <td data-col="content">{!! Str::limit(strip_tags($item->content), 100) !!}</td>
    <td data-col="points">{{ $item->points }}</td>
    <td data-col="time">{{ $item->time_limit_seconds }}s</td>
    <td data-col="actions" class="actions-cell">
        @include('admin.partials.row-action-menu', [
            'actions' => [
                ['key' => 'edit', 'label' => 'Sửa', 'href' => route('admin.question-bank.edit', $item)],
                ['key' => 'delete', 'label' => 'Xóa', 'danger' => true, 'href' => route('admin.question-bank.destroy', $item), 'method' => 'DELETE', 'confirm' => 'Xóa câu hỏi khỏi bộ? Câu hỏi trong quiz vẫn giữ nguyên.'],
            ],
            'dataAttrs' => ['item-label' => Str::limit(strip_tags($item->content), 40)],
        ])
    </td>
</tr>
