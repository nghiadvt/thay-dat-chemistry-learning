@extends('layouts.admin')

@section('title', 'Game — Hóa Thầy Đạt')
@section('page-title', 'Game')

@php
    $searchValue = trim((string) ($search ?? ''));
    $hasSearch = $searchValue !== '';
    $hasFilters = request()->hasAny(['q', 'play_mode_id']);
    $activeFilterCount = request()->filled('play_mode_id') ? 1 : 0;
    $filterChips = [];
    if (request('play_mode_id')) {
        $modeName = $playModes->firstWhere('id', (int) request('play_mode_id'))?->name ?? '#'.request('play_mode_id');
        $filterChips[] = [
            'label' => 'Chế độ: '.$modeName,
            'url' => route('admin.games.index', request()->except(['play_mode_id', 'page'])),
        ];
    }
@endphp

@push('head')
<link rel="stylesheet" href="@vasset('css/battle-arena-demo.css')">
<link rel="stylesheet" href="@vasset('css/dragon-hunt-demo.css')">
@endpush

@section('content')
<div class="demo-promo-row">
    <a href="{{ route('admin.games.dragon-demo') }}" class="drg-promo-banner">
        <span class="drg-promo-banner__badge">MỚI</span>
        <span class="drg-promo-banner__icon">🐲</span>
        <span class="drg-promo-banner__text">
            <strong>Game mới: Săn Rồng Hóa Học</strong>
            <span>Cả lớp hợp sức hạ boss Hắc Long — chí mạng, mưa thiên thạch, rồng phun lửa!</span>
        </span>
        <span class="drg-promo-banner__cta">Xem demo →</span>
    </a>
    <a href="{{ route('admin.games.battle-demo') }}" class="bat-promo-banner">
        <span class="bat-promo-banner__icon">⚔️</span>
        <span class="bat-promo-banner__text">
            <strong>Game: Đấu Trường Hóa Học</strong>
            <span>2 đội phù thủy đấu phép — trả lời đúng để tấn công, combo cộng dồn sát thương.</span>
        </span>
        <span class="bat-promo-banner__cta">Xem demo →</span>
    </a>
</div>

<div class="page-header">
    <div class="page-header__text">
        <h2>Danh sách game</h2>
        @if (!$games->isEmpty() || $hasFilters)
            <p class="page-header__meta">{{ $games->total() }} game{{ $hasFilters ? ' phù hợp bộ lọc' : '' }}</p>
        @endif
    </div>
    <a href="{{ route('admin.games.create') }}" class="btn btn-primary">+ Tạo game</a>
</div>

<div class="card admin-list-card">
    <div class="list-toolbar">
        @include('admin.partials.list-search', [
            'inputId' => 'gameSearch',
            'searchValue' => $searchValue,
            'searchPlaceholder' => 'Tìm theo tên game…',
            'preserveQuery' => request()->except(['q', 'page']),
        ])
        <div class="list-toolbar__tools">
            @include('admin.partials.list-filter-toggle', [
                'panelId' => 'gameFilterPanel',
                'activeCount' => $activeFilterCount,
            ])
        </div>
    </div>

    <div id="gameFilterPanel" class="list-filters-panel" data-filter-panel @if (!$activeFilterCount) hidden @endif>
        <form method="GET" class="list-filters-panel__form">
            @if ($hasSearch)<input type="hidden" name="q" value="{{ $searchValue }}">@endif
            <div class="list-filters-panel__grid">
                <div class="form-group">
                    <label for="gamePlayMode">Chế độ chơi</label>
                    <select id="gamePlayMode" name="play_mode_id" class="list-filter-control">
                        <option value="">Tất cả</option>
                        @foreach ($playModes as $mode)
                            <option value="{{ $mode->id }}" @selected((string) request('play_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="list-filters-panel__actions">
                <button type="submit" class="btn btn-primary btn-sm">Áp dụng bộ lọc</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.games.index') }}" class="btn btn-secondary btn-sm">Xóa tất cả</a>
                @endif
            </div>
        </form>
    </div>

    @include('admin.partials.list-active-filters', [
        'searchChip' => $hasSearch ? ['label' => $searchValue, 'url' => route('admin.games.index', request()->except(['q', 'page']))] : null,
        'chips' => $filterChips,
    ])

    @if ($games->isEmpty())
        <div class="empty-state">
            @if ($hasFilters)
                Không có game phù hợp. <a href="{{ route('admin.games.index') }}">Xóa bộ lọc</a>
            @else
                Chưa có game. <a href="{{ route('admin.games.create') }}">Tạo mới</a>
            @endif
        </div>
    @else
        <div class="game-card-grid">
            @foreach ($games as $game)
            <article class="game-card">
                @if ($cover = $game->coverImageUrl())
                    <div class="game-card__cover"><img src="{{ $cover }}" alt="{{ $game->name }}"></div>
                @else
                    <div class="game-card__cover game-card__cover--kahoot" aria-hidden="true">Quiz</div>
                @endif
                <div class="game-card__body">
                    <h3 class="game-card__title">{{ $game->name }}</h3>
                    <p class="game-card__meta">
                        {{ $game->playMode?->name ?? 'Quiz đồng bộ' }}
                        · {{ $game->quizzes_count }} quiz
                        · {{ $game->updated_at?->format('d/m/Y') }}
                    </p>
                    @if ($game->description)
                        <p class="game-card__desc">{{ Str::limit($game->description, 80) }}</p>
                    @endif
                    <div class="game-card__actions">
                        <a href="{{ route('admin.quizzes.index', ['game_id' => $game->id]) }}" class="btn btn-secondary btn-sm">Quiz</a>
                        <a href="{{ route('admin.games.edit', $game) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.games.destroy', $game) }}" data-confirm="Xóa game này?" data-confirm-title="Xóa game" data-confirm-ok="Xóa" data-confirm-danger="1">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        @include('admin.partials.list-table-footer', ['paginator' => $games, 'itemLabel' => 'game'])
    @endif
</div>
@endsection
