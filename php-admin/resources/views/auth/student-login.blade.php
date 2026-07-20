@extends('layouts.app')

@section('title', 'Đăng nhập học sinh — Hóa Thầy Đạt')

@section('content')
<div class="card" style="max-width:420px;margin:48px auto;">
    <h1 style="margin-top:0;">Đăng nhập học sinh</h1>
    <p style="color:#6b7280;margin-top:0;">Dùng tên đăng nhập và mật khẩu do thầy cô cấp.</p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('student.login') }}">
        @csrf
        <label for="username">Tên đăng nhập</label>
        <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
               autocomplete="username"
               style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:16px;">

        <label for="password">Mật khẩu</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary" style="width:100%;">Vào học</button>
    </form>

    <p style="color:#6b7280;font-size:13px;margin-bottom:0;">
        Quên mật khẩu? Hãy liên hệ thầy cô để được cấp lại.
    </p>
</div>
@endsection
