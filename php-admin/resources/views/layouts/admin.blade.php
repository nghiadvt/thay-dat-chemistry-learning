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
            <h1>Hóa Thầy Đạt</h1>
            <p>Quản trị nội dung</p>
        </div>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Tổng quan</a>
            <a href="{{ route('admin.keyboards.index') }}" class="{{ request()->routeIs('admin.keyboards.*') ? 'active' : '' }}">Bàn phím</a>
            <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}">Game</a>
            <a href="{{ route('admin.quizzes.index') }}" class="{{ request()->routeIs('admin.quizzes.*') || request()->routeIs('admin.questions.*') ? 'active' : '' }}">Quiz</a>
            <a href="{{ route('admin.question-bank.index') }}" class="{{ request()->routeIs('admin.question-bank.*') ? 'active' : '' }}">Bộ câu hỏi</a>
            <details class="admin-nav-group" @if(request()->routeIs('admin.image-cropper.*', 'admin.image-trimmer')) open @endif>
                <summary class="{{ request()->routeIs('admin.image-cropper.*', 'admin.image-trimmer') ? 'has-active-child' : '' }}">
                    <span class="admin-nav-group__label">Thao tác hình ảnh</span>
                    <span class="admin-nav-group__chevron" aria-hidden="true"></span>
                </summary>
                <div class="admin-nav-group__children">
                    <a href="{{ route('admin.image-cropper.index') }}" class="{{ request()->routeIs('admin.image-cropper.*') ? 'active' : '' }}">Cắt ảnh</a>
                    <a href="{{ route('admin.image-trimmer') }}" class="{{ request()->routeIs('admin.image-trimmer') ? 'active' : '' }}">Xóa khoảng trắng</a>
                </div>
            </details>
            <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}">Học sinh</a>
            <a href="{{ route('admin.sessions.index') }}" class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}">Phòng chơi</a>
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Báo cáo</a>
            <a href="{{ route('admin.feedback.index') }}" class="{{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}">Góp ý</a>
            <a href="{{ route('admin.appearance') }}" class="{{ request()->routeIs('admin.appearance') ? 'active' : '' }}">Giao diện</a>
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
