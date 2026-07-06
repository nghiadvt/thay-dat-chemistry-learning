@extends('layouts.admin')

@section('title', ($quiz ? 'Sửa' : 'Tạo').' quiz — Hóa Thầy Đạt')
@section('page-title', $quiz ? 'Sửa quiz' : 'Tạo quiz')

@section('content')
<div class="page-header">
    <h2>{{ $quiz ? 'Sửa: '.$quiz->name : 'Tạo quiz mới' }}</h2>
    <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ $quiz ? route('admin.quizzes.update', $quiz) : route('admin.quizzes.store') }}">
        @csrf
        @if ($quiz) @method('PUT') @endif

        <div class="form-row">
            <div class="form-group">
                <label for="game_id">Game *</label>
                <select id="game_id" name="game_id" required>
                    <option value="">— Chọn game —</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected(old('game_id', $quiz?->game_id) == $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                @error('game_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="keyboard_id">Bàn phím *</label>
                <select id="keyboard_id" name="keyboard_id" required>
                    <option value="">— Chọn bàn phím —</option>
                    @foreach ($keyboards as $keyboard)
                        <option value="{{ $keyboard->id }}" @selected(old('keyboard_id', $quiz?->keyboard_id) == $keyboard->id)>{{ $keyboard->name }}</option>
                    @endforeach
                </select>
                @error('keyboard_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label for="name">Tên quiz *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $quiz?->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="subject">Môn</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject', $quiz?->subject ?? 'chemistry') }}">
            </div>
            <div class="form-group">
                <label for="grade">Lớp</label>
                <input type="text" id="grade" name="grade" value="{{ old('grade', $quiz?->grade) }}" placeholder="10, 11, 12...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sort_order">Thứ tự</label>
                <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $quiz?->sort_order ?? 0) }}">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="checkbox-row">
                    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $quiz?->is_active ?? true))>
                    <label for="is_active" style="margin:0;font-weight:500;">Kích hoạt (dùng khi tạo phòng)</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{{ $quiz ? 'Cập nhật' : 'Tạo quiz' }}</button>
    </form>
</div>
@endsection
