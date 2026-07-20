@extends('layouts.admin')

@section('title', 'Thêm học sinh — Hóa Thầy Đạt')

@section('content')
<div class="stu-panel" style="max-width:640px">
    <p class="stu-section-title">Thêm một học sinh <small>cần thêm cả lớp? Vào trang lớp rồi bấm «＋ Thêm học sinh»</small></p>
    <form method="POST" action="{{ route('admin.students.store') }}" class="form-grid">
        @csrf
        <label>
            Họ tên
            <input type="text" name="display_name" value="{{ old('display_name') }}" required maxlength="100" placeholder="Nguyễn Văn An">
        </label>
        <label>
            Tên đăng nhập <small>(chữ, số, gạch ngang — học sinh không đổi được)</small>
            <input type="text" name="username" value="{{ old('username') }}" required maxlength="64" placeholder="an10a1">
        </label>
        <label>
            Lớp
            <select name="class_id">
                <option value="">— Chưa xếp lớp —</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(old('class_id', request('class_id')) == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Mật khẩu <small>(để trống = hệ thống tự sinh)</small>
            <input type="text" name="password" minlength="6" maxlength="64">
        </label>
        <p class="stu-credentials__hint" style="margin:0">Mã code học sinh sẽ được sinh tự động và không thể thay đổi về sau.</p>
        <div class="page-header__actions">
            <button type="submit" class="btn btn-primary">Tạo học sinh</button>
            <a href="{{ route('admin.students.index') }}" class="btn">Hủy</a>
        </div>
    </form>
</div>
@endsection
