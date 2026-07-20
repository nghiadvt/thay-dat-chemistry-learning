<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Hồ sơ tài khoản của chính học sinh đang đăng nhập.
 *
 * Học sinh chỉ được đổi tên hiển thị, ảnh đại diện và mật khẩu — KHÔNG đổi được
 * username, mã code, lớp hay trạng thái (những thứ đó do giáo viên quản lý).
 *
 * Mọi thay đổi đều bắt nhập lại mật khẩu hiện tại; nhập sai sẽ cộng dồn vào
 * cùng bộ đếm với màn đăng nhập và khóa tài khoản ở lần thứ 5.
 */
class AccountController extends Controller
{
    public function __construct(
        private StudentPasswordService $passwords,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return $this->jsonSuccess($this->profilePayload($request->user('student')));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:1', 'max:100'],
            'current_password' => ['required', 'string'],
        ]);

        if ($failure = $this->guardCurrentPassword($student, $validated['current_password'])) {
            return $failure;
        }

        $student->forceFill(['display_name' => $validated['display_name']])->save();

        return $this->jsonSuccess($this->profilePayload($student->refresh()));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:64', 'confirmed'],
        ]);

        if ($failure = $this->guardCurrentPassword($student, $validated['current_password'])) {
            return $failure;
        }

        if ($validated['password'] === $validated['current_password']) {
            return $this->jsonError('Mật khẩu mới phải khác mật khẩu hiện tại.', 422);
        }

        // Đi qua StudentPasswordService để bản mã (password_encrypted) được cập
        // nhật cùng lúc — nếu không, giáo viên sẽ vẫn xem thấy mật khẩu cũ.
        $this->passwords->apply($student, $validated['password'], null, 'apply', $request->ip());

        // Đổi mật khẩu làm thay đổi remember token / session hash.
        Auth::guard('student')->login($student->refresh());
        $request->session()->regenerate();

        return $this->jsonSuccess($this->profilePayload($student));
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['required', 'string'],
        ]);

        if ($failure = $this->guardCurrentPassword($student, $validated['current_password'])) {
            return $failure;
        }

        $oldPath = $student->avatar_path;
        $path = $request->file('avatar')->store('student-avatars', 'public');

        $student->forceFill(['avatar_path' => $path])->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $this->jsonSuccess($this->profilePayload($student->refresh()));
    }

    /**
     * Kiểm tra mật khẩu hiện tại. Trả về response lỗi nếu sai, null nếu đúng.
     */
    private function guardCurrentPassword(Student $student, string $candidate): ?JsonResponse
    {
        if (Hash::check($candidate, $student->password)) {
            if ($student->failed_attempts > 0) {
                $student->forceFill(['failed_attempts' => 0])->save();
            }

            return null;
        }

        $student->increment('failed_attempts');
        $student->refresh();

        if ($student->failed_attempts >= Student::MAX_FAILED_ATTEMPTS) {
            $student->forceFill(['status' => 'locked', 'locked_at' => now()])->save();
            Auth::guard('student')->logout();

            return $this->jsonError(
                'Bạn đã nhập sai '.Student::MAX_FAILED_ATTEMPTS.' lần. Tài khoản đã bị khóa, hãy liên hệ giáo viên để mở lại.',
                423
            );
        }

        $remaining = Student::MAX_FAILED_ATTEMPTS - $student->failed_attempts;

        return $this->jsonError('Mật khẩu hiện tại không đúng. Còn '.$remaining.' lần thử.', 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'username' => $student->username,
            'display_name' => $student->display_name,
            'student_code' => $student->student_code,
            'avatar_url' => $student->avatar_url,
            'initials' => $student->initials,
            'class_name' => $student->studentClass?->name,
            'status' => $student->status,
        ];
    }
}
