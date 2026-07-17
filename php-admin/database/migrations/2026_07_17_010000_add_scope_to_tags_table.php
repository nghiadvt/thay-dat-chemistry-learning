<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('scope', 20)->default('content')->after('id');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
            $table->unique(['scope', 'name']);
            $table->unique(['scope', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['scope', 'name']);
            $table->dropUnique(['scope', 'slug']);
            $table->unique('name');
            $table->unique('slug');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
