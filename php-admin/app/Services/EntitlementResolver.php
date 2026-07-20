<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEntitlement;
use App\Support\FeatureRegistry;
use Illuminate\Support\Collection;

/**
 * Tính quyền hiệu lực của học sinh trên từng tính năng.
 *
 * Thứ tự ưu tiên (cái sau ghi đè cái trước):
 *   1. Mặc định miễn phí trong FeatureRegistry
 *   2. Grant cấp cho cả lớp của học sinh
 *   3. Grant cấp riêng cho học sinh
 *
 * Nếu ở cùng một mức có nhiều grant còn hiệu lực trên cùng tính năng thì
 * grant MỚI NHẤT thắng — để giáo viên chỉ cần cấp lại là ghi đè, không phải
 * đi thu hồi grant cũ.
 */
class EntitlementResolver
{
    /**
     * Quyền hiệu lực cho tất cả tính năng.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(Student $student): array
    {
        $grants = $this->effectiveGrants($student);
        $resolved = [];

        foreach (FeatureRegistry::keys() as $key) {
            $resolved[$key] = $this->resolveFeature($key, $grants);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function for(Student $student, string $featureKey): array
    {
        return $this->resolveFeature($featureKey, $this->effectiveGrants($student));
    }

    /** Học sinh có được dùng tính năng này ở mức nào đó không (khác 'none'). */
    public function allows(Student $student, string $featureKey): bool
    {
        return $this->for($student, $featureKey)['access_level'] !== FeatureRegistry::ACCESS_NONE;
    }

    public function hasFullAccess(Student $student, string $featureKey): bool
    {
        return $this->for($student, $featureKey)['access_level'] === FeatureRegistry::ACCESS_FULL;
    }

    /**
     * Hạn Pro gần nhất sẽ hết trong số các tính năng đang được cấp full —
     * dùng cho banner "tài khoản pro còn N ngày" ở trang chủ.
     *
     * @return array{feature: string, expires_at: \Illuminate\Support\Carbon, days: int}|null
     */
    public function soonestExpiry(Student $student): ?array
    {
        $soonest = null;

        foreach ($this->all($student) as $key => $entry) {
            if ($entry['access_level'] !== FeatureRegistry::ACCESS_FULL || $entry['expires_at'] === null) {
                continue;
            }

            if ($soonest === null || $entry['expires_at']->lt($soonest['expires_at'])) {
                $soonest = [
                    'feature' => $key,
                    'expires_at' => $entry['expires_at'],
                    'days' => $entry['days_remaining'],
                ];
            }
        }

        return $soonest;
    }

    /**
     * @return Collection<int, StudentEntitlement>
     */
    private function effectiveGrants(Student $student): Collection
    {
        return StudentEntitlement::query()
            ->effective()
            ->where(function ($query) use ($student) {
                $query->where('student_id', $student->id);

                if ($student->class_id !== null) {
                    $query->orWhere('class_id', $student->class_id);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, StudentEntitlement>  $grants
     * @return array<string, mixed>
     */
    private function resolveFeature(string $featureKey, Collection $grants): array
    {
        $feature = FeatureRegistry::get($featureKey);

        if ($feature === null) {
            return [
                'feature_key' => $featureKey,
                'name' => $featureKey,
                'access_level' => FeatureRegistry::ACCESS_NONE,
                'scope' => [],
                'expires_at' => null,
                'days_remaining' => null,
                'source' => 'unknown',
            ];
        }

        $access = FeatureRegistry::ACCESS_FREE;
        $scope = FeatureRegistry::freeScope($featureKey);
        $expiresAt = null;
        $source = 'default';

        $applicable = $grants->where('feature_key', $featureKey);

        // Lớp trước, học sinh sau — trong mỗi mức thì grant mới nhất thắng.
        $classGrant = $applicable->whereNull('student_id')->last();
        $studentGrant = $applicable->whereNotNull('student_id')->last();

        foreach ([['class', $classGrant], ['student', $studentGrant]] as [$level, $grant]) {
            if (! $grant instanceof StudentEntitlement) {
                continue;
            }

            $access = $grant->access_level;
            $scope = match ($access) {
                FeatureRegistry::ACCESS_FULL => FeatureRegistry::fullScope($featureKey),
                FeatureRegistry::ACCESS_FREE => FeatureRegistry::freeScope($featureKey),
                default => [],
            };

            // Ghi đè lẻ từng khóa trong phạm vi (vd chỉ nới 'unlocked').
            if (is_array($grant->scope) && $grant->scope !== []) {
                $scope = array_replace($scope, $grant->scope);
            }

            $expiresAt = $grant->expires_at;
            $source = $level;
        }

        return [
            'feature_key' => $featureKey,
            'name' => $feature['name'],
            'description' => $feature['description'] ?? '',
            'access_level' => $access,
            'scope' => $scope,
            'expires_at' => $expiresAt,
            'days_remaining' => $expiresAt === null
                ? null
                : max(0, (int) ceil(now()->diffInHours($expiresAt, false) / 24)),
            'source' => $source,
        ];
    }
}
