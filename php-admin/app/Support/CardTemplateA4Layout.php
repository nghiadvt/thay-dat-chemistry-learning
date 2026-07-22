<?php

namespace App\Support;

use App\Models\CardTemplate;

/**
 * Tính lưới auto-tile A4 cho mẫu thẻ tùy chỉnh.
 */
class CardTemplateA4Layout
{
    private const PAGE_W_MM = 210.0;

    private const PAGE_H_MM = 297.0;

    /**
     * @param  array{marginMm?: float, gapMm?: float, cardWidthMm?: float}  $a4
     * @return array{
     *   marginMm: float,
     *   gapMm: float,
     *   cardWmm: float,
     *   cardHmm: float,
     *   scaleK: float,
     *   cols: int,
     *   rows: int,
     *   perSheet: int,
     *   gridWidthMm: float,
     *   gridHeightMm: float
     * }
     */
    public static function compute(float $frameWidthMm, float $frameHeightMm, array $a4): array
    {
        $marginMm = max(0.0, min(30.0, (float) ($a4['marginMm'] ?? 8)));
        $gapMm = max(0.0, min(20.0, (float) ($a4['gapMm'] ?? 4)));
        $cardWmm = max(20.0, min(100.0, (float) ($a4['cardWidthMm'] ?? 54)));

        $frameWidthMm = max(1.0, $frameWidthMm);
        $frameHeightMm = max(1.0, $frameHeightMm);

        $cardHmm = $cardWmm * ($frameHeightMm / $frameWidthMm);
        $scaleK = $cardWmm / $frameWidthMm;

        $cols = max(1, (int) floor((self::PAGE_W_MM - 2 * $marginMm + $gapMm) / ($cardWmm + $gapMm)));
        $rows = max(1, (int) floor((self::PAGE_H_MM - 2 * $marginMm + $gapMm) / ($cardHmm + $gapMm)));
        $perSheet = $cols * $rows;

        $gridWidthMm = $cols * $cardWmm + max(0, $cols - 1) * $gapMm;
        $gridHeightMm = $rows * $cardHmm + max(0, $rows - 1) * $gapMm;

        return [
            'marginMm' => $marginMm,
            'gapMm' => $gapMm,
            'cardWmm' => $cardWmm,
            'cardHmm' => $cardHmm,
            'scaleK' => $scaleK,
            'cols' => $cols,
            'rows' => $rows,
            'perSheet' => $perSheet,
            'gridWidthMm' => $gridWidthMm,
            'gridHeightMm' => $gridHeightMm,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function computeForTemplate(CardTemplate $template): array
    {
        $layout = $template->layout ?? CardTemplate::defaultLayout();

        return self::compute(
            (float) $template->frame_width_mm,
            (float) $template->frame_height_mm,
            $layout['a4'] ?? [],
        );
    }
}
