@extends('layouts.admin')

@section('title', 'Quiz — Hóa Thầy Đạt')
@section('page-title', 'Quiz')

@section('content')
<div class="page-header">
    <h2>Danh sách quiz</h2>
    <a href="{{ route('admin.quizzes.create') }}" class="btn btn-primary">+ Tạo quiz</a>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div class="form-group">
            <label for="game_id">Lọc theo game</label>
            <select id="game_id" name="game_id" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                @foreach ($games as $game)
                    <option value="{{ $game->id }}" @selected($filterGameId === $game->id)>{{ $game->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($quizzes->isEmpty())
        <div class="empty-state">Chưa có quiz. <a href="{{ route('admin.quizzes.create') }}">Tạo mới</a></div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Game</th>
                    <th>Bàn phím</th>
                    <th>Lớp</th>
                    <th>Câu hỏi</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quizzes as $quiz)
                <tr>
                    <td><strong>{{ $quiz->name }}</strong></td>
                    <td>{{ $quiz->game?->name }}</td>
                    <td>{{ $quiz->keyboard?->name }}</td>
                    <td>{{ $quiz->grade ?: '—' }}</td>
                    <td>{{ $quiz->questions_count }}</td>
                    <td>
                        <span class="badge {{ $quiz->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $quiz->is_active ? 'Active' : 'Tắt' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn btn-secondary btn-sm">Câu hỏi</a>
                        <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Xóa quiz và tất cả câu hỏi?')">
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
