<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\StudentPasswordCipher;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
    }

    public function test_teacher_can_create_a_student_with_synced_password(): void
    {
        $this->actingAs($this->teacher)->post('/admin/students', [
            'display_name' => 'Nguyễn Văn A',
            'username' => 'hs10-01',
            'password' => 'Tao@2026',
        ])->assertRedirect(route('admin.students.index'));

        $student = Student::query()->where('username', 'hs10-01')->firstOrFail();

        $this->assertTrue(Hash::check('Tao@2026', $student->password));
        $this->assertSame(
            'Tao@2026',
            app(StudentPasswordCipher::class)->decrypt($student->student_code, $student->password_encrypted)
        );
        $this->assertNotEmpty($student->student_code);
    }

    public function test_created_student_can_immediately_log_in(): void
    {
        $this->actingAs($this->teacher)->post('/admin/students', [
            'display_name' => 'Nguyễn Văn A',
            'username' => 'hs10-01',
            'password' => 'Tao@2026',
        ]);

        $this->assertTrue(auth('student')->attempt(['username' => 'hs10-01', 'password' => 'Tao@2026']));
    }

    public function test_creating_without_a_password_generates_a_working_one(): void
    {
        $this->actingAs($this->teacher)->post('/admin/students', [
            'display_name' => 'Nguyễn Văn B',
            'username' => 'hs10-02',
        ])->assertRedirect(route('admin.students.index'));

        $generated = session('generated_credentials')[0];

        $this->assertSame('hs10-02', $generated['username']);
        $this->assertNotEmpty($generated['password']);
        $this->assertTrue(auth('student')->attempt([
            'username' => 'hs10-02',
            'password' => $generated['password'],
        ]));
    }

    public function test_bulk_generate_creates_accounts_with_unique_usernames_and_codes(): void
    {
        $class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->post('/admin/students/bulk-generate', [
            'class_id' => $class->id,
            'quantity' => 10,
        ])->assertRedirect();

        $students = Student::query()->where('class_id', $class->id)->get();

        $this->assertCount(10, $students);
        $this->assertCount(10, $students->pluck('username')->unique());
        $this->assertCount(10, $students->pluck('student_code')->unique());

        // Mọi tài khoản vừa tạo đều đăng nhập được bằng mật khẩu in trên phiếu.
        $credentials = collect(session('generated_credentials'));
        $this->assertCount(10, $credentials);

        foreach ($credentials as $row) {
            $this->assertTrue(
                auth('student')->attempt(['username' => $row['username'], 'password' => $row['password']]),
                'Không đăng nhập được với '.$row['username']
            );
        }
    }

    public function test_bulk_generate_continues_numbering_on_a_second_run(): void
    {
        $class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->post('/admin/students/bulk-generate', ['class_id' => $class->id, 'quantity' => 3]);
        $this->actingAs($this->teacher)->post('/admin/students/bulk-generate', ['class_id' => $class->id, 'quantity' => 3]);

        $usernames = Student::query()->where('class_id', $class->id)->pluck('username');

        $this->assertCount(6, $usernames);
        $this->assertCount(6, $usernames->unique());
    }

    public function test_reset_password_keeps_hash_and_ciphertext_in_sync(): void
    {
        $student = Student::factory()->create(['teacher_id' => $this->teacher->id]);
        app(StudentPasswordService::class)->apply($student, 'Cu@2026');

        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$student->id.'/reset-password')
            ->assertRedirect();

        $newPassword = session('generated_credentials')[0]['password'];
        $student->refresh();

        $this->assertTrue(Hash::check($newPassword, $student->password));
        $this->assertSame(
            $newPassword,
            app(StudentPasswordCipher::class)->decrypt($student->student_code, $student->password_encrypted)
        );
        $this->assertFalse(Hash::check('Cu@2026', $student->password));
    }

    public function test_unlock_clears_the_failed_attempt_counter(): void
    {
        $student = Student::factory()->locked()->create(['teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->post('/admin/students/'.$student->id.'/unlock')->assertRedirect();

        $student->refresh();
        $this->assertSame('active', $student->status);
        $this->assertSame(0, $student->failed_attempts);
        $this->assertNull($student->locked_at);
    }

    public function test_update_cannot_change_the_student_code(): void
    {
        $student = Student::factory()->create(['teacher_id' => $this->teacher->id]);
        $originalCode = $student->student_code;

        $this->actingAs($this->teacher)->put('/admin/students/'.$student->id, [
            'display_name' => 'Tên mới',
            'username' => $student->username,
            'status' => 'active',
            'student_code' => 'HS-HACKED',
        ])->assertRedirect();

        $this->assertSame($originalCode, $student->refresh()->student_code);
        $this->assertSame('Tên mới', $student->display_name);
    }

    public function test_deleting_a_student_is_a_soft_delete(): void
    {
        $student = Student::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->delete('/admin/students/'.$student->id)->assertRedirect();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    public function test_teacher_cannot_touch_another_teachers_student(): void
    {
        $other = User::factory()->teacher()->create();
        $student = Student::factory()->create(['teacher_id' => $other->id]);

        $this->actingAs($this->teacher)->get('/admin/students/'.$student->id.'/edit')->assertForbidden();
        $this->actingAs($this->teacher)->post('/admin/students/'.$student->id.'/reset-password')->assertForbidden();
        $this->actingAs($this->teacher)->delete('/admin/students/'.$student->id)->assertForbidden();
    }

    public function test_index_only_lists_own_students(): void
    {
        $mine = Student::factory()->create(['teacher_id' => $this->teacher->id, 'display_name' => 'Học sinh của tôi']);
        $theirs = Student::factory()->create(['display_name' => 'Học sinh người khác']);

        $this->actingAs($this->teacher)->get('/admin/students')
            ->assertOk()
            ->assertSee($mine->username)
            ->assertDontSee($theirs->username);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        Student::factory()->create(['teacher_id' => $this->teacher->id, 'username' => 'hs10-01']);

        $this->actingAs($this->teacher)->post('/admin/students', [
            'display_name' => 'Trùng tên',
            'username' => 'hs10-01',
        ])->assertSessionHasErrors('username');
    }

    public function test_credentials_sheet_shows_readable_passwords_for_the_class(): void
    {
        $class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->actingAs($this->teacher)->post('/admin/students/bulk-generate', ['class_id' => $class->id, 'quantity' => 2]);
        $credentials = collect(session('generated_credentials'));

        $response = $this->actingAs($this->teacher)->get('/admin/students/classes/'.$class->id.'/credentials');

        $response->assertOk();
        foreach ($credentials as $row) {
            $response->assertSee($row['username']);
            $response->assertSee($row['password']);
        }
    }

    public function test_all_student_pages_render(): void
    {
        $class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);
        $student = Student::factory()->create(['teacher_id' => $this->teacher->id, 'class_id' => $class->id]);

        $this->actingAs($this->teacher)->get('/admin/students')->assertOk();
        $this->actingAs($this->teacher)->get('/admin/students?q='.$student->username)->assertOk()->assertSee($student->display_name);
        $this->actingAs($this->teacher)->get('/admin/students/create')->assertOk();
        $this->actingAs($this->teacher)->get('/admin/students/'.$student->id.'/edit')->assertOk();
        $this->actingAs($this->teacher)->get('/admin/students/classes/'.$class->id)->assertOk()->assertSee($student->display_name);
        $this->actingAs($this->teacher)->get(route('password.confirm'))->assertOk();

        // Route danh sách lớp cũ gộp vào trang Học sinh.
        $this->actingAs($this->teacher)->get('/admin/students/classes')->assertRedirect(route('admin.students.index'));

        // Trang đăng nhập học sinh là công khai.
        $this->get('/student/login')->assertOk()->assertSee('Đăng nhập học sinh');
    }

    public function test_teacher_cannot_view_another_teachers_class_page(): void
    {
        $class = StudentClass::factory()->create();

        $this->actingAs($this->teacher)->get('/admin/students/classes/'.$class->id)->assertForbidden();
    }

    public function test_bulk_generate_with_pasted_names_uses_them_as_display_names(): void
    {
        $class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->post('/admin/students/bulk-generate', [
            'class_id' => $class->id,
            'names' => "Nguyễn Văn An\n\n  Trần Thị Bình  \nLê Văn Cường\n",
        ])->assertRedirect(route('admin.students.classes.show', $class));

        $students = Student::query()->where('class_id', $class->id)->orderBy('id')->get();

        $this->assertSame(['Nguyễn Văn An', 'Trần Thị Bình', 'Lê Văn Cường'], $students->pluck('display_name')->all());
        $this->assertCount(3, $students->pluck('username')->unique());
        $this->assertCount(3, session('generated_credentials'));
    }

    public function test_teacher_cannot_print_another_teachers_class_sheet(): void
    {
        $class = StudentClass::factory()->create();

        $this->actingAs($this->teacher)
            ->get('/admin/students/classes/'.$class->id.'/credentials')
            ->assertForbidden();
    }
}
