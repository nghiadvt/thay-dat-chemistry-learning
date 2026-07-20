<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAccountTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->create([
            'display_name' => 'Tên cũ',
            'class_id' => StudentClass::factory(),
        ]);
        app(StudentPasswordService::class)->apply($this->student, 'Hoc@2026');
        $this->student->refresh();
    }

    public function test_me_returns_the_logged_in_students_profile(): void
    {
        $response = $this->actingAs($this->student, 'student')->getJson('/api/student/me');

        $response->assertOk()
            ->assertJsonPath('data.username', $this->student->username)
            ->assertJsonPath('data.display_name', 'Tên cũ')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_guest_cannot_read_the_profile(): void
    {
        $this->getJson('/api/student/me')->assertUnauthorized();
    }

    public function test_student_can_change_display_name_with_correct_password(): void
    {
        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', [
                'display_name' => 'Tên mới',
                'current_password' => 'Hoc@2026',
            ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Tên mới');

        $this->assertSame('Tên mới', $this->student->refresh()->display_name);
    }

    public function test_wrong_password_is_rejected_and_counted(): void
    {
        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', [
                'display_name' => 'Tên mới',
                'current_password' => 'sai-roi',
            ])
            ->assertStatus(422);

        $this->student->refresh();
        $this->assertSame(1, $this->student->failed_attempts);
        $this->assertSame('Tên cũ', $this->student->display_name);
    }

    public function test_five_wrong_passwords_lock_the_account(): void
    {
        for ($i = 0; $i < Student::MAX_FAILED_ATTEMPTS; $i++) {
            $this->actingAs($this->student, 'student')
                ->patchJson('/api/student/profile', [
                    'display_name' => 'Tên mới',
                    'current_password' => 'sai-'.$i,
                ]);
        }

        $this->student->refresh();
        $this->assertSame('locked', $this->student->status);
        $this->assertNotNull($this->student->locked_at);
    }

    public function test_a_correct_password_clears_the_failed_counter(): void
    {
        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', ['display_name' => 'X', 'current_password' => 'sai']);
        $this->assertSame(1, $this->student->refresh()->failed_attempts);

        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', ['display_name' => 'Y', 'current_password' => 'Hoc@2026'])
            ->assertOk();

        $this->assertSame(0, $this->student->refresh()->failed_attempts);
    }

    public function test_locked_student_is_blocked_even_with_a_live_session(): void
    {
        $this->student->forceFill(['status' => 'locked', 'locked_at' => now()])->save();

        $this->actingAs($this->student, 'student')
            ->getJson('/api/student/me')
            ->assertForbidden();
    }

    public function test_student_can_change_password_and_log_in_with_it(): void
    {
        $this->actingAs($this->student, 'student')
            ->putJson('/api/student/password', [
                'current_password' => 'Hoc@2026',
                'password' => 'Moi@2026',
                'password_confirmation' => 'Moi@2026',
            ])
            ->assertOk();

        $this->student->refresh();
        $this->assertTrue(Hash::check('Moi@2026', $this->student->password));
        $this->assertTrue(auth('student')->attempt([
            'username' => $this->student->username,
            'password' => 'Moi@2026',
        ]));
    }

    /**
     * Học sinh tự đổi mật khẩu vẫn phải cập nhật bản mã, nếu không giáo viên
     * sẽ xem thấy mật khẩu cũ và tưởng học sinh nhập sai.
     */
    public function test_self_service_password_change_keeps_the_teacher_view_in_sync(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($this->student, 'student')
            ->putJson('/api/student/password', [
                'current_password' => 'Hoc@2026',
                'password' => 'Moi@2026',
                'password_confirmation' => 'Moi@2026',
            ])
            ->assertOk();

        $revealed = app(StudentPasswordService::class)->reveal($this->student->refresh(), $teacher);

        $this->assertSame('Moi@2026', $revealed);
    }

    public function test_password_change_requires_confirmation_to_match(): void
    {
        $this->actingAs($this->student, 'student')
            ->putJson('/api/student/password', [
                'current_password' => 'Hoc@2026',
                'password' => 'Moi@2026',
                'password_confirmation' => 'Khac@2026',
            ])
            ->assertStatus(422);

        $this->assertTrue(Hash::check('Hoc@2026', $this->student->refresh()->password));
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        $this->actingAs($this->student, 'student')
            ->putJson('/api/student/password', [
                'current_password' => 'Hoc@2026',
                'password' => 'Hoc@2026',
                'password_confirmation' => 'Hoc@2026',
            ])
            ->assertStatus(422);
    }

    public function test_password_change_with_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->student, 'student')
            ->putJson('/api/student/password', [
                'current_password' => 'sai-roi',
                'password' => 'Moi@2026',
                'password_confirmation' => 'Moi@2026',
            ])
            ->assertStatus(422);

        $this->assertTrue(Hash::check('Hoc@2026', $this->student->refresh()->password));
    }

    public function test_student_can_upload_an_avatar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student, 'student')
            ->post('/api/student/avatar', [
                'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
                'current_password' => 'Hoc@2026',
            ])
            ->assertOk();

        $this->student->refresh();
        $this->assertNotNull($this->student->avatar_path);
        Storage::disk('public')->assertExists($this->student->avatar_path);
    }

    public function test_uploading_a_new_avatar_removes_the_previous_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student, 'student')->post('/api/student/avatar', [
            'avatar' => UploadedFile::fake()->image('first.png'),
            'current_password' => 'Hoc@2026',
        ]);
        $first = $this->student->refresh()->avatar_path;

        $this->actingAs($this->student, 'student')->post('/api/student/avatar', [
            'avatar' => UploadedFile::fake()->image('second.png'),
            'current_password' => 'Hoc@2026',
        ]);
        $second = $this->student->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student, 'student')
            ->post('/api/student/avatar', [
                'avatar' => UploadedFile::fake()->create('virus.exe', 10),
                'current_password' => 'Hoc@2026',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertNull($this->student->refresh()->avatar_path);
    }

    public function test_student_cannot_change_protected_fields(): void
    {
        $originalUsername = $this->student->username;
        $originalCode = $this->student->student_code;

        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', [
                'display_name' => 'Tên mới',
                'current_password' => 'Hoc@2026',
                'username' => 'hacker',
                'student_code' => 'HS-HACKED',
                'status' => 'active',
                'class_id' => 999,
            ])
            ->assertOk();

        $this->student->refresh();
        $this->assertSame($originalUsername, $this->student->username);
        $this->assertSame($originalCode, $this->student->student_code);
    }

    public function test_display_name_cannot_be_blank(): void
    {
        $this->actingAs($this->student, 'student')
            ->patchJson('/api/student/profile', [
                'display_name' => '   ',
                'current_password' => 'Hoc@2026',
            ])
            ->assertStatus(422);
    }
}
