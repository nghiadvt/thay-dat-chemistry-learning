@extends('layouts.admin')

@section('title', ($session->name ?? 'Phòng '.$session->pin).' — Hóa Thầy Đạt')
@section('page-title', $session->name ?? 'Phòng chơi')
@section('body-class', 'admin-body--session-host')
@section('content-class', 'admin-content--session-host')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;800&family=STIX+Two+Text&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('htd-admin/css/shared.css') }}">
<link rel="stylesheet" href="{{ asset('htd-admin/css/session-host.css') }}?v={{ file_exists(public_path('htd-admin/css/session-host.css')) ? filemtime(public_path('htd-admin/css/session-host.css')) : time() }}">
@endpush

@section('content')
<div class="admin-host-wrap">
    @include('admin.sessions._host-panel')
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_BOOT = {
    session: {
        pin: @json($session->pin),
        roomName: @json($session->name ?? 'Phòng '.$session->pin),
        quizName: @json($session->quiz?->name),
        gameName: @json($session->game?->name),
        gameId: {{ (int) $session->game_id }},
        quizId: {{ (int) ($session->quiz_id ?? 0) }},
        sessionId: {{ (int) $session->id }},
        hostName: @json($session->host?->name ?? auth()->user()->name),
        joinUrl: @json($joinUrl),
        qrUrl: @json($qrUrl),
        status: @json($session->status),
    },
    apiBase: @json(url('/')),
    wsUrl: @json(config('services.ws.url')),
};
</script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
@php
$htdJs = fn ($path) => asset($path) . '?v=' . (file_exists(public_path($path)) ? filemtime(public_path($path)) : time());
@endphp
<script src="{{ $htdJs('htd-admin/js/admin-boot.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/shared.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/equation-ui.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/api.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/socket.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/game-adapter.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/backend-bridge.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/teacher.js') }}"></script>
<script src="{{ $htdJs('htd-admin/js/admin-session-init.js') }}"></script>
@endpush
