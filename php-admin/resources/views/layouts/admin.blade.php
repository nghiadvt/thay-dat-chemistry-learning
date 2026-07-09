<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin — Hóa Thầy Đạt')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ file_exists(public_path('css/admin.css')) ? filemtime(public_path('css/admin.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin-list.css') }}?v={{ file_exists(public_path('css/admin-list.css')) ? filemtime(public_path('css/admin-list.css')) : time() }}">
    @php $fbWidgetCss = public_path('css/feedback-widget.css'); @endphp
    <link rel="stylesheet" href="{{ asset('css/feedback-widget.css') }}?v={{ file_exists($fbWidgetCss) ? filemtime($fbWidgetCss) : time() }}">
    @stack('head')
</head>
<body class="admin-body @yield('body-class')"
      data-tags-index-url="{{ route('admin.tags.index') }}"
      data-tags-store-url="{{ route('admin.tags.store') }}"
      data-tags-update-url="{{ url('/admin/tags') }}">
@include('admin.partials.tag-create-modal')

<div class="admin-shell" id="adminShell">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Menu điều hướng">
        <div class="admin-brand">
            <h1>Hóa Thầy Đạt</h1>
            <p>Quản trị nội dung</p>
        </div>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Tổng quan</a>
            <a href="{{ route('admin.keyboards.index') }}" class="{{ request()->routeIs('admin.keyboards.*') ? 'active' : '' }}">Bàn phím</a>
            <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}">Game</a>
            <a href="{{ route('admin.quizzes.index') }}" class="{{ request()->routeIs('admin.quizzes.*') || request()->routeIs('admin.questions.*') ? 'active' : '' }}">Quiz</a>
            <a href="{{ route('admin.question-bank.index') }}" class="{{ request()->routeIs('admin.question-bank.*') ? 'active' : '' }}">Bộ câu hỏi</a>
            <a href="{{ route('admin.sessions.index') }}" class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}">Phòng chơi</a>
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Báo cáo</a>
            <a href="{{ route('admin.feedback.index') }}" class="{{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}">Góp ý</a>
        </nav>
    </aside>

    <div class="admin-main">
        @unless(request()->routeIs('admin.keyboards.editor', 'admin.questions.create', 'admin.questions.edit', 'admin.question-bank.create', 'admin.question-bank.edit'))
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Đóng/mở menu" aria-expanded="true" aria-controls="adminSidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h2>@yield('page-title', 'Admin')</h2>
            </div>
            <div class="admin-user">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Đăng xuất</button>
                </form>
            </div>
        </header>
        @else
        <div class="admin-topbar admin-topbar--minimal">
            <button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Đóng/mở menu" aria-expanded="true" aria-controls="adminSidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
        @endunless

        <main class="admin-content @yield('content-class')">
            @yield('content')
        </main>
    </div>
</div>
<div id="adminToastHost" class="admin-toast-host" aria-live="polite"></div>
@if (session('success') || session('error') || session('warning'))
<script>
window.__ADMIN_FLASH__ = @json(array_filter([
    'type' => session('error') ? 'error' : (session('warning') ? 'warning' : 'success'),
    'message' => session('error') ?? session('warning') ?? session('success'),
]));
</script>
@endif
<script src="{{ asset('js/admin-list-page.js') }}?v={{ file_exists(public_path('js/admin-list-page.js')) ? filemtime(public_path('js/admin-list-page.js')) : time() }}"></script>
<script src="{{ asset('js/admin-data-table.js') }}?v={{ file_exists(public_path('js/admin-data-table.js')) ? filemtime(public_path('js/admin-data-table.js')) : time() }}"></script>
<script src="{{ asset('js/admin-toast.js') }}?v={{ file_exists(public_path('js/admin-toast.js')) ? filemtime(public_path('js/admin-toast.js')) : time() }}"></script>
<script src="{{ asset('js/admin-sidebar.js') }}?v={{ file_exists(public_path('js/admin-sidebar.js')) ? filemtime(public_path('js/admin-sidebar.js')) : time() }}"></script>
<script>window.TAG_PRESET_COLORS = @json(\App\Models\Tag::PRESET_COLORS);</script>
<script src="{{ asset('js/admin-tags.js') }}?v={{ file_exists(public_path('js/admin-tags.js')) ? filemtime(public_path('js/admin-tags.js')) : time() }}"></script>
@if (session('success') || session('error') || session('warning'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.__ADMIN_FLASH__ && window.AdminToast) {
        AdminToast.show(window.__ADMIN_FLASH__.message, window.__ADMIN_FLASH__.type);
    }
});
</script>
@endif
@stack('scripts')
@include('admin.partials.feedback-widget')
@php $fbWidgetJs = public_path('js/feedback-widget.js'); @endphp
<script src="{{ asset('js/feedback-widget.js') }}?v={{ file_exists($fbWidgetJs) ? filemtime($fbWidgetJs) : time() }}"></script>
</body>
</html>
