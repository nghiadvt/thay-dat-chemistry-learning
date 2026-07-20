<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageCropSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImageCropSourceController extends Controller
{
    /**
     * Danh sách ảnh gốc đã cắt — dùng cho các picker chọn ảnh có sẵn
     * (VD: chọn frame vịt chuyển động từ ảnh đã cắt trong image-cropper).
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));

        $sources = ImageCropSource::query()
            ->withCount('regions')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('updated_at')
            ->get();

        return $this->jsonSuccess(
            $sources->map(fn (ImageCropSource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'thumb_url' => $s->preview_url ?? $s->image_url,
                'regions_count' => $s->regions_count,
            ])->values(),
        );
    }

    /**
     * Chi tiết 1 ảnh gốc kèm toàn bộ vùng đã cắt ("Ảnh đã lưu").
     */
    public function show(ImageCropSource $source): JsonResponse
    {
        return $this->jsonSuccess([
            'id' => $source->id,
            'name' => $source->name,
            'regions' => $source->regions()->get()->map(fn ($region) => [
                'id' => $region->id,
                'label' => $region->label,
                'url' => $region->cropped_url,
            ])->values(),
        ]);
    }

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
