<?php

namespace App\Services;

use App\Models\CardTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CardTemplateStorageService
{
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public function templateBaseDir(CardTemplate $template): string
    {
        return 'card-templates/'.$template->id;
    }

    /**
     * @param  array<string, string>  $uploads  layerId => data URI
     * @param  array<string, mixed>  $sideLayout  front|back slice of layout (by reference updated)
     */
    public function syncLayerUploads(CardTemplate $template, string $side, array $uploads, array &$sideLayout): void
    {
        $layers = $sideLayout['imageLayers'] ?? [];
        $base = $this->templateBaseDir($template).'/'.$side;

        foreach ($layers as $index => $layer) {
            $layerId = $layer['id'] ?? null;
            if (! $layerId || ! isset($uploads[$layerId])) {
                continue;
            }

            $relative = $base.'/'.$layerId.'.png';
            $this->saveDataUri($uploads[$layerId], $relative);
            $layers[$index]['path'] = $relative;
        }

        $sideLayout['imageLayers'] = $layers;
    }

    /**
     * Xóa file lớp ảnh không còn trong layout mới.
     *
     * @param  array<string, mixed>  $oldLayout
     * @param  array<string, mixed>  $newLayout
     */
    public function purgeRemovedLayerFiles(array $oldLayout, array $newLayout): void
    {
        foreach (['front', 'back'] as $side) {
            $oldPaths = collect($oldLayout[$side]['imageLayers'] ?? [])
                ->pluck('path')
                ->filter()
                ->all();
            $newPaths = collect($newLayout[$side]['imageLayers'] ?? [])
                ->pluck('path')
                ->filter()
                ->flip()
                ->all();

            foreach ($oldPaths as $path) {
                if (! isset($newPaths[$path])) {
                    $this->deleteIfExists($path);
                }
            }
        }
    }

    public function saveBakedSide(CardTemplate $template, string $side, ?string $dataUri): ?string
    {
        if ($dataUri === null || trim($dataUri) === '') {
            return null;
        }

        $relative = $this->templateBaseDir($template).'/'.$side.'-baked.png';
        $this->saveDataUri($dataUri, $relative);

        return $relative;
    }

    /**
     * Bake composite PNG 300 DPI từ các lớp ảnh (fallback khi client không gửi baked).
     */
    public function bakeSideFromLayers(CardTemplate $template, string $side, array $sideLayout): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $layers = $sideLayout['imageLayers'] ?? [];
        if ($layers === []) {
            return null;
        }

        $frameW = (float) $template->frame_width_mm;
        $frameH = (float) $template->frame_height_mm;
        $pxW = max(1, (int) round($frameW / 25.4 * 300));
        $pxH = max(1, (int) round($frameH / 25.4 * 300));

        $canvas = imagecreatetruecolor($pxW, $pxH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $pxW, $pxH, $transparent);
        imagealphablending($canvas, true);

        foreach ($layers as $layer) {
            $path = $layer['path'] ?? null;
            if (! $path || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $binary = Storage::disk('public')->get($path);
            if ($binary === null) {
                continue;
            }

            $src = @imagecreatefromstring($binary);
            if ($src === false) {
                continue;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $cropW = $srcW;
            $cropH = max(1, (int) round($cropW * ($frameH / $frameW)));
            $maxSrcY = max(0, $srcH - $cropH);
            $frameCropY = max(0, min(1, (float) ($sideLayout['frameCropY'] ?? 0)));
            $srcY = (int) round($frameCropY * $maxSrcY);

            imagecopyresampled($canvas, $src, 0, 0, 0, $srcY, $pxW, $pxH, $cropW, $cropH);
            imagedestroy($src);
            break;
        }

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);

        if ($png === false || $png === '') {
            return null;
        }

        $relative = $this->templateBaseDir($template).'/'.$side.'-baked.png';
        Storage::disk('public')->put($relative, $png);

        return $relative;
    }

    public function deleteTemplateFiles(CardTemplate $template): void
    {
        $dir = $this->templateBaseDir($template);
        if (Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->deleteDirectory($dir);
        }
    }

    public function deleteIfExists(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function toDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $binary = Storage::disk('public')->get($path);
        if ($binary === null || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    public function saveDataUri(string $dataUri, string $relativePath): void
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/s', $dataUri, $matches)) {
            throw new RuntimeException('Ảnh phải là PNG/JPEG/WebP base64.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Ảnh không hợp lệ hoặc quá lớn (tối đa 8MB).');
        }

        $written = Storage::disk('public')->put($relativePath, $binary);
        if ($written === false) {
            throw new RuntimeException('Không ghi được file ảnh.');
        }
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public function normalizeLayout(array $layout, int $sides): array
    {
        $defaults = CardTemplate::defaultLayout();

        $normalized = [
            'front' => $this->normalizeSide($layout['front'] ?? []),
            'back' => $sides === 2 ? $this->normalizeSide($layout['back'] ?? []) : ['imageLayers' => [], 'elements' => []],
            'a4' => array_merge($defaults['a4'], $layout['a4'] ?? []),
        ];

        $normalized['a4']['marginMm'] = max(0, min(30, (float) ($normalized['a4']['marginMm'] ?? 8)));
        $normalized['a4']['gapMm'] = max(0, min(20, (float) ($normalized['a4']['gapMm'] ?? 4)));
        $normalized['a4']['cardWidthMm'] = max(20, min(100, (float) ($normalized['a4']['cardWidthMm'] ?? 54)));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    private function normalizeSide(array $side): array
    {
        $layers = [];
        foreach ($side['imageLayers'] ?? [] as $layer) {
            if (! is_array($layer)) {
                continue;
            }
            $id = $layer['id'] ?? ('img_'.Str::lower(Str::random(6)));
            $normalized = [
                'id' => $id,
                'path' => $layer['path'] ?? null,
                'x' => max(0, min(2, (float) ($layer['x'] ?? 0))),
                'y' => max(0, min(2, (float) ($layer['y'] ?? 0))),
                'w' => max(0.01, min(3, (float) ($layer['w'] ?? 1))),
                'h' => max(0.01, min(3, (float) ($layer['h'] ?? 1))),
                'rotation' => (float) ($layer['rotation'] ?? 0),
                'opacity' => max(0, min(1, (float) ($layer['opacity'] ?? 1))),
                'hidden' => (bool) ($layer['hidden'] ?? false),
            ];
            if (isset($layer['naturalRatio'])) {
                $normalized['naturalRatio'] = max(0.01, (float) $layer['naturalRatio']);
            }
            $layers[] = $normalized;
        }

        $elements = [];
        foreach ($side['elements'] ?? [] as $el) {
            if (! is_array($el)) {
                continue;
            }
            $elements[] = [
                'id' => $el['id'] ?? ('el_'.Str::lower(Str::random(6))),
                'binding' => $el['binding'] ?? 'static',
                'text' => (string) ($el['text'] ?? ''),
                'x' => max(0, min(1, (float) ($el['x'] ?? 0))),
                'y' => max(0, min(1, (float) ($el['y'] ?? 0))),
                'w' => max(0.02, min(1, (float) ($el['w'] ?? 0.3))),
                'h' => max(0.02, min(1, (float) ($el['h'] ?? 0.1))),
                'fontFamily' => (string) ($el['fontFamily'] ?? 'be-vietnam-pro'),
                'fontSizePt' => max(6, min(72, (float) ($el['fontSizePt'] ?? 11))),
                'fontWeight' => (int) ($el['fontWeight'] ?? 400),
                'italic' => (bool) ($el['italic'] ?? false),
                'underline' => (bool) ($el['underline'] ?? false),
                'color' => (string) ($el['color'] ?? '#111827'),
                'align' => in_array($el['align'] ?? 'left', ['left', 'center', 'right'], true) ? $el['align'] : 'left',
                'lineHeight' => max(0.8, min(3, (float) ($el['lineHeight'] ?? 1.2))),
                'paddingPx' => max(0, min(40, (int) ($el['paddingPx'] ?? 4))),
                'bgColor' => $el['bgColor'] ?? null,
                'bgOpacity' => max(0, min(1, (float) ($el['bgOpacity'] ?? 1))),
                'borderWidthPt' => max(0, min(8, (float) ($el['borderWidthPt'] ?? 0))),
                'borderColor' => (string) ($el['borderColor'] ?? '#000000'),
                'borderRadiusPx' => max(0, min(40, (int) ($el['borderRadiusPx'] ?? 0))),
            ];
        }

        return [
            'imageLayers' => $layers,
            'elements' => $elements,
            'frameCropY' => max(0, min(1, (float) ($side['frameCropY'] ?? 0))),
        ];
    }
}
