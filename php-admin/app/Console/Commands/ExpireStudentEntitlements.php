<?php

namespace App\Console\Commands;

use App\Models\StudentEntitlement;
use Illuminate\Console\Command;

/**
 * Dọn các quyền đã quá hạn.
 *
 * Về mặt logic thì không bắt buộc — EntitlementResolver đã bỏ qua grant hết hạn
 * ngay khi đọc. Lệnh này chỉ đánh dấu revoked_at để danh sách trong admin gọn
 * lại và để báo cáo dễ đọc hơn.
 */
class ExpireStudentEntitlements extends Command
{
    protected $signature = 'students:expire-entitlements {--dry-run : Chỉ đếm, không ghi}';

    protected $description = 'Đánh dấu thu hồi các quyền học sinh đã quá hạn';

    public function handle(): int
    {
        $query = StudentEntitlement::query()
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $count = $query->count();

        if ($count === 0) {
            $this->info('Không có quyền nào quá hạn.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Có {$count} quyền đã quá hạn (chưa ghi gì do --dry-run).");

            return self::SUCCESS;
        }

        $query->update(['revoked_at' => now()]);
        $this->info("Đã thu hồi {$count} quyền quá hạn.");

        return self::SUCCESS;
    }
}
