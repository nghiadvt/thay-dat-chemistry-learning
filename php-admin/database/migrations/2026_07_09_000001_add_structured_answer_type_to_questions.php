<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay', 'structured') NOT NULL");
    }

    public function down(): void
    {
        DB::table('questions')
            ->where('answer_type', 'structured')
            ->update(['answer_type' => 'essay']);

        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay') NOT NULL");
    }
};
