@extends('layouts.app')

@section('title', 'Xác nhận mật khẩu — Hóa Thầy Đạt')

@section('content')
<div class="card" style="max-width:420px;margin:48px auto;">
    <h1 style="margin-top:0;">Xác nhận mật khẩu</h1>
    <p style="color:#6b7280;margin-top:0;">
        Đây là khu vực hiển thị mật khẩu học sinh. Vui lòng nhập lại mật khẩu của bạn để tiếp tục.
    </p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <label for="password">Mật khẩu</label>
        <input id="password" type="password" name="password" required autofocus autocomplete="current-password">

        <button type="submit" class="btn btn-primary" style="width:100%;">Xác nhận</button>
    </form>
</div>
@endsection
