@extends('layouts.app')

@section('title', 'Đăng nhập — Hóa Thầy Đạt')

@section('content')
<div class="card" style="max-width:420px;margin:48px auto;">
    <h1 style="margin-top:0;">Đăng nhập giáo viên</h1>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', 'teacher@hoadat.local') }}" required autofocus>

        <label for="password">Mật khẩu</label>
        <input id="password" type="password" name="password" required>

        <button type="submit" class="btn btn-primary" style="width:100%;">Đăng nhập</button>
    </form>

    <p style="margin-top:16px;color:#6b7280;font-size:14px;">
        Tài khoản seed: <code>teacher@hoadat.local</code> / <code>password123</code>
    </p>
</div>
@endsection
