@extends('layouts.admin')

@section('title', ($keyboard ? 'Sửa' : 'Tạo').' bàn phím — Hóa Thầy Đạt')
@section('page-title', $keyboard ? 'Sửa bàn phím' : 'Tạo bàn phím')

@section('content')
<div class="page-header">
    <h2>{{ $keyboard ? 'Sửa: '.$keyboard->name : 'Tạo bàn phím mới' }}</h2>
    <a href="{{ route('admin.keyboards.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ $keyboard ? route('admin.keyboards.update', $keyboard) : route('admin.keyboards.store') }}">
        @csrf
        @if ($keyboard) @method('PUT') @endif

        <div class="form-row">
            <div class="form-group">
                <label for="name">Tên bàn phím *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $keyboard?->name) }}" required>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="subject">Môn học</label>
                <select id="subject" name="subject">
                    @foreach (['chemistry' => 'Hóa học', 'physics' => 'Vật lý', 'math' => 'Toán'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('subject', $keyboard?->subject ?? 'chemistry') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="config_json">Cấu hình JSON (theo KEYBOARD_SCHEMA) *</label>
            <p class="hint">
                Chỉnh trực quan tại <a href="/app/keyboard-editor.html" target="_blank">trình chỉnh sửa bàn phím</a>,
                bấm Export rồi dán JSON vào đây (bỏ các field <code>id</code>, <code>name</code>, <code>updatedAt</code> ở root nếu có).
            </p>
            <textarea id="config_json" name="config_json" class="code" required>{{ old('config_json', $configJson) }}</textarea>
            @error('config_json')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ $keyboard ? 'Cập nhật' : 'Tạo bàn phím' }}</button>
    </form>
</div>
@endsection
