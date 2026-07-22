@php
    /** @var list<array<string, mixed>> $elements */
    $fw = (float) $frameWidthMm * (float) $scaleK;
    $fh = (float) $frameHeightMm * (float) $scaleK;
@endphp
<div class="htd-card" style="position:relative;width:{{ $fw }}mm;height:{{ $fh }}mm;overflow:hidden;background:#fff;">
    @if (!empty($imageDataUri))
        <img src="{{ $imageDataUri }}" alt="" style="position:absolute;left:0;top:0;width:100%;height:100%;object-fit:fill;">
    @endif
    @foreach ($elements as $el)
        @php
            $fontDef = \App\Support\CardFonts::find($el['fontFamily'] ?? 'be-vietnam-pro');
            $family = $fontDef['family'] ?? 'Be Vietnam Pro';
            $text = \App\Support\CardFonts::resolveBinding($el['binding'] ?? 'static', $data, $el['text'] ?? '');
            $left = ($el['x'] ?? 0) * $fw;
            $top = ($el['y'] ?? 0) * $fh;
            $width = ($el['w'] ?? 0.3) * $fw;
            $height = ($el['h'] ?? 0.1) * $fh;
            $fontSize = ($el['fontSizePt'] ?? 11) * (float) $scaleK;
            $weight = (int) ($el['fontWeight'] ?? 400);
            $italic = !empty($el['italic']) ? 'italic' : 'normal';
            $underline = !empty($el['underline']) ? 'underline' : 'none';
            $align = $el['align'] ?? 'left';
            $color = $el['color'] ?? '#111827';
            $lineHeight = $el['lineHeight'] ?? 1.2;
            $pad = (($el['paddingPx'] ?? 4) / 96) * 25.4 * (float) $scaleK;
            $bgColor = $el['bgColor'] ?? null;
            $bgOpacity = (float) ($el['bgOpacity'] ?? 1);
            $borderW = ($el['borderWidthPt'] ?? 0) * (float) $scaleK;
            $borderColor = $el['borderColor'] ?? '#000';
            $radius = (($el['borderRadiusPx'] ?? 0) / 96) * 25.4 * (float) $scaleK;
            $bgStyle = '';
            if ($bgColor) {
                $rgb = ltrim($bgColor, '#');
                if (strlen($rgb) === 6) {
                    $r = hexdec(substr($rgb, 0, 2));
                    $g = hexdec(substr($rgb, 2, 2));
                    $b = hexdec(substr($rgb, 4, 2));
                    $bgStyle = 'background:rgba('.$r.','.$g.','.$b.','.$bgOpacity.');';
                }
            }
        @endphp
        <div style="position:absolute;left:{{ $left }}mm;top:{{ $top }}mm;width:{{ $width }}mm;height:{{ $height }}mm;box-sizing:border-box;padding:{{ $pad }}mm;font-family:'{{ $family }}',sans-serif;font-size:{{ $fontSize }}pt;font-weight:{{ $weight }};font-style:{{ $italic }};text-decoration:{{ $underline }};color:{{ $color }};text-align:{{ $align }};line-height:{{ $lineHeight }};{{ $bgStyle }}border:{{ $borderW }}pt solid {{ $borderColor }};border-radius:{{ $radius }}mm;overflow:hidden;word-break:break-word;">
            {{ $text }}
        </div>
    @endforeach
</div>
