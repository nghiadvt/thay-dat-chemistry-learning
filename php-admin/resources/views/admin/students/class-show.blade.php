@extends('layouts.admin')

@section('title', $class->name.' — Hóa Thầy Đạt')

@php
    $hue = crc32($class->name) % 360;
    $hueOf = fn (string $seed) => crc32($seed) % 360;
    $initialsOf = function (string $name) {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2));
        return mb_strtoupper(implode('', $letters) ?: '?');
    };
    $lockedCount = $students->where('status', 'locked')->count();
@endphp

@section('content')
<div class="stu-hero" style="--hue: {{ $hue }}">
    <span class="stu-hero__tile">{{ mb_strtoupper(mb_substr($class->name, 0, 4)) }}</span>
    <div class="stu-hero__text">
        <a class="stu-hero__back" href="{{ route('admin.students.index') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
            Các lớp
        </a>
        <h2>{{ $class->name }}</h2>
        <p class="stu-hero__meta">
            <strong>{{ $students->count() }}</strong> học sinh
            @if ($class->grade) · Khối {{ $class->grade }} @endif
            @if ($lockedCount) · <strong style="color:var(--stu-danger)">{{ $lockedCount }} bị khóa</strong> @endif
        </p>
    </div>
    <div class="stu-hero__actions">
        @if ($students->isNotEmpty())
            <a class="btn" href="{{ route('admin.students.print-cards', $class) }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-12-3h12v6H6z"/></svg>
                In phiếu tài khoản
            </a>
        @endif
        <button type="button" class="btn btn-primary" onclick="document.getElementById('addStudents').showModal()">＋ Thêm học sinh</button>
    </div>
</div>

@include('admin.students.partials.generated-credentials')

<div class="stu-panel" @if($students->isNotEmpty()) style="padding:8px 16px" @endif>
    @if ($students->isEmpty())
        <div class="stu-empty">
            <div class="stu-empty__icon">🎒</div>
            <h3>Lớp chưa có học sinh</h3>
            <p>Dán danh sách họ tên cả lớp — tên đăng nhập và mật khẩu sẽ được tạo tự động,<br>in phiếu phát cho học sinh là dùng được ngay.</p>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('addStudents').showModal()">＋ Thêm học sinh</button>
        </div>
    @else
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>Học sinh</th>
                    <th>Tên đăng nhập</th>
                    <th>Trạng thái</th>
                    <th style="text-align:right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td style="color:var(--stu-dim)">{{ $loop->iteration }}</td>
                        <td>
                            <span class="stu-name-cell">
                                <span class="stu-avatar" style="--hue: {{ $hueOf($student->display_name.$student->id) }}">{{ $initialsOf($student->display_name) }}</span>
                                <strong>{{ $student->display_name }}</strong>
                            </span>
                        </td>
                        <td><code>{{ $student->username }}</code></td>
                        <td>
                            @if ($student->isLocked())
                                <span class="stu-status stu-status--locked">Bị khóa</span>
                            @elseif ($student->status === 'disabled')
                                <span class="stu-status stu-status--disabled">Ngừng dùng</span>
                            @else
                                <span class="stu-status">Đang dùng</span>
                            @endif
                        </td>
                        <td class="row-actions">
                            @if ($student->isLocked())
                                <form method="POST" action="{{ route('admin.students.unlock', $student) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Mở khóa</button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-sm"
                                    onclick="openStudentReports('{{ route('admin.students.reports', $student) }}')">Thống kê</button>
                            <a class="btn btn-sm" href="{{ route('admin.students.edit', $student) }}">Sửa</a>
                            <details class="row-menu">
                                <summary class="btn btn-sm" aria-label="Thao tác khác với {{ $student->display_name }}">⋯</summary>
                                <div class="row-menu__panel">
                                    <form method="POST" action="{{ route('admin.students.reset-password', $student) }}">
                                        @csrf
                                        <button type="submit">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                            Đặt lại mật khẩu
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.students.entitlements', $student) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l8 4v5c0 4.5-3.2 7.6-8 9-4.8-1.4-8-4.5-8-9V7z"/></svg>
                                        Quyền tính năng
                                    </a>
                                    <div class="row-menu__sep"></div>
                                    <form method="POST" action="{{ route('admin.students.destroy', $student) }}"
                                          onsubmit="return confirm('Xóa {{ $student->display_name }} khỏi lớp? Lịch sử bài làm vẫn được giữ lại.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="row-menu__danger">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg>
                                            Xóa học sinh
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Đổi tên / xóa lớp: giấu xuống cuối vì hiếm khi cần --}}
<details class="class-settings">
    <summary>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
        Đổi tên hoặc xóa lớp
    </summary>
    <div class="class-settings__body">
        <form method="POST" action="{{ route('admin.students.classes.update', $class) }}" class="class-settings__rename">
            @csrf @method('PUT')
            <input type="text" name="name" value="{{ $class->name }}" required maxlength="100" aria-label="Tên lớp">
            <input type="hidden" name="grade" value="{{ $class->grade }}">
            <button type="submit" class="btn btn-sm">Lưu tên mới</button>
        </form>
        <form method="POST" action="{{ route('admin.students.classes.destroy', $class) }}"
              onsubmit="return confirm('Xóa lớp {{ $class->name }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger" @disabled($students->isNotEmpty())>Xóa lớp</button>
            @if ($students->isNotEmpty())
                <small style="color:var(--stu-dim)">Chỉ xóa được khi lớp không còn học sinh.</small>
            @endif
        </form>
    </div>
</details>

{{-- Hộp thoại thêm học sinh: dán danh sách tên hoặc nhập số lượng --}}
<dialog id="addStudents" class="add-students">
    <form method="POST" action="{{ route('admin.students.bulk-generate') }}" data-mode="names">
        @csrf
        <input type="hidden" name="class_id" value="{{ $class->id }}">
        <h3>Thêm học sinh vào lớp {{ $class->name }}</h3>
        <p class="add-students__sub">Tên đăng nhập và mật khẩu được tạo tự động cho từng em.</p>

        <div class="stu-segment" role="group" aria-label="Cách thêm học sinh">
            <button type="button" data-mode-btn="names" aria-pressed="true">Dán danh sách tên</button>
            <button type="button" data-mode-btn="quantity" aria-pressed="false">Chỉ nhập số lượng</button>
        </div>

        <div data-mode-panel="names" class="is-active">
            <label>
                Mỗi dòng một học sinh
                <textarea name="names" placeholder="Nguyễn Văn An&#10;Trần Thị Bình&#10;Lê Văn Cường"></textarea>
            </label>
        </div>
        <div data-mode-panel="quantity">
            <label>
                Số tài khoản cần tạo
                <input type="number" name="quantity" min="1" max="60" placeholder="10">
            </label>
            <p class="add-students__note">Tên hiển thị sẽ là «{{ $class->name }} - 1», «{{ $class->name }} - 2»… — đổi tên từng em sau cũng được.</p>
        </div>

        <div class="add-students__actions">
            <span class="add-students__count" id="addStudentsCount" aria-live="polite"></span>
            <button type="button" class="btn" onclick="document.getElementById('addStudents').close()">Hủy</button>
            <button type="submit" class="btn btn-primary" id="addStudentsSubmit">Tạo tài khoản</button>
        </div>
    </form>
</dialog>

@include('admin.students.partials.report-modal')

@push('scripts')
<script>
const addForm = document.querySelector('#addStudents form');
const namesInput = addForm.querySelector('[name="names"]');
const qtyInput = addForm.querySelector('[name="quantity"]');
const countLabel = document.getElementById('addStudentsCount');
const submitBtn = document.getElementById('addStudentsSubmit');

// Hai chế độ: dán danh sách tên / chỉ nhập số lượng.
addForm.querySelectorAll('[data-mode-btn]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const mode = btn.dataset.modeBtn;
        addForm.dataset.mode = mode;
        addForm.querySelectorAll('[data-mode-btn]').forEach((b) =>
            b.setAttribute('aria-pressed', String(b === btn)));
        addForm.querySelectorAll('[data-mode-panel]').forEach((p) =>
            p.classList.toggle('is-active', p.dataset.modePanel === mode));
        updateCount();
    });
});

function countNames() {
    return namesInput.value.split('\n').map((s) => s.trim()).filter(Boolean).length;
}

// Đếm trực tiếp để giáo viên biết sẽ tạo bao nhiêu tài khoản.
function updateCount() {
    const n = addForm.dataset.mode === 'names' ? countNames() : Number(qtyInput.value || 0);
    countLabel.textContent = n > 0 ? `Sẽ tạo ${n} tài khoản` : '';
    submitBtn.textContent = n > 0 ? `Tạo ${n} tài khoản` : 'Tạo tài khoản';
}
namesInput.addEventListener('input', updateCount);
qtyInput.addEventListener('input', updateCount);

addForm.addEventListener('submit', (e) => {
    // Gửi đúng dữ liệu của chế độ đang chọn để server không nhận nhầm.
    if (addForm.dataset.mode === 'names') {
        qtyInput.value = '';
        if (countNames() === 0) {
            e.preventDefault();
            namesInput.focus();
        } else if (countNames() > 60) {
            e.preventDefault();
            alert('Mỗi lần chỉ tạo được tối đa 60 học sinh. Hãy chia danh sách thành nhiều lần.');
        }
    } else {
        namesInput.value = '';
        if (!qtyInput.value) {
            e.preventDefault();
            qtyInput.focus();
        }
    }
});

// Mở menu ⋯ nào thì đóng các menu khác; bấm ra ngoài là đóng hết.
document.addEventListener('click', (e) => {
    document.querySelectorAll('.row-menu[open]').forEach((menu) => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
});
</script>
@endpush
@endsection
