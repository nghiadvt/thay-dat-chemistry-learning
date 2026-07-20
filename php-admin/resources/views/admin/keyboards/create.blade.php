@extends('layouts.admin')

@section('title', 'Tạo bàn phím — Hóa Thầy Đạt')

@section('content')
<div class="page-header">
    <h2>Tạo bàn phím mới</h2>
    <a href="{{ route('admin.keyboards.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.keyboards.store') }}">
        @csrf
        <div class="form-row">
            <div class="form-group">
                <label for="name">Tên bàn phím *</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="subject">Môn học</label>
                <select id="subject" name="subject">
                    @foreach (['chemistry' => 'Hóa học', 'physics' => 'Vật lý', 'math' => 'Toán'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('subject', 'chemistry') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="hint">Sau khi tạo, bạn sẽ vào trình chỉnh sửa layout trực quan ngay trong admin.</p>
        <button type="submit" class="btn btn-primary">Tạo và mở trình chỉnh sửa</button>
    </form>
</div>
@endsection
