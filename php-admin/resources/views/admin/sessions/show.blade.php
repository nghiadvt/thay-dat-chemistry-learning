@extends('layouts.admin')

@section('title', 'Phòng '.$session->pin.' — Hóa Thầy Đạt')
@section('page-title', 'Phòng chơi')

@section('content')
<div class="card" style="text-align:center;">
    <h3 style="margin-top:0;">PIN phòng</h3>
    <div class="pin-display">{{ $session->pin }}</div>
    <p style="color:#6b7280;">
        Game: <strong>{{ $session->game?->name }}</strong> ·
        Trạng thái: <span class="badge badge-{{ $session->status }}">{{ $session->status }}</span>
    </p>
</div>

<div class="card">
    <h3>Bước tiếp theo</h3>
    <div class="actions" style="margin-bottom:16px;">
        <a href="{{ $hostUrl }}" class="btn btn-primary" target="_blank" rel="noopener">Mở màn host</a>
        <a href="{{ $studentUrl }}" class="btn btn-secondary" target="_blank" rel="noopener">Trang học sinh (demo PIN)</a>
    </div>
    <p class="hint" style="margin:0;">
        Link host: <code>{{ $hostUrl }}</code><br>
        Học sinh nhập PIN <strong>{{ $session->pin }}</strong> tại <code>/app/index.html</code>
    </p>
</div>

<div class="card">
    <h3>Thông tin session</h3>
    <table class="data-table">
        <tr><th>Session ID</th><td>{{ $session->id }}</td></tr>
        <tr><th>Game ID</th><td>{{ $session->game_id }}</td></tr>
        <tr><th>Host</th><td>{{ $session->host?->name }}</td></tr>
        <tr><th>Tạo lúc</th><td>{{ $session->created_at?->format('d/m/Y H:i:s') }}</td></tr>
    </table>
</div>

<div class="actions">
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-secondary">Tạo phòng khác</a>
    @if ($session->status === 'ended')
        <a href="{{ route('admin.reports.show', $session) }}" class="btn btn-primary">Xem báo cáo</a>
    @endif
</div>
@endsection
