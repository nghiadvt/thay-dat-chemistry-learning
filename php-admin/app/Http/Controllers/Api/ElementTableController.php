<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodicPreset;
use Illuminate\Http\JsonResponse;

/**
 * Bảng nguyên tố học sinh thấy = published_snapshot của phiên bản đang live.
 * Không cần đăng nhập; trạng thái pro/thường do client tự đối chiếu qua
 * /api/student/entitlements để có thể cache endpoint này.
 */
class ElementTableController extends Controller
{
    public function show(): JsonResponse
    {
        $preset = PeriodicPreset::where('is_live', true)->first();

        $snapshot = $preset?->published_snapshot ?? ['categories' => [], 'elements' => []];

        return $this->jsonSuccess([
            'preset' => $preset ? ['id' => $preset->id, 'name' => $preset->name] : null,
            'categories' => $snapshot['categories'] ?? [],
            'elements' => $snapshot['elements'] ?? [],
        ]);
    }
}
