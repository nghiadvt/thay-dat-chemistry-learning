<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phiếu tài khoản — {{ $class->name }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p.meta { color: #666; margin: 0 0 16px; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .slip { border: 1px solid #bbb; border-radius: 6px; padding: 10px 12px; page-break-inside: avoid; }
        .slip h2 { font-size: 14px; margin: 0 0 6px; }
        .slip dl { margin: 0; font-size: 13px; display: grid; grid-template-columns: 110px 1fr; row-gap: 3px; }
        .slip dt { color: #666; }
        .slip dd { margin: 0; font-family: ui-monospace, monospace; font-weight: 600; }
        @media print { .no-print { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    <h1>Phiếu tài khoản — {{ $class->name }}</h1>
    <p class="meta">
        {{ $students->count() }} học sinh · In ngày {{ now()->format('d/m/Y H:i') }} ·
        Cắt rời từng ô để phát cho học sinh.
    </p>
    <p class="no-print"><button onclick="window.print()">In phiếu</button></p>

    <div class="grid">
        @foreach ($students as $row)
            <div class="slip">
                <h2>{{ $row['student']->display_name }}</h2>
                <dl>
                    <dt>Tên đăng nhập</dt><dd>{{ $row['student']->username }}</dd>
                    <dt>Mật khẩu</dt><dd>{{ $row['password'] }}</dd>
                    <dt>Mã code</dt><dd>{{ $row['student']->student_code }}</dd>
                </dl>
            </div>
        @endforeach
    </div>
</body>
</html>
