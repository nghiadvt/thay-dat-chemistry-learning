<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\EntitlementResolver;
use App\Support\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quyền hiệu lực của học sinh, để student app biết mục nào mở, mục nào gắn tag
 * Pro và còn bao nhiêu ngày.
 *
 * Đây chỉ là dữ liệu để VẼ giao diện — việc chặn thật vẫn nằm ở phía server
 * trong từng endpoint gameplay, vì client sửa được.
 */
class EntitlementController extends Controller
{
    public function __construct(
        private EntitlementResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $student = $request->user('student');

        $features = [];
        foreach ($this->resolver->all($student) as $key => $entry) {
            $features[$key] = [
                'name' => $entry['name'],
                'access_level' => $entry['access_level'],
                'is_pro' => $entry['access_level'] === FeatureRegistry::ACCESS_FULL,
                'scope' => $entry['scope'],
                'expires_at' => $entry['expires_at']?->toIso8601String(),
                'days_remaining' => $entry['days_remaining'],
            ];
        }

        $soonest = $this->resolver->soonestExpiry($student);

        return $this->jsonSuccess([
            'features' => $features,
            'pro_banner' => $soonest === null ? null : [
                'feature' => $soonest['feature'],
                'feature_name' => FeatureRegistry::label($soonest['feature']),
                'days' => $soonest['days'],
                'expires_at' => $soonest['expires_at']->toIso8601String(),
            ],
        ]);
    }
}
