<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\StudentPasswordAudit;
use App\Models\User;
use App\Services\StudentPasswordCipher;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPasswordToolTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = Student::factory()->create(['teacher_id' => $this->teacher->id]);
        app(StudentPasswordService::class)->apply($this->student, 'Goc@2026');
        $this->student->refresh();
    }

    /** Đăng nhập kèm dấu đã xác thực lại mật khẩu (middleware password.confirm). */
    private function actingAsConfirmedTeacher(?User $user = null): self
    {
        $this->actingAs($user ?? $this->teacher)
            ->withSession(['auth.password_confirmed_at' => time()]);

        return $this;
    }

    public function test_tool_page_requires_password_confirmation(): void
    {
        $this->actingAs($this->teacher)
            ->get('/admin/student-password-tool')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_tool_page_is_reachable_after_confirming(): void
    {
        $this->actingAsConfirmedTeacher()
            ->get('/admin/student-password-tool')
            ->assertOk();
    }

    public function test_decrypt_returns_the_stored_password(): void
    {
        $response = $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/decrypt', [
            'student_code' => $this->student->student_code,
            'payload' => $this->student->password_encrypted,
        ]);

        $response->assertRedirect();
        $this->assertSame('Goc@2026', session('tool_result')['password']);
    }

    public function test_decrypt_with_mismatched_code_reports_an_error(): void
    {
        $other = Student::factory()->create(['teacher_id' => $this->teacher->id]);

        $response = $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/decrypt', [
            'student_code' => $other->student_code,
            'payload' => $this->student->password_encrypted,
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tool_result'));
    }

    public function test_encrypt_without_apply_does_not_change_the_login_password(): void
    {
        $originalHash = $this->student->password;

        $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/encrypt', [
            'student_code' => $this->student->student_code,
            'password' => 'Khac@2026',
        ])->assertRedirect();

        $this->student->refresh();
        $this->assertSame($originalHash, $this->student->password);
        $this->assertTrue(Hash::check('Goc@2026', $this->student->password));
        $this->assertNotNull(session('tool_result')['payload']);
    }

    /**
     * Kịch bản chính: giáo viên đã lỡ đưa cho học sinh mật khẩu "DaDua@99";
     * dùng chế độ mã hóa + áp dụng để biến nó thành mật khẩu thật.
     */
    public function test_encrypt_with_apply_syncs_hash_and_ciphertext(): void
    {
        $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/encrypt', [
            'student_code' => $this->student->student_code,
            'password' => 'DaDua@99',
            'apply' => '1',
        ])->assertRedirect();

        $this->student->refresh();

        $this->assertTrue(Hash::check('DaDua@99', $this->student->password));
        $this->assertSame(
            'DaDua@99',
            app(StudentPasswordCipher::class)->decrypt($this->student->student_code, $this->student->password_encrypted)
        );
        $this->assertTrue(auth('student')->attempt([
            'username' => $this->student->username,
            'password' => 'DaDua@99',
        ]));
    }

    public function test_scan_finds_the_student_owning_a_ciphertext(): void
    {
        Student::factory()->count(3)->create(['teacher_id' => $this->teacher->id]);

        $response = $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/scan', [
            'payload' => $this->student->password_encrypted,
        ]);

        $response->assertRedirect();
        $this->assertSame($this->student->student_code, session('tool_result')['code']);
        $this->assertSame('Goc@2026', session('tool_result')['password']);
    }

    public function test_scan_rejects_a_malformed_payload(): void
    {
        $this->actingAsConfirmedTeacher()
            ->post('/admin/student-password-tool/scan', ['payload' => 'khong-phai-ma-hoa'])
            ->assertSessionHas('error');
    }

    public function test_another_teacher_cannot_decrypt_someone_elses_student(): void
    {
        $intruder = User::factory()->teacher()->create();

        $this->actingAsConfirmedTeacher($intruder)->post('/admin/student-password-tool/decrypt', [
            'student_code' => $this->student->student_code,
            'payload' => $this->student->password_encrypted,
        ])->assertNotFound();
    }

    public function test_another_teachers_student_is_invisible_to_scan(): void
    {
        $intruder = User::factory()->teacher()->create();

        $this->actingAsConfirmedTeacher($intruder)
            ->post('/admin/student-password-tool/scan', ['payload' => $this->student->password_encrypted])
            ->assertSessionHas('error');
    }

    public function test_admin_can_reach_any_students_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsConfirmedTeacher($admin)->post('/admin/student-password-tool/decrypt', [
            'student_code' => $this->student->student_code,
            'payload' => $this->student->password_encrypted,
        ])->assertRedirect();

        $this->assertSame('Goc@2026', session('tool_result')['password']);
    }

    public function test_each_tool_action_is_audited_without_storing_the_password(): void
    {
        StudentPasswordAudit::query()->delete();

        $this->actingAsConfirmedTeacher()->post('/admin/student-password-tool/decrypt', [
            'student_code' => $this->student->student_code,
            'payload' => $this->student->password_encrypted,
        ]);

        $audit = StudentPasswordAudit::query()->latest('id')->first();

        $this->assertSame('decrypt', $audit->action);
        $this->assertSame($this->teacher->id, $audit->user_id);
        $this->assertSame($this->student->id, $audit->student_id);
        $this->assertStringNotContainsString('Goc@2026', json_encode($audit->toArray()));
    }
}
