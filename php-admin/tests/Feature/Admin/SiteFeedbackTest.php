<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SiteFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteFeedbackTest extends TestCase
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

    public function test_teacher_can_submit_feedback_with_page_url(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->postJson(route('site-feedback.store'), [
            'body' => 'Nút lưu bị lỗi trên trang quiz.',
            'priority' => 'high',
            'page_url' => '/admin/quizzes/1/edit',
            'page_title' => 'Sửa quiz — Hóa Thầy Đạt',
            'images' => [
                UploadedFile::fake()->image('screenshot.jpg'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 1);

        $this->assertDatabaseHas('site_feedback', [
            'user_id' => $teacher->id,
            'page_url' => '/admin/quizzes/1/edit',
            'priority' => 'high',
            'status' => 'new',
        ]);

        $this->assertDatabaseCount('site_feedback_attachments', 1);
    }

    public function test_teacher_only_sees_own_feedback_on_index(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();

        SiteFeedback::create([
            'user_id' => $teacher->id,
            'page_url' => '/admin/dashboard',
            'body' => 'Góp ý của tôi',
            'priority' => 'medium',
        ]);

        SiteFeedback::create([
            'user_id' => $other->id,
            'page_url' => '/admin/games',
            'body' => 'Góp ý người khác',
            'priority' => 'low',
        ]);

        $response = $this->actingAs($teacher)->get(route('admin.feedback.index'));

        $response->assertOk()
            ->assertSee('Góp ý của tôi')
            ->assertDontSee('Góp ý người khác');
    }

    public function test_admin_sees_all_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();

        SiteFeedback::create([
            'user_id' => $teacher->id,
            'page_url' => '/admin/dashboard',
            'body' => 'Góp ý từ giáo viên',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.feedback.index'));

        $response->assertOk()
            ->assertSee('Góp ý từ giáo viên')
            ->assertSee($teacher->name);
    }
}
