<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\KeyboardTestConfig;
use Tests\TestCase;

class KeyboardManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'teacher']);
    }

    public function test_guest_is_redirected_from_keyboard_index(): void
    {
        $this->get(route('admin.keyboards.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_keyboards(): void
    {
        $keyboard = Keyboard::factory()->create(['name' => 'Bàn phím test']);

        $this->actingAs($this->user)
            ->get(route('admin.keyboards.index'))
            ->assertOk()
            ->assertSee('Bàn phím test');
    }

    public function test_create_form_renders(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.keyboards.create'))
            ->assertOk()
            ->assertSee('Tạo bàn phím mới');
    }

    public function test_store_creates_keyboard_with_default_config_including_zero_key(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.keyboards.store'), [
            'name' => 'Bàn phím Hóa mới',
            'subject' => 'chemistry',
        ]);

        $keyboard = Keyboard::query()->where('name', 'Bàn phím Hóa mới')->first();

        $this->assertNotNull($keyboard);
        $response->assertRedirect(route('admin.keyboards.editor', $keyboard));

        $numbersRow = collect($keyboard->config['rows'])->firstWhere('name', 'Numbers');
        $this->assertNotNull($numbersRow);
        $this->assertContains(
            '0',
            collect($numbersRow['keys'])->pluck('text')->all()
        );
    }

    public function test_editor_page_renders_without_blade_parse_error(): void
    {
        $keyboard = Keyboard::factory()->create([
            'name' => 'Editor OK',
            'config' => KeyboardTestConfig::minimalValid(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.keyboards.editor', $keyboard));

        $response->assertOk();
        $response->assertDontSee('ParseError', false);
        $response->assertSee('window.ADMIN_BOOT', false);
        $response->assertSee('"id":'.$keyboard->id, false);
        $response->assertSee('"name":"Editor OK"', false);
        $response->assertSee('keyboard-editor.js', false);
    }

    public function test_edit_redirects_to_editor(): void
    {
        $keyboard = Keyboard::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.keyboards.edit', $keyboard))
            ->assertRedirect(route('admin.keyboards.editor', $keyboard));
    }

    public function test_api_update_keyboard_persists_to_database(): void
    {
        $keyboard = Keyboard::factory()->create([
            'name' => 'Trước khi save',
            'config' => KeyboardTestConfig::minimalValid(),
        ]);

        $updatedConfig = KeyboardTestConfig::minimalValid();
        $updatedConfig['rows'][0]['name'] = 'Hàng đã đổi';

        $this->actingAs($this->user)
            ->putJson("/api/keyboards/{$keyboard->id}", [
                'name' => 'Sau khi save',
                'subject' => 'chemistry',
                'config' => $updatedConfig,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $keyboard->refresh();
        $this->assertSame('Sau khi save', $keyboard->name);
        $this->assertSame('Hàng đã đổi', $keyboard->config['rows'][0]['name']);
    }

    public function test_destroy_deletes_keyboard_without_quizzes(): void
    {
        $keyboard = Keyboard::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.keyboards.destroy', $keyboard))
            ->assertRedirect(route('admin.keyboards.index'));

        $this->assertDatabaseMissing('keyboards', ['id' => $keyboard->id]);
    }

    public function test_destroy_is_blocked_when_quiz_uses_keyboard(): void
    {
        $keyboard = Keyboard::factory()->create();
        $game = Game::query()->create([
            'name' => 'Game test',
            'description' => null,
            'created_by' => $this->user->id,
        ]);
        Quiz::query()->create([
            'game_id' => $game->id,
            'keyboard_id' => $keyboard->id,
            'name' => 'Quiz test',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('admin.keyboards.index'))
            ->delete(route('admin.keyboards.destroy', $keyboard))
            ->assertRedirect(route('admin.keyboards.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('keyboards', ['id' => $keyboard->id]);
    }
}
