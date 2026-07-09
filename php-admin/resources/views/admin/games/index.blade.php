@extends('layouts.admin')

@section('title', 'Game — Hóa Thầy Đạt')
@section('page-title', 'Game')

@section('content')
<div class="page-header">
    <h2>Danh sách game</h2>
    <a href="{{ route('admin.games.create') }}" class="btn btn-primary">+ Tạo game</a>
</div>

<div class="card">
    @if ($games->isEmpty())
        <div class="empty-state">Chưa có game. <a href="{{ route('admin.games.create') }}">Tạo mới</a></div>
    @else
    <div class="game-card-grid">
        @foreach ($games as $game)
        <article class="game-card">
            @if ($cover = $game->coverImageUrl())
                <div class="game-card__cover">
                    <img src="{{ $cover }}" alt="{{ $game->name }}">
                </div>
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
                    <form method="POST" action="{{ route('admin.games.destroy', $game) }}" onsubmit="return confirm('Xóa game này?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                    </form>
                </div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection
