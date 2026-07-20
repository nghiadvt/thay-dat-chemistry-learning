@extends('layouts.admin')

@section('title', 'Báo cáo — Hóa Thầy Đạt')
@section('page-title', 'Báo cáo')

@php
    $columns = [
        ['key' => 'pin', 'label' => 'PIN'],
        ['key' => 'name', 'label' => 'Tên phòng'],
        ['key' => 'game', 'label' => 'Game'],
        ['key' => 'host', 'label' => 'Giáo viên'],
        ['key' => 'ended', 'label' => 'Kết thúc'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'game_id', 'date_from', 'date_to']);
    $activeFilterCount = collect([request('game_id'), request('date_from'), request('date_to')])->filter(fn ($v) => $v !== null && $v !== '')->count();
    $filterChips = [];
    if (request('game_id')) {
        $gameName = $games->firstWhere('id', (int) request('game_id'))?->name ?? '#'.request('game_id');
        $filterChips[] = ['label' => 'Game: '.$gameName, 'url' => route('admin.reports.index', request()->except(['game_id', 'page']))];
    }
    if (request('date_from')) {
        $filterChips[] = ['label' => 'Từ '.request('date_from'), 'url' => route('admin.reports.index', request()->except(['date_from', 'page']))];
    }
    if (request('date_to')) {
        $filterChips[] = ['label' => 'Đến '.request('date_to'), 'url' => route('admin.reports.index', request()->except(['date_to', 'page']))];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Lịch sử session đã kết thúc</h2>
        @if (!$sessions->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $sessions->total() }} báo cáo{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'reportSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo PIN hoặc tên phòng…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', ['panelId' => 'reportFilterPanel', 'activeCount' => $activeFilterCount])
            @include('admin.partials.table-column-picker', ['tableId' => 'reports-list', 'columns' => $columns])
        </div>
    </div>

    <div id="reportFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="reportGame">Game</label>
                    <select id="reportGame" name="game_id" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($games as $game)
                            <option value="{{ $game->id }}" @selected((string) request('game_id') === (string) $game->id)>{{ $game->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">Từ ngày</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="list-filter-control">
                </div>
                <div class="form-group">
                    <label for="date_to">Đến ngày</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="list-filter-control">
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.reports.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($sessions->isEmpty())
        <div class="empty-state">
            @if ($hasFilters)
                Không có báo cáo phù hợp. <a href="{{ route('admin.reports.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có session đã kết thúc.
            @endif
        </div>
    @else
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table" data-table-id="reports-list">
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
                    @foreach ($sessions as $session)
                    <tr>
                        <td data-col="pin"><span class="session-pin-badge">{{ $session->pin }}</span></td>
                        <td data-col="name">{{ $session->name ?? '—' }}</td>
                        <td data-col="game">{{ $session->gameName() }}</td>
                        <td data-col="host">{{ $session->host?->name }}</td>
                        <td data-col="ended">{{ $session->ended_at?->format('d/m/Y H:i') }}</td>
                        <td data-col="actions" class="actions-cell">
                            @include('admin.partials.row-action-menu', [
                                'actions' => [
                                    ['key' => 'detail', 'label' => 'Chi tiết', 'href' => route('admin.reports.show', $session)],
                                    ['key' => 'navigate', 'label' => 'Tải CSV', 'href' => route('admin.reports.export', $session)],
                                ],
                                'dataAttrs' => [],
                            ])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $sessions, 'itemLabel' => 'báo cáo'])
    @endif
</div>
@endsection
