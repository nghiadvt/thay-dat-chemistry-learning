@extends('layouts.admin')

@section('title', 'Công cụ mật khẩu học sinh — Hóa Thầy Đạt')
@section('page-title', 'Công cụ mật khẩu học sinh')

@section('content')
@php $result = session('tool_result'); @endphp

<div class="page-header">
    <div class="page-header__text">
        <h2>Mã hóa / giải mã mật khẩu</h2>
        <p class="page-header__meta">
            Mọi thao tác ở đây đều được ghi nhật ký. Trang chỉ làm việc với học sinh bạn quản lý.
        </p>
    </div>
</div>

@if (session('error'))
    <div class="card" style="border-left:4px solid #d9534f;margin-bottom:16px">{{ session('error') }}</div>
@endif

@if ($result)
    <div class="card" style="border-left:4px solid #5cb85c;margin-bottom:16px">
        <h3 style="margin-top:0">Kết quả</h3>
        <p>Học sinh: <code>{{ $result['student'] }}</code> · Mã code: <code>{{ $result['code'] }}</code></p>
        @if (isset($result['password']))
            <p>Mật khẩu: <code style="font-size:16px">{{ $result['password'] }}</code></p>
        @endif
        @if (isset($result['payload']))
            <p>Chuỗi mã hóa:</p>
            <textarea readonly rows="3" style="width:100%">{{ $result['payload'] }}</textarea>
            @if ($result['applied'] ?? false)
                <p><strong>Đã áp dụng.</strong> Học sinh đăng nhập được bằng mật khẩu vừa nhập.</p>
            @else
                <p class="page-header__meta">Chưa áp dụng — mật khẩu đăng nhập của học sinh chưa thay đổi.</p>
            @endif
        @endif
    </div>
@endif

<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">1. Giải mã — có mã code + chuỗi mã hóa, tìm ra mật khẩu</h3>
    <form method="POST" action="{{ route('admin.students.password-tool.decrypt') }}" class="form-grid">
        @csrf
        <label>Mã code học sinh<input type="text" name="student_code" placeholder="HS-ABC123" required></label>
        <label>Chuỗi mã hóa<textarea name="payload" rows="3" required placeholder="HSP1...."></textarea></label>
        <button type="submit" class="btn btn-primary">Giải mã</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">2. Mã hóa — có mã code + mật khẩu, tạo ra chuỗi mã hóa</h3>
    <p class="page-header__meta">
        Tích “Áp dụng” khi bạn đã lỡ đưa cho học sinh một mật khẩu khác: hệ thống sẽ đặt
        chính mật khẩu đó làm mật khẩu đăng nhập thật (cập nhật đồng thời hash và chuỗi mã hóa).
    </p>
    <form method="POST" action="{{ route('admin.students.password-tool.encrypt') }}" class="form-grid">
        @csrf
        <label>Mã code học sinh<input type="text" name="student_code" placeholder="HS-ABC123" required></label>
        <label>Mật khẩu<input type="text" name="password" minlength="6" maxlength="64" required></label>
        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="apply" value="1">
            Áp dụng làm mật khẩu đăng nhập thật của học sinh
        </label>
        <button type="submit" class="btn btn-primary">Mã hóa</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">3. Dò mã code — chỉ có chuỗi mã hóa, tìm ra học sinh</h3>
    <p class="page-header__meta">
        Không thể suy ngược ra mã code bằng toán học, nên công cụ thử lần lượt trên các
        học sinh bạn quản lý; chỉ đúng mã code mới giải mã được.
    </p>
    <form method="POST" action="{{ route('admin.students.password-tool.scan') }}" class="form-grid">
        @csrf
        <label>Chuỗi mã hóa<textarea name="payload" rows="3" required></textarea></label>
        <button type="submit" class="btn btn-primary">Dò</button>
    </form>
</div>

<div class="card admin-list-card">
    <h3 style="margin:0 0 8px">Nhật ký gần đây</h3>
    <table class="admin-table">
        <thead><tr><th>Thời điểm</th><th>Học sinh</th><th>Người thực hiện</th><th>Thao tác</th><th>IP</th></tr></thead>
        <tbody>
            @forelse ($recentAudits as $audit)
                <tr>
                    <td>{{ $audit->created_at->format('d/m/Y H:i') }}</td>
                    <td><code>{{ $audit->student?->username ?? '—' }}</code></td>
                    <td>{{ $audit->user?->name ?? '—' }}</td>
                    <td>{{ $audit->action }}</td>
                    <td>{{ $audit->ip ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Chưa có thao tác nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
