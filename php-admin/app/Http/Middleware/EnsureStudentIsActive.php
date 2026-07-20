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

        if ($student && ! $student->isActive()) {
            Auth::guard('student')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $student->isLocked()
                ? 'Tài khoản đang bị khóa. Hãy liên hệ giáo viên để mở lại.'
                : 'Tài khoản đã ngừng sử dụng. Hãy liên hệ giáo viên.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'data' => null, 'error' => $message], 403);
            }

            return redirect()->route('student.login')->withErrors(['username' => $message]);
        }

        return $next($request);
    }
}
