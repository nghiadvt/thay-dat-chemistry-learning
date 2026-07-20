@extends('layouts.admin')

@section('title', 'Góp ý — Hóa Thầy Đạt')

@php
    $columns = [
        ['key' => 'created', 'label' => 'Thời gian'],
        ['key' => 'user', 'label' => 'Giáo viên', 'default' => $isAdmin],
        ['key' => 'page', 'label' => 'Trang'],
        ['key' => 'priority', 'label' => 'Ưu tiên'],
        ['key' => 'status', 'label' => 'Trạng thái'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $visibleColumns = array_values(array_filter($columns, fn ($c) => ($c['default'] ?? true) || $c['key'] === 'actions'));
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'priority', 'status']);
    $priorityLabels = ['high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp'];
    $statusLabels = \App\Support\StatusLabels::FEEDBACK;
    $activeFilterCount = collect([request('priority'), request('status')])->filter(fn ($v) => $v !== null && $v !== '')->count();
    $filterChips = [];
    if (request('priority')) {
        $filterChips[] = ['label' => 'Ưu tiên: '.($priorityLabels[request('priority')] ?? request('priority')), 'url' => route('admin.feedback.index', request()->except(['priority', 'page']))];
    }
    if (request('status')) {
        $filterChips[] = ['label' => 'Trạng thái: '.($statusLabels[request('status')] ?? request('status')), 'url' => route('admin.feedback.index', request()->except(['status', 'page']))];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>{{ $isAdmin ? 'Tất cả góp ý' : 'Góp ý của tôi' }}</h2>
        @if (!$feedback->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $feedback->total() }} góp ý{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'feedbackSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo nội dung hoặc trang…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', ['panelId' => 'feedbackFilterPanel', 'activeCount' => $activeFilterCount])
            @include('admin.partials.table-column-picker', ['tableId' => 'feedback-list', 'columns' => $visibleColumns])
        </div>
    </div>

    <div id="feedbackFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="priority">Độ ưu tiên</label>
                    <select id="priority" name="priority" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.feedback.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.feedback.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($feedback->isEmpty())
        <div class="empty-state">Chưa có góp ý nào.</div>
    @else
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table" data-table-id="feedback-list">
                <colgroup>
                    @foreach ($visibleColumns as $column)
                        <col data-col="{{ $column['key'] }}">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach ($visibleColumns as $column)
                            <th data-col="{{ $column['key'] }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($feedback as $item)
                    <tr>
                        <td data-col="created">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                        @if ($isAdmin)
                        <td data-col="user">
                            <div class="feedback-user-cell">
                                @if ($item->user?->avatar_url)
                                    <img src="{{ $item->user->avatar_url }}" alt="" class="feedback-avatar feedback-avatar--img">
                                @else
                                    <span class="feedback-avatar" aria-hidden="true">{{ $item->user?->initials }}</span>
                                @endif
                                <div>
                                    <strong>{{ $item->user?->name }}</strong>
                                    <div class="feedback-meta">#{{ $item->user_id }} · {{ $item->user?->role?->name }}</div>
                                </div>
                            </div>
                        </td>
                        @endif
                        <td data-col="page">
                            <div class="feedback-page-cell">
                                <code>{{ $item->page_url }}</code>
                                @if ($item->page_title)
                                    <div class="feedback-meta">{{ $item->page_title }}</div>
                                @endif
                            </div>
                        </td>
                        <td data-col="priority">
                            <span class="feedback-priority feedback-priority--{{ $item->priority }}">{{ $item->priorityLabel() }}</span>
                        </td>
                        <td data-col="status">
                            <span class="feedback-status feedback-status--{{ $item->status }}">{{ $item->statusLabel() }}</span>
                        </td>
                        <td data-col="actions" class="actions-cell">
                            @include('admin.partials.row-action-menu', [
                                'actions' => [
                                    ['key' => 'detail', 'label' => 'Chi tiết', 'href' => route('admin.feedback.show', $item)],
                                ],
                                'dataAttrs' => [],
                            ])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $feedback, 'itemLabel' => 'góp ý'])
    @endif
</div>
@endsection

@push('head')
@php $fbCss = public_path('css/feedback-admin.css'); @endphp
<link rel="stylesheet" href="@vasset('css/feedback-admin.css')">
@endpush
