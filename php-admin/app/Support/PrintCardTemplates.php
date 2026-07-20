<?php

namespace App\Support;

/**
 * Danh sách mẫu in "phiếu tài khoản" dựng sẵn.
 *
 * Đây là mẫu tĩnh (không cho giáo viên tự thiết kế) — mỗi mẫu quy định số
 * thẻ/trang và màu nhấn, phần HTML thực tế nằm ở
 * resources/views/admin/students/print-cards/sheet.blade.php và tự đổi giao
 * diện theo `key`. Thêm mẫu mới chỉ cần thêm một phần tử ở đây rồi khai báo
 * cách vẽ card trong file blade đó.
 */
class PrintCardTemplates
{
    public static function all(): array
    {
        return [
            'modern' => [
                'key' => 'modern',
                'name' => 'Hiện đại',
                'description' => 'Thẻ lớn, dải màu nhấn ở đầu thẻ — dễ đọc, phù hợp phát tận tay từng em.',
                'cardsPerSheet' => 4,
                'columns' => 2,
                'cardHeightMm' => 118,
                'accent' => '#2D46D6',
            ],
            'classic' => [
                'key' => 'classic',
                'name' => 'Cổ điển',
                'description' => 'Khung viền rõ ràng, bố cục quen thuộc — vừa 6 thẻ mỗi trang A4.',
                'cardsPerSheet' => 6,
                'columns' => 2,
                'cardHeightMm' => 82,
                'accent' => '#334155',
            ],
            'minimal' => [
                'key' => 'minimal',
                'name' => 'Tối giản',
                'description' => 'Đen trắng, viền đứt để cắt — gọn nhẹ, in tiết kiệm mực, 8 thẻ mỗi trang.',
                'cardsPerSheet' => 8,
                'columns' => 2,
                'cardHeightMm' => 60,
                'accent' => '#111827',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
