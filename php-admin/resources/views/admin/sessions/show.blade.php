@extends('layouts.admin')

@section('title', 'Phòng '.$session->pin.' — Hóa Thầy Đạt')
@section('page-title', 'Phòng chơi')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('htd-admin/css/shared.css') }}">
<link rel="stylesheet" href="{{ asset('htd-admin/css/session-host.css') }}">
<style>
.session-top-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 16px 24px;
    margin-bottom: 12px;
}
.session-top-bar .pin-display {
    font-size: 2rem; font-weight: 800; letter-spacing: 0.2em; color: #2D46D6;
}
.admin-host-wrap .teacher-layout { height: calc(100vh - 220px); min-height: 560px; max-height: none; }
.admin-host-wrap .teacher-sidebar { display: none; }
</style>
@endpush

@section('content')
<div class="card session-top-bar">
    <div>
        <div class="hint" style="margin:0 0 4px;">PIN — học sinh nhập tại link bên phải</div>
        <div class="pin-display">{{ $session->pin }}</div>
    </div>
    <div>
        <div class="hint" style="margin:0 0 4px;">Game</div>
        <strong>{{ $session->game?->name }}</strong>
        <span class="badge badge-{{ $session->status }}" style="margin-left:8px;">{{ $session->status }}</span>
    </div>
    <div class="actions" style="margin:0;">
        <a href="{{ $joinUrl }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener">Link học sinh tham gia</a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(@json($joinUrl))">Copy link</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('{{ $session->pin }}')">Copy PIN</button>
        @if ($session->status === 'ended')
            <a href="{{ route('admin.reports.show', $session) }}" class="btn btn-primary btn-sm">Báo cáo</a>
        @endif
    </div>
</div>

<div class="admin-host-wrap">
    @include('admin.sessions._host-panel')
</div>

<div class="actions" style="margin-top:12px;">
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-secondary">Tạo phòng khác</a>
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_BOOT = {
    session: {
        pin: @json($session->pin),
        gameId: {{ (int) $session->game_id }},
        sessionId: {{ (int) $session->id }},
        gameName: @json($session->game?->name),
    },
    apiBase: @json(url('/')),
    wsUrl: @json(config('services.ws.url')),
};
</script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('htd-admin/js/admin-boot.js') }}"></script>
<script src="{{ asset('htd-admin/js/shared.js') }}"></script>
<script src="{{ asset('htd-admin/js/api.js') }}"></script>
<script src="{{ asset('htd-admin/js/socket.js') }}"></script>
<script src="{{ asset('htd-admin/js/game-adapter.js') }}"></script>
<script src="{{ asset('htd-admin/js/backend-bridge.js') }}"></script>
<script src="{{ asset('htd-admin/js/teacher.js') }}"></script>
<script src="{{ asset('htd-admin/js/admin-session-init.js') }}"></script>
@endpush
