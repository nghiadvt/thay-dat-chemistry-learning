<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_modes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('student_ui', 64)->default('quiz-sync');
            $table->string('host_ui', 64)->default('host-quiz');
            $table->string('banner_path', 255)->nullable();
            $table->json('default_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('play_mode_id')
                ->nullable()
                ->after('description')
                ->constrained('play_modes')
                ->nullOnDelete();
            $table->json('mode_config')->nullable()->after('play_mode_id');
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->string('play_mode_slug', 64)->nullable()->after('quiz_id');
            $table->json('mode_config')->nullable()->after('play_mode_slug');
        });

        Schema::table('game_results', function (Blueprint $table) {
            $table->unsignedTinyInteger('finish_rank')->nullable()->after('rank');
            $table->timestamp('finished_at')->nullable()->after('finish_rank');
        });

        $kahootConfig = json_encode([
            'scoring' => ['type' => 'kahoot_time'],
            'flow' => ['sync_questions' => true, 'use_timer' => true],
        ], JSON_UNESCAPED_UNICODE);

        $duckConfig = json_encode([
            'scoring' => [
                'correct_delta' => 3,
                'wrong_delta' => -5,
                'allow_negative' => true,
            ],
            'win' => [
                'target_score' => 30,
                'podium_size' => 3,
            ],
            'flow' => [
                'sync_questions' => false,
                'advance_on' => 'submit',
                'use_timer' => false,
                'end_when_podium_full' => true,
            ],
            'visual' => [
                'theme' => 'duck_race',
                'track_steps' => 30,
                'track_bounds' => ['start_pct' => 20, 'end_pct' => 90],
                'duck_sprite_px' => 64,
                'duck_swim_ms' => 1150,
                'assets' => [
                    'banner' => '/app/assets/duck-race/banner.png',
                    'background' => '/app/assets/duck-race/background.png',
                    'duck' => '/app/assets/duck-race/duck-blue.gif',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        DB::table('play_modes')->insert([
            [
                'slug' => 'kahoot_sync',
                'name' => 'Quiz đồng bộ (Kahoot)',
                'description' => 'Cả phòng cùng câu, có đếm giờ, chấm theo tốc độ.',
                'student_ui' => 'quiz-sync',
                'host_ui' => 'host-quiz',
                'banner_path' => null,
                'default_config' => $kahootConfig,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'duck_race',
                'name' => 'Đua vịt hóa học',
                'description' => 'Trả lời nhanh, đúng +3 bước, sai -5 bước. Ai chạm 30 điểm trước về nhất.',
                'student_ui' => 'duck-race',
                'host_ui' => 'host-duck-race',
                'banner_path' => 'play-modes/duck-race-banner.png',
                'default_config' => $duckConfig,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $kahootId = DB::table('play_modes')->where('slug', 'kahoot_sync')->value('id');
        DB::table('games')->whereNull('play_mode_id')->update(['play_mode_id' => $kahootId]);
    }

    public function down(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->dropColumn(['finish_rank', 'finished_at']);
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['play_mode_slug', 'mode_config']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('play_mode_id');
            $table->dropColumn('mode_config');
        });

        Schema::dropIfExists('play_modes');
    }
};
