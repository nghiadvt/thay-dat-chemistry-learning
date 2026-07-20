@extends('layouts.admin')

@section('title', 'Bộ câu hỏi — Hóa Thầy Đạt')
@section('page-title', 'Bộ câu hỏi')

@php
    $pickerColumns = [
        ['key' => 'type', 'label' => 'Loại'],
        ['key' => 'group', 'label' => 'Nhóm'],
        ['key' => 'tags', 'label' => 'Chủ đề'],
        ['key' => 'content', 'label' => 'Nội dung'],
        ['key' => 'points', 'label' => 'Điểm'],
        ['key' => 'time', 'label' => 'Thời gian'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $searchValue = trim((string) ($filterQuery ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = ($filterTagIds ?? []) !== [] || ($filterTagNone ?? false) || ($filterTagMatch ?? 'and') !== 'and' || $filterAnswerType || $hasSearch || ($filterGroupId ?? null);
    $activeFilterCount = count($filterTagIds ?? []) + (($filterTagNone ?? false) ? 1 : 0) + (($filterTagMatch ?? 'and') !== 'and' ? 1 : 0) + ($filterAnswerType ? 1 : 0) + (($filterGroupId ?? null) ? 1 : 0);
    $typeLabels = ['mc' => 'Trắc nghiệm', 'essay' => 'Tự luận', 'structured' => 'Phương trình'];
    $filterChips = [];
    if ($filterGroupId ?? null) {
        $groupLabel = $filterGroupId === 'none' ? 'Chưa phân nhóm' : ($groups->firstWhere('id', (int) $filterGroupId)?->name ?? 'Nhóm #'.$filterGroupId);
        $filterChips[] = ['label' => 'Nhóm: '.$groupLabel, 'url' => route('admin.question-bank.index', request()->except(['group_id', 'page']))];
    }
    foreach ($filterTagIds ?? [] as $tagId) {
        $tagName = $tags->firstWhere('id', $tagId)?->name ?? '#'.$tagId;
        $remove = request()->except(['page']);
        $remove['tag_ids'] = array_values(array_diff($filterTagIds, [$tagId]));
        if ($remove['tag_ids'] === []) unset($remove['tag_ids']);
        $filterChips[] = ['label' => 'Chủ đề: '.$tagName, 'url' => route('admin.question-bank.index', $remove)];
    }
    if ($filterTagNone ?? false) {
        $filterChips[] = ['label' => 'Chưa có chủ đề', 'url' => route('admin.question-bank.index', request()->except(['tag_none', 'page']))];
    }
    if (($filterTagMatch ?? 'and') !== 'and') {
        $filterChips[] = ['label' => 'Khớp: HOẶC', 'url' => route('admin.question-bank.index', array_merge(request()->except(['page']), ['tag_match' => 'and']))];
    }
    if ($filterAnswerType) {
        $filterChips[] = ['label' => 'Loại: '.($typeLabels[$filterAnswerType] ?? $filterAnswerType), 'url' => route('admin.question-bank.index', request()->except(['answer_type', 'page']))];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Bộ câu hỏi</h2>
        @if ($grouped)
            <p class="page-header__meta">{{ collect($sections)->sum('count') }} câu hỏi trong {{ count($sections) }} nhóm</p>
        @elseif (!$items->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $items->total() }} câu hỏi{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <div class="page-header__actions">
        <a href="{{ route('admin.groups.index', ['scope' => 'question_bank']) }}" class="btn btn-secondary">Quản lý nhóm</a>
        <a href="{{ route('admin.question-bank.create') }}" class="btn btn-primary">+ Thêm câu hỏi</a>
    </div>
</div>

<div class="card admin-list-card" id="qbListCard" data-bulk-tags-url="{{ route('admin.question-bank.bulk-tags') }}">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'qbSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm trong nội dung câu hỏi…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', ['panelId' => 'qbFilterPanel', 'activeCount' => $activeFilterCount])
            @include('admin.partials.csv-exchange', [
                'tableId' => 'question-bank-list',
                'exportUrl' => route('admin.question-bank.export-csv'),
                'templateUrl' => route('admin.question-bank.import-template'),
                'importUrl' => route('admin.question-bank.import-csv'),
                'registry' => $csvRegistry,
                'preserveQuery' => request()->except(['page']),
            ])
            @include('admin.partials.table-column-picker', ['tableId' => 'question-bank-list', 'columns' => $pickerColumns])
        </div>
    </div>

    <div id="qbFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    @include('admin.partials.group-select', [
                        'mode' => 'filter',
                        'id' => 'qbGroupFilter',
                        'groups' => $groups,
                        'selected' => $filterGroupId ?? '',
                    ])
                </div>
                <div class="form-group list-filters-panel__wide">
                    <label>Chủ đề</label>
                    @include('admin.partials.tag-select', [
                        'mode' => 'filter-multi',
                        'tags' => $tags,
                        'selected' => $filterTagIds ?? [],
                        'tagNone' => $filterTagNone ?? false,
                        'tagMatch' => $filterTagMatch ?? 'and',
                        'autoSubmit' => false,
                        'showAll' => true,
                        'showUntagged' => true,
                    ])
                </div>
                <div class="form-group">
                    <label for="answer_type">Loại câu</label>
                    <select id="answer_type" name="answer_type" class="list-filter-control">
                        <option value="">Tất cả loại</option>
                        @foreach ($typeLabels as $val => $label)
                            <option value="{{ $val }}" @selected($filterAnswerType === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.question-bank.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.question-bank.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if (! $grouped && $items->isEmpty())
        <div class="empty-state">
            Chưa có câu hỏi trong bộ.
            <a href="{{ route('admin.question-bank.create') }}">Thêm câu hỏi đầu tiên</a>
        </div>
    @else
        <div id="qbBulkBar" class="qq-bulk-bar qq-bulk-bar--idle">
            <span class="qq-bulk-count"><strong id="qbBulkCount">0</strong> câu đã chọn</span>
            <div class="qq-bulk-actions">
                <button type="button" class="btn btn-secondary btn-sm" data-qb-bulk-action="tags" disabled>Đổi chủ đề</button>
            </div>
        </div>
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table {{ $grouped ? 'admin-grouped-table' : '' }}" data-table-id="question-bank-list">
                <colgroup>
                    <col data-col="select">
                    @foreach ($pickerColumns as $column)
                        <col data-col="{{ $column['key'] }}">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th class="admin-col-select" data-col="select">
                            <input type="checkbox" id="qbSelectAll" aria-label="Chọn tất cả">
                        </th>
                        @foreach ($pickerColumns as $column)
                            <th data-col="{{ $column['key'] }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                @if ($grouped)
                    @include('admin.partials.list-group-sections', [
                        'sections' => $sections,
                        'recent' => $recent,
                        'rowView' => 'admin.question-bank._row',
                        'rowVar' => 'item',
                        'rowExtra' => ['tags' => $tags],
                        'rowsUrl' => route('admin.question-bank.group-rows'),
                        'colspan' => count($pickerColumns) + 1,
                        'emptyText' => 'Nhóm này chưa có câu hỏi nào.',
                    ])
                @else
                    <tbody>
                        @foreach ($items as $item)
                            @include('admin.question-bank._row', ['item' => $item, 'tags' => $tags])
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
        @unless ($grouped)
            @include('admin.partials.list-table-footer', ['paginator' => $items, 'itemLabel' => 'câu hỏi'])
        @endunless
    @endif
</div>

<div id="qbBulkModal" class="qq-modal" hidden aria-hidden="true">
    <div class="qq-modal-backdrop" data-close-qb-bulk-modal></div>
    <div class="qq-modal-dialog qq-bulk-modal" role="dialog" aria-labelledby="qbBulkModalTitle">
        <header class="qq-modal-header">
            <h3 id="qbBulkModalTitle">Đổi chủ đề hàng loạt</h3>
            <button type="button" class="qq-modal-close" data-close-qb-bulk-modal aria-label="Đóng">×</button>
        </header>
        <div class="qq-modal-body">
            <div class="form-group">
                <label>Chọn chủ đề (có thể chọn nhiều)</label>
                @include('admin.partials.bulk-tag-picker', ['tags' => $tags, 'id' => 'qbBulkTagPicker', 'selectedIds' => []])
                <button type="button" class="tag-checklist-add" data-open-tag-modal-from-checklist>+ Thêm chủ đề</button>
            </div>
            <p id="qbBulkConfirmText" class="bulk-tag-confirm-text"></p>
        </div>
        <footer class="qq-modal-footer">
            <button type="button" class="btn btn-secondary" data-close-qb-bulk-modal>Hủy</button>
            <button type="button" class="btn btn-primary" id="btnQbBulkConfirm">Xác nhận</button>
        </footer>
    </div>
</div>
@endsection

@push('head')
@php $qbCss = public_path('htd-admin/css/quiz-questions.css'); @endphp
<link rel="stylesheet" href="@vasset('htd-admin/css/quiz-questions.css')">
@endpush

@push('scripts')
<script src="@vasset('js/admin-csv-exchange.js')"></script>
<script src="@vasset('js/question-tags-cell.js')"></script>
<script src="@vasset('js/question-bank-list.js')"></script>
<script src="@vasset('js/grouped-list.js')"></script>
@endpush
