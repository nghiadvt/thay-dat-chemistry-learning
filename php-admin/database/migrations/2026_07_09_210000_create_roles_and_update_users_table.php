<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 32)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            [
                'name' => 'Quản trị viên',
                'slug' => 'admin',
                'description' => 'Toàn quyền admin dashboard',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Giáo viên',
                'slug' => 'teacher',
                'description' => 'Soạn nội dung và host phòng chơi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $teacherRoleId = DB::table('roles')->where('slug', 'teacher')->value('id');

        Schema::table('users', function (Blueprint $table) use ($teacherRoleId) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->restrictOnDelete();
            $table->string('avatar_path', 255)->nullable()->after('role_id');
        });

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')->where('role', 'admin')->update(['role_id' => $adminRoleId]);
            DB::table('users')->where('role', 'teacher')->update(['role_id' => $teacherRoleId]);
            DB::table('users')->whereNull('role_id')->update(['role_id' => $teacherRoleId]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        } else {
            DB::table('users')->whereNull('role_id')->update(['role_id' => $teacherRoleId]);
        }

        // Siết NOT NULL sau khi đã backfill. Cú pháp MODIFY chỉ có ở MySQL;
        // SQLite (dùng cho test) bỏ qua, cột vẫn nullable nhưng luôn có giá trị.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY role_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher'])->default('teacher')->after('password');
        });

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        DB::table('users')->where('role_id', $adminRoleId)->update(['role' => 'admin']);
        DB::table('users')->whereNull('role')->update(['role' => 'teacher']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('avatar_path');
        });

        Schema::dropIfExists('roles');
    }
};
