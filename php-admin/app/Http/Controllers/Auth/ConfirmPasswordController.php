<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Xác thực lại mật khẩu giáo viên trước khi vào các trang nhạy cảm (công cụ
 * xem mật khẩu học sinh). Middleware `password.confirm` của Laravel sẽ chuyển
 * hướng tới đây và ghi nhớ trong 15 phút (config auth.password_timeout).
 */
class ConfirmPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Mật khẩu không đúng.']);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('admin.dashboard'));
    }
}
