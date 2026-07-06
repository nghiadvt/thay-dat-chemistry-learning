@extends('layouts.admin')

@section('title', $quiz->name.' — Quiz')
@section('page-title', 'Quiz: '.$quiz->name)

@section('content')
<div class="page-header">
    <div>
        <h2>{{ $quiz->name }}</h2>
        <p style="margin:4px 0 0;color:#6b7280;">
            Game: <strong>{{ $quiz->game?->name }}</strong> ·
            Bàn phím: <strong>{{ $quiz->keyboard?->name }}</strong> ·
            <span class="badge {{ $quiz->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $quiz->is_active ? 'Active' : 'Tắt' }}</span>
        </p>
    </div>
    <div class="actions">
        <a href="{{ route('admin.questions.create', $quiz) }}" class="btn btn-primary">+ Thêm câu hỏi</a>
        <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn btn-secondary">Sửa quiz</a>
        <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">← Danh sách</a>
    </div>
</div>

<div class="card">
    <h3>Câu hỏi ({{ $quiz->questions->count() }})</h3>

    @if ($quiz->questions->isEmpty())
        <div class="empty-state">
            Chưa có câu hỏi.
            <a href="{{ route('admin.questions.create', $quiz) }}">Thêm câu hỏi đầu tiên</a>
        </div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Loại</th>
                    <th>Nội dung</th>
                    <th>Thời gian</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quiz->questions as $question)
                <tr>
                    <td>{{ $question->sort_order }}</td>
                    <td><code>{{ $question->answer_type }}</code></td>
                    <td>{!! Str::limit(strip_tags($question->content), 80) !!}</td>
                    <td>{{ $question->time_limit_seconds }}s</td>
                    <td class="actions">
                        <a href="{{ route('admin.questions.edit', [$quiz, $question]) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.questions.destroy', [$quiz, $question]) }}" onsubmit="return confirm('Xóa câu hỏi này?')">
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
