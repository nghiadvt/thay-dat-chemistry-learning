<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin — Hóa Thầy Đạt')</title>
    {{-- Áp theme trước khi render để không chớp trắng (chọn ở Admin → Giao diện) --}}
    <script>(function(){try{var t=localStorage.getItem('adminTheme');if(t&&t!=='default')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <link rel="stylesheet" href="@vasset('css/admin.css')">
    <link rel="stylesheet" href="@vasset('css/admin-theme-lab.css')">
    <link rel="stylesheet" href="@vasset('css/admin-theme-notebook.css')">
    <link rel="stylesheet" href="@vasset('css/admin-theme-arcade.css')">
    <link rel="stylesheet" href="@vasset('css/admin-theme-chalk.css')">
    <link rel="stylesheet" href="@vasset('css/admin-theme-galaxy.css')">
    <link rel="stylesheet" href="@vasset('css/admin-list.css')">
    <link rel="stylesheet" href="@vasset('css/admin-confirm.css')">
    <link rel="stylesheet" href="@vasset('css/feedback-widget.css')">
    @if (request()->routeIs('admin.students.*'))
        {{-- Khu học sinh có bộ giao diện riêng; font rơi về system-ui nếu offline --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="@vasset('css/admin-students.css')">
    @endif
    @stack('head')
</head>
<body class="admin-body @yield('body-class')"
      data-tags-index-url="{{ request()->routeIs('admin.image-cropper.*') ? route('admin.image-crop-groups.index') : route('admin.tags.index') }}"
      data-tags-store-url="{{ request()->routeIs('admin.image-cropper.*') ? route('admin.image-crop-groups.store') : route('admin.tags.store') }}"
      data-tags-update-url="{{ url('/admin/tags') }}">
@include('admin.partials.tag-create-modal')

<div class="admin-shell" id="adminShell">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Menu điều hướng">
        <div class="admin-brand">
            <button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Đóng/mở menu" aria-expanded="true" aria-controls="adminSidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="admin-brand-text">
                <h1>Hóa Thầy Đạt</h1>
                <p>Quản trị nội dung</p>
            </div>
        </div>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Tổng quan">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 11.5L12 4l8 7.5M6 10v9a1 1 0 001 1h3v-5a1 1 0 011-1h2a1 1 0 011 1v5h3a1 1 0 001-1v-9"/></svg>
                <span>Tổng quan</span>
            </a>
            <a href="{{ route('admin.keyboards.index') }}" class="{{ request()->routeIs('admin.keyboards.*') ? 'active' : '' }}" title="Bàn phím">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" stroke-linejoin="round"/><path stroke-linecap="round" d="M6.5 10h.01M10 10h.01M13.5 10h.01M17 10h.01M7 14.5h10"/></svg>
                <span>Bàn phím</span>
            </a>
            <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}" title="Game">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6a4 4 0 014 4v3a3 3 0 01-3 3c-.8 0-1.5-.4-2-1l-1-1h-4l-1 1c-.5.6-1.2 1-2 1a3 3 0 01-3-3v-3a4 4 0 014-4z"/><path stroke-linecap="round" d="M7.5 11.5v2M6.5 12.5h2M15.5 11h.01M17.5 12.5h.01"/></svg>
                <span>Game</span>
            </a>
            <a href="{{ route('admin.quizzes.index') }}" class="{{ request()->routeIs('admin.quizzes.*') || request()->routeIs('admin.questions.*') ? 'active' : '' }}" title="Quiz">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.6 9.7a2.4 2.4 0 014.7.7c0 1.6-2.3 1.7-2.3 3.4"/><path stroke-linecap="round" d="M12 16.7h.01"/></svg>
                <span>Quiz</span>
            </a>
            <a href="{{ route('admin.question-bank.index') }}" class="{{ request()->routeIs('admin.question-bank.*') ? 'active' : '' }}" title="Bộ câu hỏi">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.5c-1.5-1-4-1.5-6-1.5v13c2 0 4.5.5 6 1.5m0-13c1.5-1 4-1.5 6-1.5v13c-2 0-4.5.5-6 1.5m0-13v13"/></svg>
                <span>Bộ câu hỏi</span>
            </a>
            <details class="admin-nav-group" @if(request()->routeIs('admin.image-cropper.*', 'admin.image-trimmer')) open @endif>
                <summary class="{{ request()->routeIs('admin.image-cropper.*', 'admin.image-trimmer') ? 'has-active-child' : '' }}" title="Thao tác hình ảnh">
                    <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.5" cy="9.5" r="1.4"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 16l-5-5-4 4-3-3-6 6"/></svg>
                    <span class="admin-nav-group__label">Thao tác hình ảnh</span>
                    <span class="admin-nav-group__chevron" aria-hidden="true"></span>
                </summary>
                <div class="admin-nav-group__children">
                    <a href="{{ route('admin.image-cropper.index') }}" class="{{ request()->routeIs('admin.image-cropper.*') ? 'active' : '' }}">Cắt ảnh</a>
                    <a href="{{ route('admin.image-trimmer') }}" class="{{ request()->routeIs('admin.image-trimmer') ? 'active' : '' }}">Xóa khoảng trắng</a>
                </div>
            </details>
            <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}" title="Học sinh">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4L3 8.5 12 13l9-4.5L12 4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.5 10.7v4.3c0 1.5 2.5 2.8 5.5 2.8s5.5-1.3 5.5-2.8v-4.3"/></svg>
                <span>Học sinh</span>
            </a>
            <a href="{{ route('admin.sessions.index') }}" class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}" title="Phòng chơi">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 9.3l4.5 2.7-4.5 2.7V9.3z"/></svg>
                <span>Phòng chơi</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" title="Báo cáo">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19V10M12 19V5M19.5 19v-7"/></svg>
                <span>Báo cáo</span>
            </a>
            <a href="{{ route('admin.feedback.index') }}" class="{{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}" title="Góp ý">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12.5a7.5 7.5 0 11-3.2-6.15L20 5.5l-1 3.9c.65 1 1 2.06 1 3.1z"/></svg>
                <span>Góp ý</span>
            </a>
            <a href="{{ route('admin.appearance') }}" class="{{ request()->routeIs('admin.appearance') ? 'active' : '' }}" title="Giao diện">
                <svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20a8 8 0 110-16c4.2 0 7.5 2.8 7.5 6.2 0 1.9-1.4 3.3-3.3 3.3h-1.4a1.4 1.4 0 00-1.1 2.3c.3.4.4.8.2 1.3-.3.7-1 1.2-1.9 1.2z"/><circle cx="7.8" cy="10.8" r="1"/><circle cx="10.3" cy="7.5" r="1"/><circle cx="14.5" cy="7.8" r="1"/></svg>
                <span>Giao diện</span>
            </a>
        </nav>

        <div class="admin-user">
            @if (auth()->user()->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="admin-user-avatar admin-user-avatar--img">
            @else
                <span class="admin-user-avatar" aria-hidden="true">{{ auth()->user()->initials }}</span>
            @endif
            <span class="admin-user-name">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="admin-user-logout">
                @csrf
                <button type="submit" class="admin-user-logout-btn" aria-label="Đăng xuất" title="Đăng xuất">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <main class="admin-content @yield('content-class')">
            @unless(request()->routeIs('admin.keyboards.editor', 'admin.questions.create', 'admin.questions.edit', 'admin.question-bank.create', 'admin.question-bank.edit'))
            <div class="admin-page-header">
                <h2 class="admin-page-title">@yield('page-title', 'Admin')</h2>
            </div>
            @endunless
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
<script src="@vasset('js/admin-theme.js')"></script>
<script src="@vasset('js/admin-confirm.js')"></script>
<script src="@vasset('js/admin-loading.js')"></script>
<script src="@vasset('js/admin-list-page.js')"></script>
<script src="@vasset('js/admin-data-table.js')"></script>
<script src="@vasset('js/admin-toast.js')"></script>
<script src="@vasset('js/admin-sidebar.js')"></script>
<script>window.TAG_PRESET_COLORS = @json(\App\Models\Tag::PRESET_COLORS);</script>
<script src="@vasset('js/admin-tags.js')"></script>
<script src="@vasset('js/group-select.js')"></script>
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
<script src="@vasset('js/feedback-widget.js')"></script>
</body>
</html>
