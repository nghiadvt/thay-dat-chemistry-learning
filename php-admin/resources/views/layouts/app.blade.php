<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Hóa Thầy Đạt Admin')</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        .container { max-width: 960px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #2D46D6; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input[type=email], input[type=password] { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; }
        .error { color: #dc2626; margin-bottom: 12px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        ul.endpoints { line-height: 1.8; }
    </style>
</head>
<body>
<div class="container">
    @yield('content')
</div>
</body>
</html>
