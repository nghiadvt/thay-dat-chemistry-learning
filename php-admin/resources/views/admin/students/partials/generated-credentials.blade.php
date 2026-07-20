@if (session('generated_credentials'))
    <div class="stu-credentials">
        <div class="stu-credentials__head">
            <span class="stu-credentials__badge" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 13 4 4L19 7"/></svg>
            </span>
            <h3>Đã tạo {{ count(session('generated_credentials')) }} tài khoản — lưu lại mật khẩu bên dưới</h3>
        </div>
        <p class="stu-credentials__hint">
            Mật khẩu chỉ hiển thị ở đây. Hãy in phiếu hoặc chép lại trước khi rời trang —
            sau này vẫn xem lại được qua <a href="{{ route('admin.students.password-tool') }}">công cụ mật khẩu</a>.
        </p>
        <table class="admin-table">
            <thead>
                <tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Mã code</th><th>Mật khẩu</th></tr>
            </thead>
            <tbody>
                @foreach (session('generated_credentials') as $row)
                    <tr>
                        <td>{{ $row['display_name'] ?? '—' }}</td>
                        <td><code>{{ $row['username'] }}</code></td>
                        <td><code>{{ $row['code'] }}</code></td>
                        <td><code>{{ $row['password'] }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
