@extends('layouts.admin')

@section('title', 'Bàn phím — Hóa Thầy Đạt')
@section('page-title', 'Bàn phím')

@php
    $columns = [
        ['key' => 'preview', 'label' => 'Preview'],
        ['key' => 'name', 'label' => 'Tên'],
        ['key' => 'subject', 'label' => 'Môn'],
        ['key' => 'rows', 'label' => 'Số hàng'],
        ['key' => 'quizzes', 'label' => 'Quiz dùng'],
        ['key' => 'updated', 'label' => 'Cập nhật'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'subject']);
    $activeFilterCount = request()->filled('subject') ? 1 : 0;
    $filterChips = [];
    if (request('subject')) {
        $filterChips[] = [
            'label' => 'Môn: '.request('subject'),
            'url' => route('admin.keyboards.index', request()->except(['subject', 'page'])),
        ];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Danh sách bàn phím</h2>
        @if (!$keyboards->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $keyboards->total() }} bàn phím{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <a href="{{ route('admin.keyboards.create') }}" class="btn btn-primary">+ Tạo bàn phím</a>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'keyboardSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo tên hoặc môn…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', [
                'panelId' => 'keyboardFilterPanel',
                'activeCount' => $activeFilterCount,
            ])
            @include('admin.partials.table-column-picker', ['tableId' => 'keyboards-list', 'columns' => $columns])
        </div>
    </div>

    <div id="keyboardFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="keyboardSubject">Môn</label>
                    <select id="keyboardSubject" name="subject" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject }}" @selected(request('subject') === $subject)>{{ $subject }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.keyboards.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.keyboards.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($keyboards->isEmpty())
        <div class="empty-state">
            @if ($hasFilters)
                Không có bàn phím phù hợp. <a href="{{ route('admin.keyboards.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có bàn phím. <a href="{{ route('admin.keyboards.create') }}">Tạo mới</a>
            @endif
        </div>
    @else
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table" data-table-id="keyboards-list">
                <colgroup>
                    @foreach ($columns as $column)
                        <col data-col="{{ $column['key'] }}">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th data-col="{{ $column['key'] }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($keyboards as $keyboard)
                    <tr>
                        <td data-col="preview" class="kb-preview-cell">
                            @if ($keyboard->preview_url)
                                <button type="button" class="kb-preview-thumb" data-preview-src="{{ $keyboard->preview_url }}" data-preview-name="{{ $keyboard->name }}" title="Xem preview">
                                    <img src="{{ $keyboard->preview_url }}" alt="Preview {{ $keyboard->name }}" loading="lazy">
                                </button>
                            @else
                                <a href="{{ route('admin.keyboards.editor', $keyboard) }}" class="kb-preview-missing">Chưa có</a>
                            @endif
                        </td>
                        <td data-col="name"><strong>{{ $keyboard->name }}</strong></td>
                        <td data-col="subject">{{ $keyboard->subject ?: '—' }}</td>
                        <td data-col="rows">{{ count($keyboard->config['rows'] ?? []) }}</td>
                        <td data-col="quizzes">{{ $keyboard->quizzes_count }}</td>
                        <td data-col="updated">{{ $keyboard->updated_at?->format('d/m/Y H:i') }}</td>
                        <td data-col="actions" class="actions-cell">
                            @include('admin.partials.row-action-menu', [
                                'actions' => [
                                    ['key' => 'edit', 'label' => 'Chỉnh sửa', 'href' => route('admin.keyboards.edit', $keyboard)],
                                    ['key' => 'navigate', 'label' => 'Mở editor', 'href' => route('admin.keyboards.editor', $keyboard)],
                                    ['key' => 'delete', 'label' => 'Xóa', 'danger' => true, 'href' => route('admin.keyboards.destroy', $keyboard), 'method' => 'DELETE', 'confirm' => "Xóa bàn phím «{$keyboard->name}»?"],
                                ],
                                'dataAttrs' => ['item-label' => $keyboard->name],
                            ])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $keyboards, 'itemLabel' => 'bàn phím'])
    @endif
</div>

@include('admin.partials.keyboard-preview-lightbox')
@endsection

@push('scripts')
@php $kbPreviewJs = public_path('htd-admin/js/admin-keyboard-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/admin-keyboard-preview.js') }}?v={{ file_exists($kbPreviewJs) ? filemtime($kbPreviewJs) : time() }}"></script>
@endpush
