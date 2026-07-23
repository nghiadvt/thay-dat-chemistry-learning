<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentLoginController extends Controller
{
    public function __construct(private StudentLockService $locks) {}

    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.home');
        }

        return view('auth.student-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureNotRateLimited($request, $validated['username']);

        $student = Student::query()->where('username', $validated['username'])->first();

        // Thông báo chung để không tiết lộ username nào có tồn tại.
        $generic = ['username' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];

        if ($student === null) {
            RateLimiter::hit($this->throttleKey($request, $validated['username']));

            return back()->withInput($request->only('username'))->withErrors($generic);
        }

        if ($student->studentClass && ! $student->studentClass->is_active) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Lớp học đã tạm ngừng hoạt động. Vui lòng liên hệ giáo viên/admin.']);
        }

        if ($student->isLocked()) {
            $byTeacher = $student->latestLockLog?->locked_by_teacher ?? false;

            $message = $byTeacher
                ? 'Tài khoản đã bị khóa bởi giáo viên. Vui lòng liên hệ giáo viên/admin để được hỗ trợ.'
                : 'Tài khoản đang bị khóa do nhập sai nhiều lần. Liên hệ giáo viên để mở khóa.';

            return back()->withInput($request->only('username'))
                ->withErrors(['username' => $message]);
        }

        if (! Auth::guard('student')->attempt($validated)) {
            RateLimiter::hit($this->throttleKey($request, $validated['username']));

            $student->increment('failed_attempts');
            $student->refresh();

            if ($student->failed_attempts >= Student::MAX_FAILED_ATTEMPTS) {
                $this->locks->lock($student, actor: null, ip: $request->ip(), byTeacher: false);

                return back()->withInput($request->only('username'))
                    ->withErrors(['username' => 'Đã nhập sai '.Student::MAX_FAILED_ATTEMPTS.' lần, tài khoản bị khóa. Liên hệ giáo viên để mở khóa.']);
            }

            $remaining = Student::MAX_FAILED_ATTEMPTS - $student->failed_attempts;

            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Sai mật khẩu. Còn '.$remaining.' lần thử trước khi khóa tài khoản.']);
        }

        RateLimiter::clear($this->throttleKey($request, $validated['username']));

        $student->forceFill([
            'failed_attempts' => 0,
            'locked_at' => null,
            'last_login_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return redirect()->intended(route('student.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }

    /**
     * Chặn dò mật khẩu ở tầng IP + username, độc lập với bộ đếm failed_attempts
     * của từng tài khoản.
     */
    private function ensureNotRateLimited(Request $request, string $username): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $username), 10)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $username));

        throw ValidationException::withMessages([
            'username' => 'Thử quá nhiều lần. Vui lòng đợi '.$seconds.' giây.',
        ]);
    }

    private function throttleKey(Request $request, string $username): string
    {
        return 'student-login:'.mb_strtolower($username).'|'.$request->ip();
    }
}
