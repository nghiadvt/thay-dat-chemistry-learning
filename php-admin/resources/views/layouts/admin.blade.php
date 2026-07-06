<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin — Hóa Thầy Đạt')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('head')
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <h1>Hóa Thầy Đạt</h1>
            <p>Quản trị nội dung</p>
        </div>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Tổng quan</a>
            <a href="{{ route('admin.keyboards.index') }}" class="{{ request()->routeIs('admin.keyboards.*') ? 'active' : '' }}">Bàn phím</a>
            <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}">Game</a>
            <a href="{{ route('admin.quizzes.index') }}" class="{{ request()->routeIs('admin.quizzes.*') || request()->routeIs('admin.questions.*') ? 'active' : '' }}">Quiz</a>
            <a href="{{ route('admin.sessions.create') }}" class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}">Phòng chơi</a>
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Báo cáo</a>
            <a href="/app/teacher.html" target="_blank" rel="noopener">Màn host →</a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <h2>@yield('page-title', 'Admin')</h2>
            <div class="admin-user">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Đăng xuất</button>
                </form>
            </div>
        </header>

        <main class="admin-content">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
