@extends('layouts.admin')

@section('title', 'Phòng chơi — Hóa Thầy Đạt')
@section('page-title', 'Phòng chơi')

@php
    $sessionColumns = [
        ['key' => 'name', 'label' => 'Tên phòng'],
        ['key' => 'pin', 'label' => 'PIN'],
        ['key' => 'quiz', 'label' => 'Quiz'],
        ['key' => 'game', 'label' => 'Game'],
        ['key' => 'host', 'label' => 'Giáo viên'],
        ['key' => 'status', 'label' => 'Trạng thái'],
        ['key' => 'active', 'label' => 'Bật'],
        ['key' => 'created', 'label' => 'Tạo lúc'],
        ['key' => 'actions', 'label' => 'Hành động'],
    ];

    $statusLabels = \App\Support\StatusLabels::SESSION;

    $searchValue = trim((string) ($search ?? request('q', '')));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'status', 'is_active', 'game_id', 'host_id', 'created_from', 'created_to']);

    $activeFilterCount = collect([
        request('status'),
        request('is_active'),
        request('game_id'),
        request('host_id'),
        request('created_from'),
        request('created_to'),
    ])->filter(fn ($v) => $v !== null && $v !== '')->count();

    // Chip bộ lọc — cùng contract 'url' với partial list-active-filters (như các trang khác)
    $chipRemoveUrl = fn (string $key) => route('admin.sessions.index', array_diff_key(request()->query(), [$key => '', 'page' => '']));
    $filterChips = [];
    if (request('status')) {
        $filterChips[] = ['label' => 'Trạng thái: '.($statusLabels[request('status')] ?? request('status')), 'url' => $chipRemoveUrl('status')];
    }
    if (request()->has('is_active') && request('is_active') !== '') {
        $filterChips[] = ['label' => request('is_active') === '1' ? 'Đang bật' : 'Đã tắt', 'url' => $chipRemoveUrl('is_active')];
    }
    if (request('game_id')) {
        $gameName = $games->firstWhere('id', (int) request('game_id'))?->name ?? 'Game #'.request('game_id');
        $filterChips[] = ['label' => 'Game: '.$gameName, 'url' => $chipRemoveUrl('game_id')];
    }
    if (request('host_id')) {
        $hostName = $hosts->firstWhere('id', (int) request('host_id'))?->name ?? 'GV #'.request('host_id');
        $filterChips[] = ['label' => 'Giáo viên: '.$hostName, 'url' => $chipRemoveUrl('host_id')];
    }
    if (request('created_from')) {
        $filterChips[] = ['label' => 'Từ '.request('created_from'), 'url' => $chipRemoveUrl('created_from')];
    }
    if (request('created_to')) {
        $filterChips[] = ['label' => 'Đến '.request('created_to'), 'url' => $chipRemoveUrl('created_to')];
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Danh sách phòng</h2>
        @if (!$sessions->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $sessions->total() }} phòng{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">+ Tạo phòng mới</a>
</div>

<div class="card admin-list-card sessions-list-card" id="sessionsListCard" data-bulk-destroy-url="{{ route('admin.sessions.bulk-destroy') }}">
    <div class="list-toolbar">
        <form method="GET" class="list-toolbar__search" data-admin-list-search>
            <label class="list-search" for="sessionSearch">
                <svg class="list-search__icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">
                    <path fill="currentColor" d="M8.5 3a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm6.36 10.14-2.55-2.55a.75.75 0 0 1 1.06-1.06l2.55 2.55a.75.75 0 1 1-1.06 1.06Z"/>
                </svg>
                <input
                    type="search"
                    id="sessionSearch"
                    name="q"
                    value="{{ $searchValue }}"
                    placeholder="Tìm theo tên phòng hoặc PIN…"
                    autocomplete="off"
                >
            </label>
            @foreach (request()->except(['q', 'page']) as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $item)
                        @if (is_scalar($item) && $item !== '')
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endif
                    @endforeach
                @elseif (is_scalar($value) && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm">Tìm</button>
            <button type="button" class="btn btn-secondary btn-sm list-search__clear" data-search-clear @if (!$hasSearch) hidden @endif aria-label="Xóa tìm kiếm">×</button>
        </form>

        <div class="list-toolbar__tools">
            <button
                type="button"
                class="list-toolbar__filter-toggle"
                data-filter-panel-toggle
                aria-expanded="{{ ($hasFilters && $activeFilterCount) ? 'true' : 'false' }}"
                aria-controls="sessionFilterPanel"
            >
                <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M2.5 4.75A.75.75 0 0 1 3.25 4h13.5a.75.75 0 0 1 .53 1.28l-5.03 5.03v4.19a.75.75 0 0 1-1.085.67l-2.5-1.25A.75.75 0 0 1 8 14.25v-3.94L2.72 5.28A.75.75 0 0 1 2.5 4.75Z"/></svg>
                <span>Bộ lọc</span>
                @if ($activeFilterCount)
                    <span class="list-toolbar__badge">{{ $activeFilterCount }}</span>
                @endif
            </button>

            @include('admin.partials.table-column-picker', [
                'tableId' => 'sessions-list',
                'columns' => $sessionColumns,
            ])
        </div>
    </div>

    <div
        id="sessionFilterPanel"
        class="list-filters-panel"
        data-filter-panel
        @if (!$activeFilterCount) hidden @endif
    >
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)
                <input type="hidden" name="q" value="{{ $searchValue }}">
            @endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="sessionStatus">Trạng thái</label>
                    <select id="sessionStatus" name="status" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="sessionActive">Bật / tắt</label>
                    <select id="sessionActive" name="is_active" class="list-filter-control">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(request('is_active') === '1')>Đang bật</option>
                        <option value="0" @selected(request('is_active') === '0')>Đã tắt</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sessionGame">Game</label>
                    <select id="sessionGame" name="game_id" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($games as $game)
                            <option value="{{ $game->id }}" @selected((string) request('game_id') === (string) $game->id)>{{ $game->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="sessionHost">Giáo viên</label>
                    <select id="sessionHost" name="host_id" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($hosts as $host)
                            <option value="{{ $host->id }}" @selected((string) request('host_id') === (string) $host->id)>{{ $host->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="sessionCreatedFrom">Tạo từ ngày</label>
                    <input type="date" id="sessionCreatedFrom" name="created_from" value="{{ request('created_from') }}" class="list-filter-control">
                </div>
                <div class="form-group">
                    <label for="sessionCreatedTo">Tạo đến ngày</label>
                    <input type="date" id="sessionCreatedTo" name="created_to" value="{{ request('created_to') }}" class="list-filter-control">
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'chips' => $filterChips,
        'searchChip' => $hasSearch
            ? ['label' => $searchValue, 'url' => route('admin.sessions.index', request()->except(['q', 'page']))]
            : null,
    ])

    @if ($sessions->isEmpty())
        <div class="empty-state">
            @if ($hasFilters)
                Không có phòng phù hợp. <a href="{{ route('admin.sessions.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có phòng nào. <a href="{{ route('admin.sessions.create') }}">Tạo phòng mới</a>
            @endif
        </div>
    @else
        <div id="sessionsBulkBar" class="sessions-bulk-bar sessions-bulk-bar--idle">
            <span class="sessions-bulk-count"><strong id="sessionsBulkCount">0</strong> phòng đã chọn</span>
            <div class="sessions-bulk-actions">
                <button type="button" class="btn btn-danger btn-sm" data-sessions-bulk-delete disabled>Xóa đã chọn</button>
            </div>
        </div>

        <div class="table-wrap sessions-table-wrap">
            <table class="data-table sessions-data-table" data-table-id="sessions-list">
                <colgroup>
                    <col data-col="select">
                    @foreach ($sessionColumns as $column)
                        <col data-col="{{ $column['key'] }}">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th class="sessions-col-select" data-col="select">
                            <input type="checkbox" id="sessionsSelectAll" aria-label="Chọn tất cả phòng trên trang">
                        </th>
                        @foreach ($sessionColumns as $column)
                            <th data-col="{{ $column['key'] }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                    @php
                        $rowActions = [];
                        if ($session->quiz_id) {
                            $rowActions[] = ['key' => 'trial', 'label' => 'Chơi thử'];
                        }
                        if ($session->status !== 'ended') {
                            $rowActions[] = ['key' => 'host', 'label' => 'Vào phòng chơi'];
                        }
                        if ($session->status === 'ended' && $session->is_active) {
                            $rowActions[] = ['key' => 'replay', 'label' => 'Chơi lại'];
                        }
                        if ($session->status !== 'playing') {
                            $rowActions[] = ['key' => 'delete', 'label' => 'Xóa phòng', 'danger' => true];
                        }
                    @endphp
                    <tr class="{{ $session->is_active ? '' : 'row-inactive' }}" data-session-id="{{ $session->id }}">
                        <td class="sessions-col-select" data-col="select">
                            <input
                                type="checkbox"
                                class="sessions-row-check"
                                value="{{ $session->id }}"
                                aria-label="Chọn phòng {{ $session->name ?? $session->pin }}"
                                @disabled($session->status === 'playing')
                            >
                        </td>
                        <td data-col="name">
                            <span class="session-name-cell">{{ $session->name ?? 'Phòng '.$session->pin }}</span>
                        </td>
                        <td data-col="pin">
                            <span class="session-pin-badge">{{ $session->pin }}</span>
                        </td>
                        <td data-col="quiz" title="{{ $session->quiz?->name }}">{{ $session->quiz?->name ?? '—' }}</td>
                        <td data-col="game" title="{{ $session->game?->name }}">{{ $session->game?->name ?? '—' }}</td>
                        <td data-col="host">{{ $session->host?->name ?? '—' }}</td>
                        <td data-col="status">
                            <span class="badge badge-{{ $session->status }}">
                                {{ $statusLabels[$session->status] ?? $session->status }}
                            </span>
                        </td>
                        <td data-col="active">
                            @include('admin.partials.toggle-switch', [
                                'formAction' => route('admin.sessions.toggle-active', $session),
                                'checked' => $session->is_active,
                                'submitOnChange' => true,
                                'label' => 'Bật / tắt phòng',
                            ])
                        </td>
                        <td data-col="created" class="session-created-cell">{{ $session->created_at?->format('d/m/Y H:i') }}</td>
                        <td data-col="actions" class="actions-cell">
                            <div class="row-actions-group">
                                <a href="{{ route('admin.sessions.edit', $session) }}" class="btn btn-secondary btn-sm">Chi tiết</a>
                                @if (!empty($rowActions))
                                    @include('admin.partials.row-action-menu', [
                                        'menuLabel' => 'Khác',
                                        'actions' => $rowActions,
                                        'dataAttrs' => [
                                            'host-url' => route('admin.sessions.show', $session),
                                            'reset-url' => route('admin.sessions.reset', $session),
                                            'delete-url' => route('admin.sessions.destroy', $session),
                                            'quiz-id' => $session->quiz_id,
                                            'quiz-name' => $session->quiz?->name,
                                            'session-pin' => $session->pin,
                                            'session-name' => $session->name ?? ('Phòng '.$session->pin),
                                        ],
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="list-table-footer">
            <p class="list-table-footer__summary">
                @if ($sessions->total())
                    Hiển thị {{ $sessions->firstItem() }}–{{ $sessions->lastItem() }} / {{ $sessions->total() }} phòng
                @endif
            </p>
            @if ($sessions->hasPages())
                <div class="list-table-footer__pagination">
                    {{ $sessions->links('vendor.pagination.admin') }}
                </div>
            @endif
        </div>
    @endif
</div>

<div class="admin-modal" id="sessionDeleteModal" hidden>
    <div class="admin-modal__backdrop" data-close-delete-modal></div>
    <div class="admin-modal__panel" role="dialog" aria-labelledby="sessionDeleteModalTitle" aria-modal="true">
        <h3 id="sessionDeleteModalTitle">Xóa phòng?</h3>
        <p id="sessionDeleteModalBody" class="admin-modal__text"></p>
        <form id="sessionDeleteModalForm" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" id="sessionDeleteModalMethod" value="DELETE">
            <div id="sessionDeleteModalIds"></div>
            <div class="admin-modal__actions">
                <button type="button" class="btn btn-secondary" data-close-delete-modal>Hủy</button>
                <button type="submit" class="btn btn-danger">Xóa vĩnh viễn</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('head')
@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@endpush

@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); $slJs = public_path('js/sessions-list.js'); @endphp
<script src="@vasset('htd-admin/js/quiz-preview.js')"></script>
<script src="@vasset('js/sessions-list.js')"></script>
@endpush
