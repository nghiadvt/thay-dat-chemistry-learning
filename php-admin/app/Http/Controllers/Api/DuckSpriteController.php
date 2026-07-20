<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DuckSprite;
use App\Models\DuckSpriteFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quản lý "vịt chuyển động" cho game Đua vịt: mỗi vịt là một bộ
 * frame ảnh (8-10 hình) + tốc độ phát (fps). Admin upload frame,
 * sắp thứ tự, preview animation ngay trong trang cấu hình game.
 */
class DuckSpriteController extends Controller
{
    private const MAX_FRAMES = 20;

    public function index(): JsonResponse
    {
        $ducks = DuckSprite::query()
            ->with('frames')
            ->orderBy('name')
            ->get();

        return $this->jsonSuccess(
            $ducks->map(fn (DuckSprite $duck) => $duck->toManagerPayload())->values()->all(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fps' => ['required', 'integer', 'min:1', 'max:30'],
            'frames' => ['required', 'array', 'min:1', 'max:'.self::MAX_FRAMES],
            'frames.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $folder = 'duck-sprites/'.now()->format('Ymd-His').'-'.Str::random(6);

        $duck = DB::transaction(function () use ($validated, $folder) {
            $duck = DuckSprite::create([
                'name' => $validated['name'],
                'fps' => $validated['fps'],
                'folder' => $folder,
            ]);

            foreach (array_values($validated['frames']) as $index => $file) {
                $duck->frames()->create([
                    'path' => $this->storeFrame($file, $folder),
                    'position' => $index,
                ]);
            }

            return $duck;
        });

        return $this->jsonSuccess($duck->load('frames')->toManagerPayload(), 201);
    }

    /**
     * Cập nhật tên, tốc độ và thứ tự frame. `frame_ids` là danh sách
     * ĐẦY ĐỦ id frame theo thứ tự mới — frame không có trong danh sách
     * sẽ bị xóa (cả file).
     */
    public function update(Request $request, DuckSprite $duckSprite): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fps' => ['required', 'integer', 'min:1', 'max:30'],
            'frame_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_FRAMES],
            'frame_ids.*' => ['integer'],
        ]);

        $frames = $duckSprite->frames()->get()->keyBy('id');
        $orderedIds = array_values(array_unique(array_map('intval', $validated['frame_ids'])));

        foreach ($orderedIds as $id) {
            if (! $frames->has($id)) {
                return $this->jsonError('Frame không thuộc vịt này.', 422);
            }
        }

        DB::transaction(function () use ($duckSprite, $validated, $frames, $orderedIds) {
            $duckSprite->update([
                'name' => $validated['name'],
                'fps' => $validated['fps'],
            ]);

            foreach ($orderedIds as $position => $id) {
                $frames[$id]->update(['position' => $position]);
            }

            $removed = $frames->except($orderedIds);
            foreach ($removed as $frame) {
                Storage::disk('public')->delete($frame->path);
                $frame->delete();
            }
        });

        return $this->jsonSuccess($duckSprite->fresh()->load('frames')->toManagerPayload());
    }

    /**
     * Thêm frame mới vào cuối danh sách (dùng khi sửa vịt đã có).
     */
    public function addFrames(Request $request, DuckSprite $duckSprite): JsonResponse
    {
        $validated = $request->validate([
            'frames' => ['required', 'array', 'min:1', 'max:'.self::MAX_FRAMES],
            'frames.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $existing = $duckSprite->frames()->count();
        if ($existing + count($validated['frames']) > self::MAX_FRAMES) {
            return $this->jsonError('Tối đa '.self::MAX_FRAMES.' frame mỗi vịt.', 422);
        }

        $nextPosition = (int) $duckSprite->frames()->max('position') + 1;
        $created = [];

        foreach (array_values($validated['frames']) as $index => $file) {
            $created[] = $duckSprite->frames()->create([
                'path' => $this->storeFrame($file, $duckSprite->folder),
                'position' => $nextPosition + $index,
            ]);
        }

        return $this->jsonSuccess([
            'frames' => array_map(fn (DuckSpriteFrame $frame) => [
                'id' => $frame->id,
                'position' => $frame->position,
                'url' => $frame->url,
            ], $created),
        ], 201);
    }

    public function destroy(DuckSprite $duckSprite): JsonResponse
    {
        Storage::disk('public')->deleteDirectory($duckSprite->folder);
        $duckSprite->delete();

        return $this->jsonSuccess();
    }

    private function storeFrame(UploadedFile $file, string $folder): string
    {
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $name = now()->format('His').'-'.Str::random(8).'.'.$ext;

        $path = $file->storeAs($folder, $name, 'public');
        abort_if($path === false, 500, 'Không lưu được frame.');

        return $path;
    }
}
