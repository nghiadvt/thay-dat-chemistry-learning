<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use App\Services\RedisRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        if (Role::query()->exists()) {
            return;
        }

        Role::query()->insert([
            ['name' => 'Quản trị viên', 'slug' => 'admin', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Giáo viên', 'slug' => 'teacher', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createSession(array $overrides = []): GameSession
    {
        $teacher = User::factory()->teacher()->create();

        $keyboard = Keyboard::create([
            'name' => 'Test keyboard',
            'subject' => 'chemistry',
            'config' => ['rows' => []],
        ]);

        $game = Game::create([
            'name' => 'Test game',
            'created_by' => $teacher->id,
        ]);

        $quiz = Quiz::create([
            'game_id' => $game->id,
            'keyboard_id' => $keyboard->id,
            'name' => 'Test quiz',
            'is_active' => true,
        ]);

        return GameSession::create(array_merge([
            'pin' => '123456',
            'name' => 'Phòng test',
            'host_id' => $teacher->id,
            'game_id' => $game->id,
            'quiz_id' => $quiz->id,
            'status' => 'waiting',
            'is_active' => true,
        ], $overrides));
    }

    public function test_teacher_can_delete_waiting_session(): void
    {
        Storage::fake('public');
        $session = $this->createSession(['pin' => '111111']);

        $redis = Mockery::mock(RedisRoomService::class);
        $redis->shouldReceive('purgeRoom')->once()->with('111111');
        $this->app->instance(RedisRoomService::class, $redis);

        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->delete(route('admin.sessions.destroy', $session));

        $response->assertRedirect(route('admin.sessions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('game_sessions', ['id' => $session->id]);
    }

    public function test_cannot_delete_playing_session(): void
    {
        $session = $this->createSession(['pin' => '222222', 'status' => 'playing']);

        $redis = Mockery::mock(RedisRoomService::class);
        $redis->shouldNotReceive('purgeRoom');
        $this->app->instance(RedisRoomService::class, $redis);

        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->delete(route('admin.sessions.destroy', $session));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('game_sessions', ['id' => $session->id]);
    }

    public function test_bulk_destroy_deletes_multiple_sessions_and_related_rows(): void
    {
        Storage::fake('public');

        $sessionA = $this->createSession(['pin' => '333333', 'name' => 'Phòng A']);
        $sessionB = $this->createSession(['pin' => '444444', 'name' => 'Phòng B']);
        $playing = $this->createSession(['pin' => '555555', 'name' => 'Phòng C', 'status' => 'playing']);

        GameResult::create([
            'session_id' => $sessionA->id,
            'student_name' => 'HS1',
            'score' => 100,
            'rank' => 1,
        ]);

        $redis = Mockery::mock(RedisRoomService::class);
        $redis->shouldReceive('purgeRoom')->once()->with('333333');
        $redis->shouldReceive('purgeRoom')->once()->with('444444');
        $redis->shouldNotReceive('purgeRoom')->with('555555');
        $this->app->instance(RedisRoomService::class, $redis);

        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->post(route('admin.sessions.bulk-destroy'), [
            'ids' => [$sessionA->id, $sessionB->id, $playing->id],
        ]);

        $response->assertRedirect(route('admin.sessions.index'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseMissing('game_sessions', ['id' => $sessionA->id]);
        $this->assertDatabaseMissing('game_sessions', ['id' => $sessionB->id]);
        $this->assertDatabaseHas('game_sessions', ['id' => $playing->id]);
        $this->assertDatabaseMissing('game_results', ['session_id' => $sessionA->id]);
    }
}
