<?php

namespace Tests\Feature\Admin;

use App\Exceptions\StudentPasswordCipherException;
use App\Models\Student;
use App\Models\StudentPasswordAudit;
use App\Models\User;
use App\Services\StudentPasswordCipher;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class StudentPasswordServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentPasswordService $service;

    private StudentPasswordCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cipher = app(StudentPasswordCipher::class);
        $this->service = app(StudentPasswordService::class);
    }

    public function test_apply_writes_hash_and_ciphertext_together(): void
    {
        $student = Student::factory()->create();

        $this->service->apply($student, 'Moi@2026');
        $student->refresh();

        $this->assertTrue(Hash::check('Moi@2026', $student->password));
        $this->assertNotNull($student->password_encrypted);
        $this->assertSame('Moi@2026', $this->cipher->decrypt($student->student_code, $student->password_encrypted));
        $this->assertNotNull($student->password_updated_at);
    }

    public function test_student_can_authenticate_with_the_applied_password(): void
    {
        $student = Student::factory()->create();

        $this->service->apply($student, 'Moi@2026');

        $this->assertTrue(auth('student')->attempt([
            'username' => $student->username,
            'password' => 'Moi@2026',
        ]));
    }

    public function test_reveal_returns_the_latest_password_not_the_previous_one(): void
    {
        $student = Student::factory()->create();

        $this->service->apply($student, 'Cu@2026');
        $this->service->apply($student->refresh(), 'Moi@2026');

        $this->assertSame('Moi@2026', $this->service->reveal($student->refresh()));
    }

    /**
     * Kịch bản nghiệp vụ chính: giáo viên lỡ đưa cho học sinh một mật khẩu khác
     * với mật khẩu hệ thống đang lưu. Nhập đúng mật khẩu đã đưa rồi áp dụng thì
     * học sinh phải đăng nhập được bằng chính mật khẩu đó.
     */
    public function test_teacher_can_make_a_wrongly_handed_out_password_become_the_real_one(): void
    {
        $student = Student::factory()->create();
        $this->service->apply($student, 'HeThong@1');

        $this->service->apply($student->refresh(), 'DaDua@99');
        $student->refresh();

        $this->assertTrue(auth('student')->attempt([
            'username' => $student->username,
            'password' => 'DaDua@99',
        ]));
        $this->assertFalse(Hash::check('HeThong@1', $student->password));
        $this->assertSame('DaDua@99', $this->service->reveal($student));
    }

    public function test_reset_generates_new_password_and_keeps_both_fields_in_sync(): void
    {
        $student = Student::factory()->create();

        $plain = $this->service->reset($student);
        $student->refresh();

        $this->assertTrue(Hash::check($plain, $student->password));
        $this->assertSame($plain, $this->cipher->decrypt($student->student_code, $student->password_encrypted));
    }

    public function test_cipher_failure_leaves_the_existing_password_untouched(): void
    {
        $student = Student::factory()->create();
        $this->service->apply($student, 'Goc@2026');
        $student->refresh();
        $originalHash = $student->password;
        $originalCipher = $student->password_encrypted;

        try {
            $this->service->apply($student, '');
            $this->fail('Mật khẩu rỗng phải bị từ chối.');
        } catch (StudentPasswordCipherException) {
            // mong đợi
        }

        $student->refresh();
        $this->assertSame($originalHash, $student->password);
        $this->assertSame($originalCipher, $student->password_encrypted);
        $this->assertTrue(Hash::check('Goc@2026', $student->password));
    }

    public function test_reveal_fails_clearly_when_no_ciphertext_stored_yet(): void
    {
        $student = Student::factory()->create(['password_encrypted' => null]);

        $this->expectException(StudentPasswordCipherException::class);
        $this->service->reveal($student);
    }

    public function test_student_code_is_immutable_after_creation(): void
    {
        $student = Student::factory()->create();

        $this->expectException(LogicException::class);
        $student->student_code = 'HS-DOIMOI';
    }

    public function test_every_operation_writes_an_audit_row_without_plaintext(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = Student::factory()->create();

        $this->service->apply($student, 'Moi@2026', $teacher, 'apply', '10.0.0.9');
        $this->service->reveal($student->refresh(), $teacher, '10.0.0.9');

        $audits = StudentPasswordAudit::all();
        $this->assertCount(2, $audits);
        $this->assertEqualsCanonicalizing(['apply', 'decrypt'], $audits->pluck('action')->all());

        foreach ($audits as $audit) {
            $this->assertSame($teacher->id, $audit->user_id);
            $this->assertSame($student->id, $audit->student_id);
            $this->assertStringNotContainsString('Moi@2026', json_encode($audit->toArray()));
        }
    }
}
