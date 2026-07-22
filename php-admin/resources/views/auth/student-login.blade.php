@extends('layouts.blank')

@section('title', 'Đăng nhập học sinh — Hóa Thầy Đạt')

@php $slCssV = file_exists(public_path('htd-admin/css/student-login.css')) ? filemtime(public_path('htd-admin/css/student-login.css')) : time(); @endphp
@section('head')
<link rel="stylesheet" href="{{ asset('htd-admin/css/student-login.css') }}?v={{ $slCssV }}">
@endsection

@section('content')
<div class="sl-stage">
    <div class="sl-panel">
        <h1 class="sl-panel__title">Đăng nhập học sinh</h1>
        <p class="sl-panel__hint">Dùng tên đăng nhập và mật khẩu do thầy cô cấp.</p>

        @if ($errors->any())
            <div class="sl-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('student.login') }}" class="sl-form">
            @csrf
            <label for="username" class="sl-label">Tên đăng nhập</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                   autocomplete="username" class="sl-input">

            <label for="password" class="sl-label">Mật khẩu</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="sl-input">

            <button type="submit" class="sl-submit">Vào học</button>
        </form>

        <p class="sl-footnote">Quên mật khẩu? Hãy liên hệ thầy cô để được cấp lại.</p>
    </div>
</div>
@endsection
