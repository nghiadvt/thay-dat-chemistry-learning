<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageCropSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImageCropSourceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $validated['image'];
        $originalFilename = $file->getClientOriginalName();
        $name = trim((string) ($validated['name'] ?? '')) !== ''
            ? $validated['name']
            : pathinfo($originalFilename, PATHINFO_FILENAME);

        $folder = 'image-crop-sources/'.now()->format('Ymd-His').'-'.Str::random(6);
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $path = $file->storeAs($folder, 'original.'.$ext, 'public');

        if ($path === false) {
            return $this->jsonError('Không lưu được ảnh.', 500);
        }

        $source = ImageCropSource::create([
            'name' => $name,
            'original_filename' => $originalFilename,
            'path' => $path,
            'folder' => $folder,
        ]);

        return $this->jsonSuccess([
            'id' => $source->id,
            'name' => $source->name,
            'image_url' => $source->image_url,
        ], 201);
    }
}
