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
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Mô tả</th>
                    <th>Quiz</th>
                    <th>Cập nhật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($games as $game)
                <tr>
                    <td><strong>{{ $game->name }}</strong></td>
                    <td>{{ Str::limit($game->description, 60) }}</td>
                    <td>{{ $game->quizzes_count }}</td>
                    <td>{{ $game->updated_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.quizzes.index', ['game_id' => $game->id]) }}" class="btn btn-secondary btn-sm">Quiz</a>
                        <a href="{{ route('admin.games.edit', $game) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.games.destroy', $game) }}" onsubmit="return confirm('Xóa game này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
