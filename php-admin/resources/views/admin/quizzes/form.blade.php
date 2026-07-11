@extends('layouts.admin')

@section('title', 'Tạo quiz — Hóa Thầy Đạt')
@section('page-title', 'Tạo quiz')

@section('content')
<div class="page-header">
    <h2>Tạo quiz mới</h2>
    <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.quizzes.store') }}">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label for="game_id">Game *</label>
                <select id="game_id" name="game_id" required>
                    <option value="">— Chọn game —</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected(old('game_id') == $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                @error('game_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            @include('admin.partials.keyboard-select-with-preview', [
                'keyboards' => $keyboards,
                'selectedKeyboardId' => old('keyboard_id'),
            ])
        </div>

        <div class="form-group">
            <label for="name">Tên quiz *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            @include('admin.partials.tag-select', [
                'mode' => 'multi',
                'tags' => $bankTags,
                'selected' => $selectedQuizTagIds ?? [],
                'label' => 'Chủ đề (tag)',
            ])
            @error('tag_ids')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="subject">Môn</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject', 'chemistry') }}">
            </div>
            <div class="form-group">
                <label for="grade">Lớp</label>
                <input type="text" id="grade" name="grade" value="{{ old('grade') }}" placeholder="10, 11, 12...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sort_order">Thứ tự</label>
                <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', 0) }}">
            </div>
            <div class="form-group">
                <label>Kích hoạt</label>
                <div class="toggle-field">
                    <input type="hidden" name="is_active" value="0">
                    @include('admin.partials.toggle-switch', [
                        'name' => 'is_active',
                        'checked' => (bool) old('is_active', true),
                        'label' => 'Kích hoạt quiz',
                    ])
                    <span class="toggle-field-label">Hiển thị khi chơi game</span>
                </div>
            </div>
        </div>

        <div class="form-group quiz-play-settings">
            <label>Tùy chọn khi chơi</label>
            <div class="toggle-field">
                <input type="hidden" name="show_explanation" value="0">
                @include('admin.partials.toggle-switch', [
                    'name' => 'show_explanation',
                    'checked' => (bool) old('show_explanation', false),
                    'label' => 'Hiển thị giải thích đáp án',
                ])
                <span class="toggle-field-label">Hiển thị giải thích đáp án sau khi học sinh trả lời</span>
            </div>
            <div class="toggle-field">
                <input type="hidden" name="shuffle_options" value="0">
                @include('admin.partials.toggle-switch', [
                    'name' => 'shuffle_options',
                    'checked' => (bool) old('shuffle_options', false),
                    'label' => 'Xáo trộn đáp án trắc nghiệm',
                ])
                <span class="toggle-field-label">Xáo trộn thứ tự đáp án trắc nghiệm (mỗi học sinh khác nhau)</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Tạo quiz</button>
    </form>
</div>

@include('admin.partials.keyboard-preview-lightbox')
@endsection

@push('scripts')
<script src="@vasset('htd-admin/js/admin-keyboard-preview.js')"></script>
@endpush
