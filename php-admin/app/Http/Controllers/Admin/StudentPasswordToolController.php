<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StudentPasswordCipherException;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentPasswordAudit;
use App\Services\StudentPasswordCipher;
use App\Services\StudentPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Công cụ mã hóa / giải mã mật khẩu học sinh.
 *
 * Trang này hiển thị mật khẩu ở dạng đọc được nên được bảo vệ bằng: chỉ giáo
 * viên sở hữu học sinh (hoặc admin), bắt xác thực lại mật khẩu, giới hạn tần
 * suất và ghi nhật ký mọi thao tác.
 *
 * Kết quả luôn trả về qua flash session của một redirect (POST → redirect →
 * GET) để mật khẩu không nằm trên URL và không bị lưu vào lịch sử trình duyệt.
 */
class StudentPasswordToolController extends Controller
{
    public function __construct(
        private StudentPasswordCipher $cipher,
        private StudentPasswordService $passwords,
    ) {}

    public function show(Request $request): View
    {
        $recentAudits = StudentPasswordAudit::query()
            ->with(['student:id,student_code,username', 'user:id,name'])
            ->whereHas('student', fn ($q) => $q->visibleTo($request->user()))
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.students.password-tool', compact('recentAudits'));
    }

    /**
     * Mã code + bản mã -> mật khẩu gốc.
     */
    public function decrypt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:32'],
            'payload' => ['required', 'string', 'max:2000'],
        ]);

        $student = $this->resolveStudent($request, $validated['student_code']);

        try {
            $plain = $this->cipher->decrypt($student->student_code, $validated['payload']);
        } catch (StudentPasswordCipherException $e) {
            return back()->with('error', $e->getMessage());
        }

        StudentPasswordAudit::create([
            'student_id' => $student->id,
            'user_id' => $request->user()->id,
            'action' => 'decrypt',
            'ip' => $request->ip(),
        ]);

        return back()->with('tool_result', [
            'mode' => 'decrypt',
            'student' => $student->username,
            'code' => $student->student_code,
            'password' => $plain,
        ]);
    }

    /**
     * Mã code + mật khẩu -> bản mã. Tùy chọn "apply" sẽ đặt luôn mật khẩu này
     * cho học sinh (ghi đồng thời hash + bản mã) — đây là cách sửa lại khi đã
     * lỡ đưa cho học sinh một mật khẩu khác với mật khẩu hệ thống đang lưu.
     */
    public function encrypt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'apply' => ['nullable', 'boolean'],
        ]);

        $student = $this->resolveStudent($request, $validated['student_code']);
        $apply = $request->boolean('apply');

        try {
            if ($apply) {
                // apply() tự mã hóa và ghi cả hai trường trong 1 transaction.
                $this->passwords->apply($student, $validated['password'], $request->user(), 'apply', $request->ip());
                $student->refresh();
                $payload = $student->password_encrypted;
            } else {
                $payload = $this->cipher->encrypt($student->student_code, $validated['password']);

                StudentPasswordAudit::create([
                    'student_id' => $student->id,
                    'user_id' => $request->user()->id,
                    'action' => 'encrypt',
                    'ip' => $request->ip(),
                ]);
            }
        } catch (StudentPasswordCipherException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()
            ->with($apply ? 'success' : 'status', $apply
                ? 'Đã đặt mật khẩu mới cho học sinh (hash và bản mã đã đồng bộ).'
                : 'Đã tạo bản mã. Chưa áp dụng cho học sinh.')
            ->with('tool_result', [
                'mode' => 'encrypt',
                'student' => $student->username,
                'code' => $student->student_code,
                'payload' => $payload,
                'applied' => $apply,
            ]);
    }

    /**
     * Bản mã -> tìm ra học sinh sở hữu.
     *
     * Thay thế cho việc "suy ra mã code từ mật khẩu + bản mã" (bất khả thi về
     * mật mã học): vì AEAD chỉ giải mã thành công với đúng mã code, ta quét qua
     * tập học sinh mà giáo viên đang quản lý.
     */
    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
        ]);

        $codes = Student::query()
            ->visibleTo($request->user())
            ->pluck('student_code');

        try {
            $match = $this->cipher->scan($validated['payload'], $codes);
        } catch (StudentPasswordCipherException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($match === null) {
            return back()->with('error', 'Không tìm thấy học sinh nào khớp với chuỗi mã hóa này.');
        }

        $student = Student::query()->visibleTo($request->user())
            ->where('student_code', $match['code'])
            ->firstOrFail();

        StudentPasswordAudit::create([
            'student_id' => $student->id,
            'user_id' => $request->user()->id,
            'action' => 'scan',
            'ip' => $request->ip(),
        ]);

        return back()->with('tool_result', [
            'mode' => 'scan',
            'student' => $student->username,
            'code' => $student->student_code,
            'password' => $match['password'],
        ]);
    }

    private function resolveStudent(Request $request, string $code): Student
    {
        $student = Student::query()
            ->visibleTo($request->user())
            ->where('student_code', trim($code))
            ->first();

        abort_if($student === null, 404, 'Không tìm thấy học sinh với mã code này.');

        return $student;
    }
}
