<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn học sinh đã bị khóa/ngừng sử dụng ngay cả khi phiên đăng nhập vẫn còn.
 *
 * Cần thiết vì giáo viên có thể khóa tài khoản trong lúc học sinh đang mở app —
 * nếu chỉ kiểm tra ở màn đăng nhập thì phiên cũ vẫn dùng được tiếp.
 */
class EnsureStudentIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student');

        $classInactive = $student && $student->studentClass && ! $student->studentClass->is_active;

        if ($student && (! $student->isActive() || $classInactive)) {
            Auth::guard('student')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($classInactive) {
                $message = 'Lớp học đã tạm ngừng hoạt động. Vui lòng liên hệ giáo viên/admin.';
            } else {
                $byTeacher = $student->latestLockLog?->locked_by_teacher ?? false;
                $message = $byTeacher
                    ? 'Tài khoản đã bị khóa bởi giáo viên. Hãy liên hệ giáo viên/admin để mở lại.'
                    : 'Tài khoản đang bị khóa do nhập sai nhiều lần. Hãy liên hệ giáo viên để mở lại.';
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'data' => null, 'error' => $message], 403);
            }

            return redirect()->route('student.login')->withErrors(['username' => $message]);
        }

        return $next($request);
    }
}
