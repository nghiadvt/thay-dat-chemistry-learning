<?php

namespace App\Support;

/**
 * Nguồn duy nhất cho label trạng thái tiếng Việt — thay các mảng
 * $statusLabels lặp lại trong từng Blade/controller.
 */
class StatusLabels
{
    /** Trạng thái phòng chơi (game_sessions.status). */
    public const SESSION = [
        'waiting' => 'Chờ',
        'playing' => 'Đang chơi',
        'ended' => 'Kết thúc',
    ];

    /** Trạng thái góp ý (feedback.status). */
    public const FEEDBACK = [
        'new' => 'Mới',
        'read' => 'Đã xem',
        'done' => 'Hoàn thành',
    ];

    public static function session(?string $status): string
    {
        return self::SESSION[$status] ?? (string) $status;
    }

    public static function feedback(?string $status): string
    {
        return self::FEEDBACK[$status] ?? (string) $status;
    }
}
