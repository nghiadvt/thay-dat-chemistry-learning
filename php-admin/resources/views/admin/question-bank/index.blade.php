@extends('layouts.admin')

@section('title', 'Bộ câu hỏi — Hóa Thầy Đạt')
@section('page-title', 'Bộ câu hỏi')

@php
    $pickerColumns = [
        ['key' => 'type', 'label' => 'Loại'],
        ['key' => 'tags', 'label' => 'Chủ đề'],
        ['key' => 'content', 'label' => 'Nội dung'],
        ['key' => 'points', 'label' => 'Điểm'],
        ['key' => 'time', 'label' => 'Thời gian'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $searchValue = trim((string) ($filterQuery ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = ($filterTagIds ?? []) !== [] || ($filterTagNone ?? false) || ($filterTagMatch ?? 'and') !== 'and' || $filterAnswerType || $hasSearch;
    $activeFilterCount = count($filterTagIds ?? []) + (($filterTagNone ?? false) ? 1 : 0) + (($filterTagMatch ?? 'and') !== 'and' ? 1 : 0) + ($filterAnswerType ? 1 : 0);
    $typeLabels = ['mc' => 'Trắc nghiệm', 'essay' => 'Tự luận', 'structured' => 'Phương trình'];
    $filterChips = [];
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
        @if (!$items->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $items->total() }} câu hỏi{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <a href="{{ route('admin.question-bank.create') }}" class="btn btn-primary">+ Thêm câu hỏi</a>
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

    @if ($items->isEmpty())
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
            <table class="data-table admin-list-table" data-table-id="question-bank-list">
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
                <tbody>
                    @foreach ($items as $item)
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
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $items, 'itemLabel' => 'câu hỏi'])
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
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-questions.css') }}?v={{ file_exists($qbCss) ? filemtime($qbCss) : time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin-csv-exchange.js') }}?v={{ file_exists(public_path('js/admin-csv-exchange.js')) ? filemtime(public_path('js/admin-csv-exchange.js')) : time() }}"></script>
<script src="{{ asset('js/question-tags-cell.js') }}?v={{ file_exists(public_path('js/question-tags-cell.js')) ? filemtime(public_path('js/question-tags-cell.js')) : time() }}"></script>
<script src="{{ asset('js/question-bank-list.js') }}?v={{ file_exists(public_path('js/question-bank-list.js')) ? filemtime(public_path('js/question-bank-list.js')) : time() }}"></script>
@endpush
