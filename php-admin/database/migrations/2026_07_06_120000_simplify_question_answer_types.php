<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'formula', 'structured', 'essay') NOT NULL");

        DB::table('questions')
            ->whereIn('answer_type', ['formula', 'structured'])
            ->update(['answer_type' => 'essay']);

        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'essay') NOT NULL");
        DB::statement('ALTER TABLE questions MODIFY correct_answer_normalized TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE questions MODIFY correct_answer_normalized VARCHAR(255) NULL');
        DB::statement("ALTER TABLE questions MODIFY answer_type ENUM('mc', 'formula', 'structured') NOT NULL");
    }
};
