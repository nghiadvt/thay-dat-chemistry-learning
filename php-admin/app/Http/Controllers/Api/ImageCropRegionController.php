<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageCropRegion;
use App\Models\ImageCropSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageCropRegionController extends Controller
{
    private const MAX_REGIONS = 60;

    private const MAX_BYTES_PER_IMAGE = 8 * 1024 * 1024;

    public function sync(Request $request, ImageCropSource $source): JsonResponse
    {
        $validated = $request->validate([
            'regions' => ['required', 'array', 'min:1', 'max:'.self::MAX_REGIONS],
            'regions.*.id' => ['nullable', 'integer'],
            'regions.*.label' => ['nullable', 'string', 'max:120'],
            // x/y có thể âm — vùng khoanh được phép lấn ra ngoài mép trái/trên
            // của ảnh, phần nằm ngoài ảnh không được cắt (canvas tự bỏ qua).
            'regions.*.x' => ['required', 'integer', 'min:-20000', 'max:20000'],
            'regions.*.y' => ['required', 'integer', 'min:-20000', 'max:20000'],
            'regions.*.w' => ['required', 'integer', 'min:1'],
            'regions.*.h' => ['required', 'integer', 'min:1'],
            'regions.*.rotation' => ['nullable', 'numeric', 'min:0', 'max:359.99'],
            'regions.*.flipped' => ['nullable', 'boolean'],
            'regions.*.data' => ['required', 'string'],
            'preview' => ['nullable', 'string'],
        ]);

        $keptIds = [];
        $saved = [];

        foreach ($validated['regions'] as $index => $item) {
            $position = $index + 1;

            $decoded = $this->decodeImageData($item['data']);
            if ($decoded === null) {
                return $this->jsonError("Vùng #{$position} phải là ảnh base64 hợp lệ (PNG/JPEG/WebP/AVIF/SVG).", 422);
            }
            [$binary, $ext] = $decoded;
            if (strlen($binary) > self::MAX_BYTES_PER_IMAGE) {
                return $this->jsonError("Vùng #{$position} quá lớn (tối đa 8MB).", 422);
            }

            $region = null;
            if (! empty($item['id'])) {
                $region = $source->regions()->whereKey($item['id'])->first();
            }
            $region ??= new ImageCropRegion(['image_crop_source_id' => $source->id]);

            $label = $item['label'] ?? null;
            $slug = Str::slug($label ?: 'vung-'.$position, '-');
            $filename = now()->format('Ymd-His').'-'.$slug.'.'.$ext;
            $path = $source->folder.'/'.$filename;
            $suffix = 1;
            while (Storage::disk('public')->exists($path) && $path !== $region->cropped_path) {
                $suffix++;
                $path = $source->folder.'/'.now()->format('Ymd-His').'-'.$slug.'-'.$suffix.'.'.$ext;
            }

            if (Storage::disk('public')->put($path, $binary) === false) {
                return $this->jsonError("Không lưu được vùng #{$position}.", 500);
            }

            if ($region->cropped_path && $region->cropped_path !== $path) {
                Storage::disk('public')->delete($region->cropped_path);
            }

            $region->fill([
                'label' => $label,
                'position' => $position,
                'x' => $item['x'],
                'y' => $item['y'],
                'w' => $item['w'],
                'h' => $item['h'],
                'rotation' => $item['rotation'] ?? 0,
                'flipped' => $item['flipped'] ?? false,
                'cropped_path' => $path,
            ]);
            $region->save();

            $keptIds[] = $region->id;
            $saved[] = [
                'id' => $region->id,
                'label' => $region->label,
                'position' => $region->position,
                'url' => $region->cropped_url,
            ];
        }

        $source->regions()->whereNotIn('id', $keptIds)->get()->each(function (ImageCropRegion $stale) {
            if ($stale->cropped_path) {
                Storage::disk('public')->delete($stale->cropped_path);
            }
            $stale->delete();
        });

        if (! empty($validated['preview'])) {
            $decodedPreview = $this->decodeImageData($validated['preview']);
            if ($decodedPreview !== null) {
                [$previewBinary] = $decodedPreview;
                $previewPath = $source->folder.'/preview.png';
                if (Storage::disk('public')->put($previewPath, $previewBinary) !== false) {
                    $source->update(['preview_path' => $previewPath]);
                }
            }
        }

        return $this->jsonSuccess(['regions' => $saved]);
    }

    public function destroy(ImageCropSource $source, ImageCropRegion $region): JsonResponse
    {
        abort_unless($region->image_crop_source_id === $source->id, 404);

        if ($region->cropped_path) {
            Storage::disk('public')->delete($region->cropped_path);
        }
        $region->delete();

        return $this->jsonSuccess(['id' => $region->id]);
    }

    /**
     * Giải mã data URL dạng "data:image/<mime>;base64,..." — chấp nhận
     * png/jpeg/webp/avif/svg+xml (xuất ảnh phía client có thể chọn định
     * dạng khác nhau). Trả về [binary, extension] hoặc null nếu không hợp lệ.
     *
     * @return array{0: string, 1: string}|null
     */
    private function decodeImageData(string $data): ?array
    {
        if (! preg_match('/^data:image\/(png|jpeg|webp|avif|svg\+xml);base64,(.+)$/s', $data, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            return null;
        }

        $ext = match ($matches[1]) {
            'jpeg' => 'jpg',
            'svg+xml' => 'svg',
            default => $matches[1],
        };

        return [$binary, $ext];
    }
}
