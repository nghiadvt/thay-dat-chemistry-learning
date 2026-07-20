<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM là cú pháp riêng của MySQL; SQLite (dùng cho test) lưu cột này
        // dưới dạng text nên bỏ qua phần đổi kiểu, chỉ giữ lại phần dữ liệu.
        if ($this->isMysql()) {
            DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'formula', 'structured', 'essay') NOT NULL");
        }

        DB::table('questions')
            ->whereIn('answer_type', ['formula', 'structured'])
            ->update(['answer_type' => 'essay']);

        if ($this->isMysql()) {
            DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay') NOT NULL");
            DB::statement('ALTER TABLE questions MODIFY correct_answer_normalized TEXT NULL');
        }
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement('ALTER TABLE questions MODIFY correct_answer_normalized VARCHAR(255) NULL');
        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'formula', 'structured') NOT NULL");
    }

    private function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }
};
