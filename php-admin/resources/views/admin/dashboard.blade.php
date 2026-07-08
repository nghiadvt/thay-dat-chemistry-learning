@extends('layouts.admin')

@section('title', 'Tổng quan — Hóa Thầy Đạt')
@section('page-title', 'Tổng quan')

@section('content')
<div class="stat-grid">
    <div class="stat-card">
        <div class="value">{{ $stats['keyboards'] }}</div>
        <div class="label">Bàn phím</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['games'] }}</div>
        <div class="label">Game</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['quizzes'] }}</div>
        <div class="label">Quiz</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['questions'] }}</div>
        <div class="label">Câu hỏi</div>
    </div>
</div>

<div class="card">
    <h3>Bắt đầu nhanh</h3>
    <ol style="line-height:1.8;margin:0;padding-left:20px;">
        <li>Tạo <a href="{{ route('admin.keyboards.create') }}">bàn phím</a> (hoặc dùng trình chỉnh sửa tại <a href="/app/keyboard-editor.html" target="_blank">/app/keyboard-editor.html</a>)</li>
        <li>Tạo <a href="{{ route('admin.games.create') }}">game</a> và gán <a href="{{ route('admin.quizzes.create') }}">quiz</a> + câu hỏi</li>
        <li><a href="{{ route('admin.sessions.create') }}">Tạo phòng</a> → nhận PIN + QR → <a href="{{ route('admin.sessions.index') }}">danh sách phòng</a></li>
        <li>Sau khi chơi xong, xem <a href="{{ route('admin.reports.index') }}">báo cáo</a> và tải CSV</li>
    </ol>
</div>

@if ($recentSessions->isNotEmpty())
<div class="card">
    <h3>Phòng gần đây</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên phòng</th>
                    <th>PIN</th>
                    <th>Quiz</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentSessions as $session)
                <tr>
                    <td><strong>{{ $session->name ?? 'Phòng '.$session->pin }}</strong></td>
                    <td>{{ $session->pin }}</td>
                    <td>{{ $session->quiz?->name ?? $session->game?->name }}</td>
                    <td>
                        <span class="badge badge-{{ $session->status }}">{{ $session->status }}</span>
                    </td>
                    <td>{{ $session->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions">
                        @if ($session->status === 'ended')
                            @if ($session->is_active)
                                <form
                                    method="POST"
                                    action="{{ route('admin.sessions.reset', $session) }}"
                                    class="inline-form"
                                    onsubmit="return confirm('Chơi lại với cùng PIN {{ $session->pin }}?');"
                                >
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Chơi lại</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.reports.show', $session) }}" class="btn btn-secondary btn-sm">Báo cáo</a>
                        @else
                            <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-secondary btn-sm">Chi tiết</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
