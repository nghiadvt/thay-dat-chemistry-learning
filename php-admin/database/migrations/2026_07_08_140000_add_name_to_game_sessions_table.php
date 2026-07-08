<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('pin');
        });

        DB::table('game_sessions')->whereNull('name')->update([
            'name' => DB::raw("CONCAT('Phòng ', pin)"),
        ]);
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
