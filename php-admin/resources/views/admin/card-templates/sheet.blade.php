<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phiếu tài khoản — {{ $class->name }}</title>
    @php
        /** @var \App\Models\CardTemplate $cardTemplate */
        /** @var list<array{data: array<string, mixed>}> $rows */
        $sideLabel = ($side ?? 'front') === 'back' ? 'Mặt sau' : 'Mặt trước';
    @endphp
    <style>
        {!! $fontCss !!}
        @page { margin: {{ $a4['marginMm'] }}mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DejaVu Sans', system-ui, sans-serif;
            color: #111;
        }
        .sheet-head { margin-bottom: 6mm; }
        .sheet-head h1 { font-size: 13pt; margin: 0 0 2px; color: #2D46D6; }
        .sheet-head p { font-size: 8pt; color: #666; margin: 0; }
        .grid { position: relative; }
        .card-slot { position: absolute; overflow: hidden; }
    </style>
</head>
<body>
    <div class="sheet-head">
        <h1>Phiếu tài khoản — {{ $class->name }}</h1>
        <p>{{ $cardTemplate->name }} · {{ $sideLabel }} · {{ count($rows) }} thẻ trên trang · In ngày {{ now()->format('d/m/Y') }}</p>
    </div>

    <div class="grid" style="width:{{ $a4['gridWidthMm'] }}mm;height:{{ $a4['gridHeightMm'] }}mm;">
        @foreach ($rows as $row)
            @php
                $col = $loop->index % $a4['cols'];
                $rowIndex = intdiv($loop->index, $a4['cols']);
                $left = $col * ($a4['cardWmm'] + $a4['gapMm']);
                $top = $rowIndex * ($a4['cardHmm'] + $a4['gapMm']);
            @endphp
            <div class="card-slot" style="left:{{ $left }}mm;top:{{ $top }}mm;width:{{ $a4['cardWmm'] }}mm;height:{{ $a4['cardHmm'] }}mm;">
                @include('admin.card-templates._card', [
                    'elements' => $elements,
                    'imageDataUri' => $imageDataUri,
                    'frameWidthMm' => (float) $cardTemplate->frame_width_mm,
                    'frameHeightMm' => (float) $cardTemplate->frame_height_mm,
                    'scaleK' => $a4['scaleK'],
                    'data' => $row['data'],
                ])
            </div>
        @endforeach
    </div>
</body>
</html>
