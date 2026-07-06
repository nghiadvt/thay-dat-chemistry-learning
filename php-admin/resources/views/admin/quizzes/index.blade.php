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
        <div class="form-group">
            <label for="tag_id">Lọc theo chủ đề</label>
            <select id="tag_id" name="tag_id" onchange="this.form.submit()">
                <option value="">Tất cả chủ đề</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected($filterTagId === $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($filterGameId || $filterTagId)
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary btn-sm">Xóa bộ lọc</a>
            </div>
        @endif
    </form>

    @if ($quizzes->isEmpty())
        <div class="empty-state">Chưa có quiz. <a href="{{ route('admin.quizzes.create') }}">Tạo mới</a></div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Chủ đề</th>
                    <th>Game</th>
                    <th>Bàn phím</th>
                    <th>Lớp</th>
                    <th>Câu hỏi</th>
                    <th>Bật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quizzes as $quiz)
                <tr class="{{ $quiz->is_active ? '' : 'row-inactive' }}">
                    <td><strong>{{ $quiz->name }}</strong></td>
                    <td>
                        @if ($quiz->tags->isEmpty())
                            <span class="text-muted">—</span>
                        @else
                            <div class="tag-list tag-list--compact">
                                @foreach ($quiz->tags as $tag)
                                    <a href="{{ route('admin.quizzes.index', ['tag_id' => $tag->id]) }}" class="tag-chip tag-chip--link">{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>{{ $quiz->game?->name }}</td>
                    <td>{{ $quiz->keyboard?->name }}</td>
                    <td>{{ $quiz->grade ?: '—' }}</td>
                    <td>{{ $quiz->questions_count }}</td>
                    <td>
                        @include('admin.partials.toggle-switch', [
                            'formAction' => route('admin.quizzes.toggle-active', $quiz),
                            'checked' => $quiz->is_active,
                            'submitOnChange' => true,
                            'label' => 'Bật/tắt quiz',
                        ])
                    </td>
                    <td class="actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-quiz-preview="{{ $quiz->id }}" data-quiz-name="{{ $quiz->name }}">Xem trước</button>
                        <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn btn-primary btn-sm">Chi tiết</a>
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

@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
@push('head')
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@endpush
@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/quiz-preview.js') }}?v={{ file_exists($qpJs) ? filemtime($qpJs) : $qpV }}"></script>
@endpush
