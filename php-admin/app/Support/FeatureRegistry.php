<?php

namespace App\Support;

/**
 * Danh mục tính năng có thể cấp quyền cho học sinh.
 *
 * Đây là NGUỒN SỰ THẬT DUY NHẤT về "có những tính năng nào". Thêm game mới =
 * thêm một entry ở đây, không cần sửa schema, không cần sửa controller, không
 * cần sửa view — trang cấp quyền và API của học sinh đều duyệt theo danh sách này.
 *
 * Mỗi tính năng khai báo:
 *   - name/description: hiển thị cho giáo viên
 *   - play_mode: slug trong bảng play_modes (nếu tính năng gắn với một chế độ chơi)
 *   - free_scope: học sinh chưa được cấp quyền thì được dùng tới đâu
 *   - full_scope: cấp "full" nghĩa là gì
 *   - scope_fields: các ô số/danh sách mà giáo viên chỉnh được khi cấp quyền lẻ
 */
class FeatureRegistry
{
    public const ACCESS_NONE = 'none';

    public const ACCESS_FREE = 'free';

    public const ACCESS_FULL = 'full';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'elements' => [
                'name' => 'Đọc nguyên tố',
                'description' => 'Luyện đọc tên và ký hiệu nguyên tố hóa học.',
                'play_mode' => null,
                'free_scope' => ['unlocked' => 12],
                'full_scope' => ['unlocked' => null], // null = không giới hạn
                'scope_fields' => [
                    'unlocked' => ['label' => 'Số nguyên tố mở', 'type' => 'int', 'min' => 0],
                ],
            ],

            'balance' => [
                'name' => 'Cân bằng phương trình',
                'description' => 'Cân bằng hệ số phương trình hóa học theo cấp độ.',
                'play_mode' => null,
                'free_scope' => ['tiers' => ['t1'], 'levels_per_tier' => 3],
                'full_scope' => ['tiers' => ['t1', 't2', 't3'], 'levels_per_tier' => null],
                'scope_fields' => [
                    'tiers' => ['label' => 'Cấp độ mở', 'type' => 'list', 'options' => ['t1', 't2', 't3']],
                    'levels_per_tier' => ['label' => 'Số màn mỗi cấp', 'type' => 'int', 'min' => 0],
                ],
            ],

            'quiz' => [
                'name' => 'Ôn trắc nghiệm',
                'description' => 'Luyện tập câu hỏi trắc nghiệm theo khối và chủ đề.',
                'play_mode' => null,
                'free_scope' => ['max_questions' => 10],
                'full_scope' => ['max_questions' => null],
                'scope_fields' => [
                    'max_questions' => ['label' => 'Số câu mỗi lượt', 'type' => 'int', 'min' => 1],
                ],
            ],

            'duck_race' => [
                'name' => 'Chơi game (phòng của giáo viên)',
                'description' => 'Vào phòng chơi bằng mã PIN do giáo viên mở.',
                'play_mode' => 'duck_race',
                'free_scope' => ['enabled' => true],
                'full_scope' => ['enabled' => true],
                'scope_fields' => [],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::get($key)['name'] ?? $key;
    }

    /**
     * @return array<string, mixed>
     */
    public static function freeScope(string $key): array
    {
        return self::get($key)['free_scope'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fullScope(string $key): array
    {
        return self::get($key)['full_scope'] ?? [];
    }
}
