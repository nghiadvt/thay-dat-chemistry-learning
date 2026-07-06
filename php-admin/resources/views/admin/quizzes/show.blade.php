@extends('layouts.admin')

@section('title', $quiz->name.' — Quiz')
@section('page-title', 'Chi tiết quiz')

@php
    $tagsValue = old('tags_input', $quiz->tags->pluck('name')->implode(', '));
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2>{{ $quiz->name }}</h2>
        <p style="margin:4px 0 0;color:#6b7280;">
            Game: <strong>{{ $quiz->game?->name }}</strong> ·
            Bàn phím: <strong>{{ $quiz->keyboard?->name }}</strong>
        </p>
        @if ($quiz->tags->isNotEmpty())
            <div class="tag-list" style="margin-top:8px;">
                @foreach ($quiz->tags as $tag)
                    <span class="tag-chip">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </div>
    <div class="actions">
        <button type="button" class="btn btn-secondary" data-quiz-preview="{{ $quiz->id }}" data-quiz-name="{{ $quiz->name }}">Xem trước</button>
        <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">← Danh sách</a>
    </div>
</div>

<div class="card quiz-detail-section">
    <div class="section-header">
        <h3>Thông tin quiz</h3>
        <div class="section-header-actions toggle-field">
            <span class="toggle-field-label">Kích hoạt</span>
            @include('admin.partials.toggle-switch', [
                'formAction' => route('admin.quizzes.toggle-active', $quiz),
                'checked' => $quiz->is_active,
                'submitOnChange' => true,
                'label' => 'Bật/tắt quiz',
            ])
        </div>
    </div>

    <form method="POST" action="{{ route('admin.quizzes.update', $quiz) }}">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label for="game_id">Game *</label>
                <select id="game_id" name="game_id" required>
                    <option value="">— Chọn game —</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected(old('game_id', $quiz->game_id) == $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                @error('game_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="keyboard_id">Bàn phím *</label>
                <select id="keyboard_id" name="keyboard_id" required>
                    <option value="">— Chọn bàn phím —</option>
                    @foreach ($keyboards as $keyboard)
                        <option value="{{ $keyboard->id }}" @selected(old('keyboard_id', $quiz->keyboard_id) == $keyboard->id)>{{ $keyboard->name }}</option>
                    @endforeach
                </select>
                @error('keyboard_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label for="name">Tên quiz *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $quiz->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="tags_input">Chủ đề (tag)</label>
            <input
                type="text"
                id="tags_input"
                name="tags_input"
                value="{{ $tagsValue }}"
                placeholder="Ví dụ: Hóa vô cơ, Hóa hữu cơ, Lớp 10"
                list="quiz-tag-suggestions"
            >
            <datalist id="quiz-tag-suggestions">
                @foreach ($allTags as $tagName)
                    <option value="{{ $tagName }}"></option>
                @endforeach
            </datalist>
            <p class="hint">Phân cách bằng dấu phẩy. Một quiz có thể thuộc nhiều chủ đề.</p>
            @error('tags_input')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="subject">Môn</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject', $quiz->subject ?? 'chemistry') }}">
            </div>
            <div class="form-group">
                <label for="grade">Lớp</label>
                <input type="text" id="grade" name="grade" value="{{ old('grade', $quiz->grade) }}" placeholder="10, 11, 12...">
            </div>
        </div>

        <div class="form-group">
            <label for="sort_order">Thứ tự trong game</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $quiz->sort_order ?? 0) }}" style="max-width:160px;">
        </div>

        <button type="submit" class="btn btn-primary">Lưu thông tin quiz</button>
    </form>
</div>

<div class="card quiz-detail-section">
    <div class="section-header">
        <h3>Câu hỏi ({{ $quiz->questions->count() }})</h3>
        <div class="section-header-actions">
            <a href="{{ route('admin.questions.create', $quiz) }}" class="btn btn-primary btn-sm">+ Thêm câu hỏi</a>
        </div>
    </div>

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
                    <th>Điểm</th>
                    <th>Thời gian</th>
                    <th>Bật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quiz->questions as $question)
                @php $questionActive = $question->is_active ?? true; @endphp
                <tr class="{{ $questionActive ? '' : 'row-inactive' }}">
                    <td>{{ $question->sort_order }}</td>
                    <td>{{ $question->answer_type === 'mc' ? 'Trắc nghiệm' : 'Tự luận' }}</td>
                    <td>{!! Str::limit(strip_tags($question->content), 80) !!}</td>
                    <td>{{ $question->points ?? 1 }}</td>
                    <td>{{ $question->time_limit_seconds }}s</td>
                    <td>
                        @include('admin.partials.toggle-switch', [
                            'formAction' => route('admin.questions.toggle-active', [$quiz, $question]),
                            'checked' => $questionActive,
                            'submitOnChange' => true,
                            'label' => 'Bật/tắt câu hỏi',
                        ])
                    </td>
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

@push('head')
@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@endpush
@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/quiz-preview.js') }}?v={{ file_exists($qpJs) ? filemtime($qpJs) : $qpV }}"></script>
@endpush
