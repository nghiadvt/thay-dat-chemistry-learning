<?php

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\Keyboard;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KeyboardTestConfig;
use Tests\TestCase;

class KeyboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'teacher']);
        Storage::fake('public');
    }

    public function test_index_returns_keyboards(): void
    {
        Keyboard::factory()->create(['name' => 'API Keyboard']);

        $this->actingAs($this->user)
            ->getJson('/api/keyboards')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['name' => 'API Keyboard']);
    }

    public function test_store_accepts_config_with_zero_key(): void
    {
        $payload = [
            'name' => 'API Create',
            'subject' => 'chemistry',
            'config' => KeyboardTestConfig::minimalValid(),
        ];

        $this->actingAs($this->user)
            ->postJson('/api/keyboards', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.keyboard.name', 'API Create');

        $keyboard = Keyboard::query()->where('name', 'API Create')->first();
        $this->assertNotNull($keyboard);
        $this->assertContains(
            '0',
            collect($keyboard->config['rows'][0]['keys'])->pluck('text')->all()
        );
    }

    public function test_store_rejects_invalid_config(): void
    {
        $config = KeyboardTestConfig::minimalValid();
        $config['rows'][0]['keys'][0]['text'] = '';

        $this->actingAs($this->user)
            ->postJson('/api/keyboards', [
                'name' => 'Invalid',
                'config' => $config,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_keyboard_with_preview_url_field(): void
    {
        $keyboard = Keyboard::factory()->create([
            'preview_path' => 'keyboards/1.png',
        ]);
        Storage::disk('public')->put('keyboards/1.png', 'png');

        $this->actingAs($this->user)
            ->getJson("/api/keyboards/{$keyboard->id}")
            ->assertOk()
            ->assertJsonPath('data.keyboard.id', $keyboard->id)
            ->assertJsonPath('data.keyboard.preview_url', Storage::disk('public')->url('keyboards/1.png'));
    }

    public function test_update_persists_config_changes(): void
    {
        $keyboard = Keyboard::factory()->create();
        $updated = KeyboardTestConfig::minimalValid();
        $updated['rows'][1]['name'] = 'Symbols Updated';

        $this->actingAs($this->user)
            ->putJson("/api/keyboards/{$keyboard->id}", [
                'name' => 'Renamed',
                'config' => $updated,
            ])
            ->assertOk()
            ->assertJsonPath('data.keyboard.name', 'Renamed');

        $keyboard->refresh();
        $this->assertSame('Symbols Updated', $keyboard->config['rows'][1]['name']);
    }

    public function test_upload_preview_stores_png_and_updates_path(): void
    {
        $keyboard = Keyboard::factory()->create();
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PfI3pgAAAABJRU5ErkJggg==';

        $this->actingAs($this->user)
            ->postJson("/api/keyboards/{$keyboard->id}/preview", ['image' => $png])
            ->assertOk()
            ->assertJsonPath('success', true);

        $keyboard->refresh();
        $this->assertSame("keyboards/{$keyboard->id}.png", $keyboard->preview_path);
        Storage::disk('public')->assertExists("keyboards/{$keyboard->id}.png");
    }

    public function test_upload_preview_rejects_invalid_payload(): void
    {
        $keyboard = Keyboard::factory()->create();

        $this->actingAs($this->user)
            ->postJson("/api/keyboards/{$keyboard->id}/preview", ['image' => 'not-an-image'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_destroy_is_blocked_when_quiz_uses_keyboard(): void
    {
        $keyboard = Keyboard::factory()->create();
        $game = Game::query()->create([
            'name' => 'Game',
            'created_by' => $this->user->id,
        ]);
        Quiz::query()->create([
            'game_id' => $game->id,
            'keyboard_id' => $keyboard->id,
            'name' => 'Quiz',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/keyboards/{$keyboard->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('keyboards', ['id' => $keyboard->id]);
    }

    public function test_destroy_removes_keyboard_and_preview_file(): void
    {
        $keyboard = Keyboard::factory()->create([
            'preview_path' => 'keyboards/9.png',
        ]);
        Storage::disk('public')->put('keyboards/9.png', 'png');

        $this->actingAs($this->user)
            ->deleteJson("/api/keyboards/{$keyboard->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('keyboards', ['id' => $keyboard->id]);
        Storage::disk('public')->assertMissing('keyboards/9.png');
    }
}
