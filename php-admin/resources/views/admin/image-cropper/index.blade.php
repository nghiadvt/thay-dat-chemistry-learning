@extends('layouts.admin')

@section('title', 'Cắt ảnh — Hóa Thầy Đạt')
@section('page-title', 'Cắt ảnh')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-image-cropper.css')">
@endpush

@php
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['updated_from', 'updated_to']);
    $activeFilterCount = collect([request('updated_from'), request('updated_to')])
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->count();
    $chipRemoveUrl = fn (string $key) => route('admin.image-cropper.index', array_diff_key(request()->query(), [$key => '', 'page' => '']));
    $filterChips = [];
    if (request('updated_from')) {
        $filterChips[] = ['label' => 'Cập nhật từ '.request('updated_from'), 'url' => $chipRemoveUrl('updated_from')];
    }
    if (request('updated_to')) {
        $filterChips[] = ['label' => 'Cập nhật đến '.request('updated_to'), 'url' => $chipRemoveUrl('updated_to')];
    }
    $sortLabels = [
        'updated_desc' => 'Cập nhật mới nhất',
        'updated_asc' => 'Cập nhật cũ nhất',
        'name_asc' => 'Tên A → Z',
        'name_desc' => 'Tên Z → A',
    ];
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Danh sách nhóm ảnh</h2>
        @if (!$groups->isEmpty() || $hasSearch || $hasFilters)
            <p class="page-header__meta">{{ $groups->total() }} nhóm{{ $hasSearch || $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <a href="{{ route('admin.image-cropper.create') }}" class="btn btn-primary">+ Tải ảnh mới</a>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'imageCropperSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo tên nhóm…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', [
                'panelId' => 'imageCropperFilterPanel',
                'activeCount' => $activeFilterCount,
            ])
        </div>
    </div>

    <div id="imageCropperFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="icSort">Sắp xếp</label>
                    <select id="icSort" name="sort" class="list-filter-control">
                        @foreach ($sortLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($sort ?? 'updated_desc') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="icUpdatedFrom">Cập nhật từ ngày</label>
                    <input type="date" id="icUpdatedFrom" name="updated_from" value="{{ request('updated_from') }}" class="list-filter-control">
                </div>
                <div class="form-group">
                    <label for="icUpdatedTo">Cập nhật đến ngày</label>
                    <input type="date" id="icUpdatedTo" name="updated_to" value="{{ request('updated_to') }}" class="list-filter-control">
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.image-cropper.index', request()->except(['updated_from', 'updated_to', 'page'])) }}" class="btn btn-secondary btn-sm">Xóa bộ lọc ngày</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.image-cropper.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($groups->isEmpty())
        <div class="empty-state">
            @if ($hasSearch || $hasFilters)
                Không có nhóm phù hợp. <a href="{{ route('admin.image-cropper.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có ảnh nào. <a href="{{ route('admin.image-cropper.create') }}">Tải ảnh mới</a>
            @endif
        </div>
    @else
        <div class="ic-group-list">
            @foreach ($groups as $group)
                @php $tag = $group['tag']; $previewSources = $group['sources']->take(4); @endphp
                <details class="ic-group-accordion" style="--ic-group-color: {{ $tag->color ?? '#9CA3AF' }};">
                    <summary class="ic-group-summary">
                        <svg class="ic-group-chevron" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5H5.5z"/></svg>
                        <div class="ic-group-heading">
                            @include('admin.partials.tag-chip', ['tag' => $tag])
                            <span class="ic-group-meta">cập nhật {{ $group['updated_at']?->diffForHumans() }}</span>
                        </div>
                        @if ($previewSources->isNotEmpty())
                            <div class="ic-group-preview-strip">
                                @foreach ($previewSources as $previewSource)
                                    <span class="ic-group-preview-thumb">
                                        @if ($previewSource->preview_url ?? $previewSource->image_url)
                                            <img src="{{ $previewSource->preview_url ?? $previewSource->image_url }}" alt="" loading="lazy">
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <span class="ic-group-count-badge">{{ count($group['sources']) }} ảnh</span>
                    </summary>
                    <div class="ic-group-body">
                        <div class="table-wrap admin-list-table-wrap">
                            <table class="data-table admin-list-table">
                                <thead>
                                    <tr>
                                        <th>Ảnh</th>
                                        <th>Tên</th>
                                        <th>Số ảnh con</th>
                                        <th>Thời gian tạo</th>
                                        <th>Nhóm</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['sources'] as $source)
                                    @php $sourceThumbUrl = $source->preview_url ?? $source->image_url; @endphp
                                    <tr>
                                        <td class="kb-preview-cell">
                                            @if ($sourceThumbUrl)
                                                <button type="button" class="kb-preview-thumb ic-list-thumb" data-preview-src="{{ $sourceThumbUrl }}" data-preview-name="{{ $source->name }}" title="Xem ảnh gốc + vùng đã khoanh">
                                                    <img src="{{ $sourceThumbUrl }}" alt="{{ $source->name }}" loading="lazy">
                                                </button>
                                            @else
                                                <span class="kb-preview-missing">Không có ảnh</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $source->name }}</strong></td>
                                        <td>{{ $source->regions_count }}</td>
                                        <td>{{ $source->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="ic-group-cell">
                                            @include('admin.partials.question-tags-cell', [
                                                'tags' => $allGroupTags,
                                                'selectedTags' => $source->tags,
                                                'updateUrl' => route('admin.image-cropper.update-groups', $source),
                                            ])
                                        </td>
                                        <td class="actions-cell">
                                            @include('admin.partials.row-action-menu', [
                                                'actions' => [
                                                    ['key' => 'edit', 'label' => 'Chỉnh sửa', 'href' => route('admin.image-cropper.edit', $source)],
                                                    ['key' => 'delete', 'label' => 'Xóa', 'danger' => true, 'href' => route('admin.image-cropper.destroy', $source), 'method' => 'DELETE', 'confirm' => "Xóa ảnh «{$source->name}» và toàn bộ vùng đã cắt?"],
                                                ],
                                                'dataAttrs' => ['item-label' => $source->name],
                                            ])
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $groups, 'itemLabel' => 'nhóm'])
    @endif
</div>

@include('admin.partials.keyboard-preview-lightbox')
@endsection

@push('scripts')
<script src="@vasset('htd-admin/js/admin-keyboard-preview.js')"></script>
<script src="@vasset('js/question-tags-cell.js')"></script>
@endpush
