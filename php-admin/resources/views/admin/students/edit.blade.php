@extends('layouts.admin')

@section('title', 'Sửa học sinh — Hóa Thầy Đạt')

@section('content')
@include('admin.students.partials.generated-credentials')

<div class="stu-panel" style="max-width:640px">
    <p class="stu-section-title">{{ $student->display_name }}
        <small>mã code <code>{{ $student->student_code }}</code> — bất biến, dùng cho công cụ mật khẩu</small>
    </p>
    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="form-grid">
        @csrf @method('PUT')
        <label>
            Họ tên
            <input type="text" name="display_name" value="{{ old('display_name', $student->display_name) }}" required maxlength="100">
        </label>
        <label>
            Email <small>(tùy chọn — in lên phiếu tài khoản)</small>
            <input type="email" name="email" value="{{ old('email', $student->email) }}" maxlength="190" placeholder="an@example.com">
        </label>
        <label>
            Mô tả <small>(tùy chọn)</small>
            <textarea name="description" maxlength="1000" rows="3">{{ old('description', $student->description) }}</textarea>
        </label>
        <label>
            Tên đăng nhập
            <input type="text" name="username" value="{{ old('username', $student->username) }}" required maxlength="64">
        </label>
        <label>
            Lớp
            <select name="class_id">
                <option value="">— Chưa xếp lớp —</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(old('class_id', $student->class_id) == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Trạng thái
            <select name="status">
                @foreach (['active' => 'Đang dùng', 'locked' => 'Bị khóa'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="page-header__actions">
            <button type="submit" class="btn btn-primary">Lưu</button>
            <a href="{{ $student->class_id ? route('admin.students.classes.show', $student->class_id) : route('admin.students.index') }}" class="btn">Quay lại</a>
        </div>
    </form>
</div>

<div class="stu-panel" style="max-width:640px">
    <p class="stu-section-title">Mật khẩu <small>đặt lại nếu học sinh quên</small></p>
    <form method="POST" action="{{ route('admin.students.reset-password', $student) }}">
        @csrf
        <button type="submit" class="btn">Đặt lại mật khẩu ngẫu nhiên</button>
    </form>
</div>
@endsection
