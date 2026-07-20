<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phiếu tài khoản — {{ $class->name }}</title>
    @php
        // Lưới nhiều cột trong dompdf rất hay lỗi: float làm chữ đè lên
        // nhau giữa các cột, <table> bị ước tính sai chiều cao nên ngắt
        // trang sớm dù còn thừa chỗ, inline-block thì không chịu xếp cạnh
        // nhau. Cách chắc chắn nhất là tự tính toạ độ mm rồi đặt từng thẻ
        // bằng position:absolute — dompdf vẽ đúng vị trí tuyệt đối rất ổn
        // định vì không phải tự ước tính flow/ngắt trang gì cả.
        $pageWidthMm = 210;
        $marginMm = 10;
        $usableWidthMm = $pageWidthMm - ($marginMm * 2);
        $columns = $template['columns'];
        $gapMm = 4;
        $cardWidthMm = ($usableWidthMm - ($gapMm * ($columns - 1))) / $columns;
        $cardHeightMm = $template['cardHeightMm'];
    @endphp
    <style>
        @page { margin: {{ $marginMm }}mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', system-ui, sans-serif;
            margin: 0;
            color: #111;
        }

        .sheet-head { margin-bottom: 6mm; }
        .sheet-head h1 { font-size: 13pt; margin: 0 0 2px; color: {{ $template['accent'] }}; }
        .sheet-head p { font-size: 8pt; color: #666; margin: 0; }

        .grid { position: relative; }
        .card {
            position: absolute;
            width: {{ $cardWidthMm }}mm;
            height: {{ $cardHeightMm }}mm;
            padding: 5mm 6mm;
            overflow: hidden;
        }

        /* ── modern: thẻ bo tròn, dải màu ở đầu ───────────────────── */
        .tpl-modern .card {
            border: 1pt solid #e2e2e6;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .tpl-modern .card__bar {
            display: block;
            height: 6mm;
            margin: -5mm -6mm 5mm;
            background: {{ $template['accent'] }};
            border-radius: 10px 10px 0 0;
        }
        .tpl-modern .card__name { font-size: 12.5pt; font-weight: 700; margin: 0 0 4mm; color: #111; }

        /* ── classic: khung viền đơn, gạch chân tiêu đề ───────────── */
        .tpl-classic .card {
            border: 1.4pt solid {{ $template['accent'] }};
            border-radius: 4px;
        }
        .tpl-classic .card__bar { display: none; }
        .tpl-classic .card__name {
            font-size: 11pt; font-weight: 700; margin: 0 0 3mm; color: {{ $template['accent'] }};
            padding-bottom: 2mm; border-bottom: 0.8pt solid #cbd5e1;
        }

        /* ── minimal: đen trắng, viền đứt để cắt ──────────────────── */
        .tpl-minimal .card {
            border: 1pt dashed #9ca3af;
            border-radius: 0;
        }
        .tpl-minimal .card__bar { display: none; }
        .tpl-minimal .card__name { font-size: 9.5pt; font-weight: 700; margin: 0 0 2mm; color: #111; }

        .card__row { font-size: 7.5pt; color: #777; margin: 0 0 0.5mm; text-transform: uppercase; letter-spacing: 0.4pt; }
        .card__value {
            font-family: 'DejaVu Sans Mono', ui-monospace, monospace;
            font-size: 10.5pt; font-weight: 700;
            background: #f4f4f6; border-radius: 3px;
            padding: 1.6mm 2.6mm; margin: 0 0 2.6mm;
            display: block; letter-spacing: 0.4pt;
        }
        .tpl-minimal .card__value { background: none; border: 0.8pt solid #d1d5db; padding: 1.2mm 2mm; font-size: 9.5pt; }
    </style>
</head>
<body class="tpl-{{ $template['key'] }}">
    <div class="sheet-head">
        <h1>Phiếu tài khoản — {{ $class->name }}</h1>
        <p>Mẫu {{ $template['name'] }} · {{ $rows->count() }} thẻ trên trang này · In ngày {{ now()->format('d/m/Y') }}</p>
    </div>

    <div class="grid">
        @foreach ($rows as $row)
            @php
                $col = $loop->index % $columns;
                $rowIndex = intdiv($loop->index, $columns);
                $left = $col * ($cardWidthMm + $gapMm);
                $top = $rowIndex * ($cardHeightMm + $gapMm);
            @endphp
            <div class="card" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                <span class="card__bar"></span>
                <div class="card__name">{{ $row['student']->display_name }}</div>
                <div class="card__row">Tên đăng nhập</div>
                <div class="card__value">{{ $row['student']->username }}</div>
                <div class="card__row">Mật khẩu</div>
                <div class="card__value">{{ $row['password'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
