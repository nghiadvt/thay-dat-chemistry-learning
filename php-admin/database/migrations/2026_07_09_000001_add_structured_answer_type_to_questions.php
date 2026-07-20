<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM chỉ có ở MySQL; SQLite lưu cột này dạng text nên không cần đổi.
        if ($this->isMysql()) {
            DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay', 'structured') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('questions')
            ->where('answer_type', 'structured')
            ->update(['answer_type' => 'essay']);

        if ($this->isMysql()) {
            DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay') NOT NULL");
        }
    }

    private function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }
};
