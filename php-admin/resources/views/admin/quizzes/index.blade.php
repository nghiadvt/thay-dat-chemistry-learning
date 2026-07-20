@extends('layouts.admin')

@section('title', 'Quiz — Hóa Thầy Đạt')
@section('page-title', 'Quiz')

@php
    $columns = [
        ['key' => 'name', 'label' => 'Tên'],
        ['key' => 'group', 'label' => 'Nhóm'],
        ['key' => 'tags', 'label' => 'Chủ đề'],
        ['key' => 'game', 'label' => 'Game'],
        ['key' => 'keyboard', 'label' => 'Bàn phím'],
        ['key' => 'grade', 'label' => 'Lớp'],
        ['key' => 'questions', 'label' => 'Câu hỏi'],
        ['key' => 'active', 'label' => 'Bật'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'game_id', 'tag_id', 'group_id']);
    $activeFilterCount = collect([request('game_id'), request('tag_id'), request('group_id')])->filter(fn ($v) => $v !== null && $v !== '')->count();
    $filterChips = [];
    if ($filterGroupId) {
        $groupLabel = $filterGroupId === 'none' ? 'Chưa phân nhóm' : ($groups->firstWhere('id', (int) $filterGroupId)?->name ?? 'Nhóm #'.$filterGroupId);
        $filterChips[] = ['label' => 'Nhóm: '.$groupLabel, 'url' => route('admin.quizzes.index', request()->except(['group_id', 'page']))];
    }
    if ($filterGameId) {
        $gameName = $games->firstWhere('id', $filterGameId)?->name ?? 'Game #'.$filterGameId;
        $filterChips[] = ['label' => 'Game: '.$gameName, 'url' => route('admin.quizzes.index', request()->except(['game_id', 'page']))];
    }
    if ($filterTagId) {
        $tagLabel = $filterTagId === 'none' ? 'Chưa có chủ đề' : ($tags->firstWhere('id', (int) $filterTagId)?->name ?? 'Chủ đề #'.$filterTagId);
        $filterChips[] = ['label' => 'Chủ đề: '.$tagLabel, 'url' => route('admin.quizzes.index', request()->except(['tag_id', 'page']))];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Danh sách quiz</h2>
        @if ($grouped)
            <p class="page-header__meta">{{ collect($sections)->sum('count') }} quiz trong {{ count($sections) }} nhóm</p>
        @elseif (!$quizzes->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $quizzes->total() }} quiz{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <div class="page-header__actions">
        <a href="{{ route('admin.groups.index', ['scope' => 'quiz']) }}" class="btn btn-secondary">Quản lý nhóm</a>
        <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary">+ Tạo quiz</a>
    </div>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'quizSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo tên quiz…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', ['panelId' => 'quizFilterPanel', 'activeCount' => $activeFilterCount])
            @include('admin.partials.csv-exchange', [
                'tableId' => 'quizzes-list',
                'exportUrl' => route('admin.quizzes.export-csv'),
                'templateUrl' => route('admin.quizzes.import-template'),
                'importUrl' => route('admin.quizzes.import-csv'),
                'registry' => $csvRegistry,
                'preserveQuery' => request()->except(['page']),
            ])
            @include('admin.partials.table-column-picker', ['tableId' => 'quizzes-list', 'columns' => $columns])
        </div>
    </div>

    <div id="quizFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    @include('admin.partials.group-select', [
                        'mode' => 'filter',
                        'id' => 'quizGroupFilter',
                        'groups' => $groups,
                        'selected' => $filterGroupId ?? '',
                    ])
                </div>
                <div class="form-group">
                    <label for="quizGame">Game</label>
                    <select id="quizGame" name="game_id" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($games as $game)
                            <option value="{{ $game->id }}" @selected($filterGameId === $game->id)>{{ $game->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group list-filters-panel__wide">
                    @include('admin.partials.tag-select', [
                        'mode' => 'filter',
                        'tags' => $tags,
                        'selected' => $filterTagId ?? '',
                        'name' => 'tag_id',
                        'autoSubmit' => false,
                        'showAll' => true,
                        'showUntagged' => true,
                    ])
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.quizzes.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($grouped)
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table admin-grouped-table" data-table-id="quizzes-list">
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
                @include('admin.partials.list-group-sections', [
                    'sections' => $sections,
                    'recent' => $recent,
                    'rowView' => 'admin.quizzes._row',
                    'rowVar' => 'quiz',
                    'rowsUrl' => route('admin.quizzes.group-rows'),
                    'colspan' => count($columns),
                    'emptyText' => 'Nhóm này chưa có quiz nào.',
                ])
            </table>
        </div>
    @elseif ($quizzes->isEmpty())
        <div class="empty-state">
            @if ($hasFilters)
                Không có quiz phù hợp. <a href="{{ route('admin.quizzes.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có quiz. <a href="{{ route('admin.quizzes.create') }}">Tạo mới</a>
            @endif
        </div>
    @else
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table" data-table-id="quizzes-list">
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
                    @foreach ($quizzes as $quiz)
                        @include('admin.quizzes._row', ['quiz' => $quiz])
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $quizzes, 'itemLabel' => 'quiz'])
    @endif
</div>
@endsection

@push('head')
@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@endpush

@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); @endphp
<script src="@vasset('js/admin-csv-exchange.js')"></script>
<script src="@vasset('js/grouped-list.js')"></script>
<script src="@vasset('htd-admin/js/quiz-preview.js')"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.AdminListPage.onAction = function (menu, action) {
    if (action === 'preview') {
      window.HTDQuizPreview?.openQuiz(menu.dataset.quizId, menu.dataset.quizName || 'Quiz');
    }
  };
});
</script>
@endpush
