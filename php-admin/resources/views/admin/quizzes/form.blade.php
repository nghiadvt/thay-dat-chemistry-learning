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
            <div class="form-group">
                <label for="keyboard_id">Bàn phím *</label>
                <select id="keyboard_id" name="keyboard_id" required>
                    <option value="">— Chọn bàn phím —</option>
                    @foreach ($keyboards as $keyboard)
                        <option value="{{ $keyboard->id }}" @selected(old('keyboard_id') == $keyboard->id)>{{ $keyboard->name }}</option>
                    @endforeach
                </select>
                @error('keyboard_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label for="name">Tên quiz *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="tags_input">Chủ đề (tag)</label>
            <input type="text" id="tags_input" name="tags_input" value="{{ old('tags_input') }}" placeholder="Ví dụ: Hóa vô cơ, Hóa hữu cơ" list="quiz-tag-suggestions">
            <datalist id="quiz-tag-suggestions">
                @foreach ($allTags as $tagName)
                    <option value="{{ $tagName }}"></option>
                @endforeach
            </datalist>
            <p class="hint">Phân cách bằng dấu phẩy.</p>
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

        <button type="submit" class="btn btn-primary">Tạo quiz</button>
    </form>
</div>
@endsection
