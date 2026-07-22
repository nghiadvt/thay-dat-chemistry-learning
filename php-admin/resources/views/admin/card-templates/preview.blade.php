<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Xem trước thẻ</title>
    <style>
        {!! $fontCss !!}
        * { box-sizing: border-box; }
        body { margin: 16px; background: #f3f4f6; font-family: system-ui, sans-serif; }
        .preview-wrap { display: inline-block; padding: 12px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    </style>
</head>
<body>
    <div class="preview-wrap">
        @include('admin.card-templates._card', compact('elements', 'imageDataUri', 'frameWidthMm', 'frameHeightMm', 'scaleK', 'data'))
    </div>
</body>
</html>
