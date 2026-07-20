<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StudentLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $password = 'Hoc@2026'): Student
    {
        $student = Student::factory()->create();
        app(StudentPasswordService::class)->apply($student, $password);

        return $student->refresh();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('student-login:');
        parent::tearDown();
    }

    public function test_student_can_log_in_with_correct_credentials(): void
    {
        $student = $this->makeStudent();

        $response = $this->post('/student/login', [
            'username' => $student->username,
            'password' => 'Hoc@2026',
        ]);

        $response->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertNotNull($student->refresh()->last_login_at);
    }

    public function test_wrong_password_increments_failed_attempts(): void
    {
        $student = $this->makeStudent();

        $this->post('/student/login', [
            'username' => $student->username,
            'password' => 'sai-roi',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('student');
        $this->assertSame(1, $student->refresh()->failed_attempts);
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $student = $this->makeStudent();

        for ($i = 0; $i < Student::MAX_FAILED_ATTEMPTS; $i++) {
            $this->post('/student/login', [
                'username' => $student->username,
                'password' => 'sai-roi-'.$i,
            ]);
        }

        $student->refresh();
        $this->assertSame('locked', $student->status);
        $this->assertNotNull($student->locked_at);

        // Đúng mật khẩu cũng không vào được khi đã bị khóa.
        $this->post('/student/login', [
            'username' => $student->username,
            'password' => 'Hoc@2026',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('student');
    }

    public function test_successful_login_resets_the_failed_counter(): void
    {
        $student = $this->makeStudent();

        $this->post('/student/login', ['username' => $student->username, 'password' => 'sai']);
        $this->assertSame(1, $student->refresh()->failed_attempts);

        $this->post('/student/login', ['username' => $student->username, 'password' => 'Hoc@2026']);

        $this->assertSame(0, $student->refresh()->failed_attempts);
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $student = $this->makeStudent();
        $student->forceFill(['status' => 'disabled'])->save();

        $this->post('/student/login', [
            'username' => $student->username,
            'password' => 'Hoc@2026',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('student');
    }

    public function test_unknown_username_does_not_reveal_whether_it_exists(): void
    {
        $this->makeStudent();

        $response = $this->post('/student/login', [
            'username' => 'khong-ton-tai',
            'password' => 'bat-ky',
        ]);

        $response->assertSessionHasErrors(['username' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
    }

    public function test_student_session_is_separate_from_teacher_guard(): void
    {
        $student = $this->makeStudent();

        $this->post('/student/login', ['username' => $student->username, 'password' => 'Hoc@2026']);

        $this->assertAuthenticatedAs($student, 'student');
        $this->assertGuest('web');
    }
}
