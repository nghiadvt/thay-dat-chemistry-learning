<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageCropController extends Controller
{
    private const MAX_IMAGES = 40;

    private const MAX_BYTES_PER_IMAGE = 8 * 1024 * 1024;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMAGES],
            'images.*.data' => ['required', 'string'],
            'images.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        $folder = 'cropped-images/'.now()->format('Ymd-His').'-'.Str::random(6);
        $saved = [];

        foreach ($validated['images'] as $index => $image) {
            $position = $index + 1;

            if (! preg_match('/^data:image\/png;base64,(.+)$/s', $image['data'], $matches)) {
                return $this->jsonError("Ảnh #{$position} phải là PNG base64.", 422);
            }

            $binary = base64_decode($matches[1], true);
            if ($binary === false || strlen($binary) > self::MAX_BYTES_PER_IMAGE) {
                return $this->jsonError("Ảnh #{$position} không hợp lệ hoặc quá lớn (tối đa 8MB).", 422);
            }

            $label = Str::slug($image['label'] ?? '', '-');
            $name = ($label !== '' ? $label : 'anh-'.$position).'-'.Str::random(6).'.png';
            $path = $folder.'/'.$name;

            if (Storage::disk('public')->put($path, $binary) === false) {
                return $this->jsonError("Không lưu được ảnh #{$position}.", 500);
            }

            $saved[] = [
                'path' => $path,
                'url' => url(Storage::disk('public')->url($path)),
                'label' => $image['label'] ?? null,
            ];
        }

        return $this->jsonSuccess(['images' => $saved, 'folder' => $folder], 201);
    }
}
