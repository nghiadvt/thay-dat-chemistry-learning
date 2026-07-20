@extends('layouts.admin')

@section('title', 'Học sinh — Hóa Thầy Đạt')
@section('page-title', 'Học sinh')

@php
    // Màu ổn định theo tên lớp để mỗi lớp có một nhận diện riêng.
    $hueOf = fn (string $seed) => crc32($seed) % 360;
    $initialsOf = function (string $name) {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2));
        return mb_strtoupper(implode('', $letters) ?: '?');
    };
@endphp

@section('content')
@include('admin.students.partials.generated-credentials')

{{-- Tìm nhanh một học sinh khi không nhớ em đó ở lớp nào --}}
<form method="GET" class="student-search" role="search">
    <div class="student-search__box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Tìm học sinh theo tên, tên đăng nhập hoặc mã code…"
               aria-label="Tìm học sinh">
    </div>
    <button type="submit" class="btn btn-primary">Tìm</button>
    @if ($search !== '')
        <a href="{{ route('admin.students.index') }}" class="btn">Xem các lớp</a>
    @endif
</form>

@if ($results !== null)
    <div class="stu-panel">
        <p class="stu-section-title">Kết quả tìm kiếm <small>{{ $results->count() }} học sinh khớp «{{ $search }}»</small></p>
        @if ($results->isEmpty())
            <div class="stu-empty">
                <div class="stu-empty__icon">🔍</div>
                <h3>Không tìm thấy học sinh nào</h3>
                <p>Thử gõ ngắn hơn, hoặc tìm theo tên đăng nhập / mã code in trên phiếu tài khoản.</p>
                <a href="{{ route('admin.students.index') }}" class="btn">Quay về danh sách lớp</a>
            </div>
        @else
            <table class="admin-table">
                <thead>
                    <tr><th>Học sinh</th><th>Tên đăng nhập</th><th>Lớp</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($results as $student)
                        <tr>
                            <td>
                                <span class="stu-name-cell">
                                    <span class="stu-avatar" style="--hue: {{ $hueOf($student->display_name.$student->id) }}">{{ $initialsOf($student->display_name) }}</span>
                                    <strong>{{ $student->display_name }}</strong>
                                </span>
                            </td>
                            <td><code>{{ $student->username }}</code></td>
                            <td>
                                @if ($student->studentClass)
                                    <a href="{{ route('admin.students.classes.show', $student->studentClass) }}">{{ $student->studentClass->name }}</a>
                                @else
                                    <span class="stu-status stu-status--disabled">Chưa xếp lớp</span>
                                @endif
                            </td>
                            <td class="row-actions">
                                <a class="btn btn-sm" href="{{ route('admin.students.edit', $student) }}">Sửa</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@else
    {{-- Mỗi lớp một thẻ; bấm vào thẻ để quản lý học sinh của lớp đó --}}
    <div class="class-grid">
        @foreach ($classes as $class)
            <a class="class-card" href="{{ route('admin.students.classes.show', $class) }}"
               style="--hue: {{ $hueOf($class->name) }}; --i: {{ $loop->index }}">
                <span class="class-card__top">
                    <span class="class-card__tile">{{ mb_strtoupper(mb_substr($class->name, 0, 4)) }}</span>
                    <span>
                        <span class="class-card__name">{{ $class->name }}</span>
                        @if ($class->grade)
                            <span class="class-card__grade">Khối {{ $class->grade }}</span>
                        @endif
                    </span>
                </span>
                <span class="class-card__foot">
                    <span>{{ $class->students_count }} học sinh</span>
                    <svg class="class-card__arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 12h14m-6-6 6 6-6 6"/>
                    </svg>
                </span>
            </a>
        @endforeach

        <form method="POST" action="{{ route('admin.students.classes.store') }}"
              class="class-card class-card--new" style="--i: {{ $classes->count() }}">
            @csrf
            <span class="class-card__name">＋ Thêm lớp mới</span>
            <input type="text" name="name" placeholder="Tên lớp, ví dụ: 10A1" required maxlength="100"
                   aria-label="Tên lớp mới">
            <button type="submit" class="btn btn-primary btn-sm">Tạo lớp</button>
        </form>
    </div>

    @if ($classes->isEmpty())
        <div class="stu-panel stu-empty" style="margin-top:20px">
            <div class="stu-empty__icon">🏫</div>
            <h3>Bắt đầu bằng việc tạo lớp đầu tiên</h3>
            <p>Nhập tên lớp vào ô phía trên. Tạo lớp xong là thêm được học sinh ngay trong lớp —<br>chỉ cần dán danh sách họ tên, tài khoản sẽ được tạo tự động.</p>
        </div>
    @endif

    @if ($unassigned->isNotEmpty())
        <div class="stu-panel" style="margin-top:24px">
            <p class="stu-section-title">Chưa xếp lớp <small>{{ $unassigned->count() }} học sinh</small></p>
            <p class="stu-credentials__hint">Bấm «Sửa» rồi chọn lớp để đưa học sinh vào lớp.</p>
            <table class="admin-table">
                <tbody>
                    @foreach ($unassigned as $student)
                        <tr>
                            <td>
                                <span class="stu-name-cell">
                                    <span class="stu-avatar" style="--hue: {{ $hueOf($student->display_name.$student->id) }}">{{ $initialsOf($student->display_name) }}</span>
                                    <strong>{{ $student->display_name }}</strong>
                                </span>
                            </td>
                            <td><code>{{ $student->username }}</code></td>
                            <td class="row-actions">
                                <a class="btn btn-sm" href="{{ route('admin.students.edit', $student) }}">Sửa</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="student-tools-links">
        <a href="{{ route('admin.students.create') }}">Thêm học sinh lẻ</a>
        &nbsp;·&nbsp;
        <a href="{{ route('admin.students.password-tool') }}">Công cụ xem lại mật khẩu</a>
    </p>
@endif
@endsection
