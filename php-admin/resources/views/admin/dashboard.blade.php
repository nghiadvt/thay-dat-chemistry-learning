@extends('layouts.admin')

@section('title', 'Tổng quan — Hóa Thầy Đạt')
@section('page-title', 'Tổng quan')

@php
    $statusLabels = \App\Support\StatusLabels::SESSION;
    $hasDailyData = collect($chartSessionsDaily['values'] ?? [])->sum() > 0;
    $hasStatusData = collect($chartStatus['values'] ?? [])->sum() > 0;
    $hasTopGames = $topGames->isNotEmpty();
@endphp

@section('content')
<div class="dashboard-hero">
    <div class="dashboard-hero__text">
        <h2>Xin chào, {{ $user->name }}</h2>
        <p>{{ $isAdmin ? 'Tổng quan toàn hệ thống' : 'Tổng quan phòng bạn đang host' }} · cập nhật {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="dashboard-hero__actions">
        <a href="{{ route('admin.dashboard.export') }}" class="btn btn-secondary">Xuất CSV tổng quan</a>
        <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">+ Tạo phòng</a>
    </div>
</div>

<div class="dashboard-kpi-grid">
    <a href="{{ route('admin.sessions.index') }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value dashboard-kpi__value--primary">{{ $stats['sessions_total'] }}</div>
        <div class="dashboard-kpi__label">Phòng chơi</div>
        <div class="dashboard-kpi__hint">{{ $stats['sessions_active'] }} đang bật</div>
    </a>
    <a href="{{ route('admin.sessions.index', ['status' => 'playing']) }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value dashboard-kpi__value--warning">{{ $stats['sessions_playing'] }}</div>
        <div class="dashboard-kpi__label">Đang chơi</div>
        <div class="dashboard-kpi__hint">{{ $stats['sessions_waiting'] }} đang chờ</div>
    </a>
    <a href="{{ route('admin.reports.index') }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value dashboard-kpi__value--success">{{ $stats['sessions_ended'] }}</div>
        <div class="dashboard-kpi__label">Đã kết thúc</div>
        <div class="dashboard-kpi__hint">{{ $stats['players_total'] }} lượt chơi HS</div>
    </a>
    <a href="{{ route('admin.quizzes.index') }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value">{{ $stats['quizzes'] }}</div>
        <div class="dashboard-kpi__label">Quiz</div>
        <div class="dashboard-kpi__hint">{{ $stats['questions'] }} câu hỏi</div>
    </a>
    <a href="{{ route('admin.question-bank.index') }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value">{{ $stats['question_bank'] }}</div>
        <div class="dashboard-kpi__label">Bộ câu hỏi</div>
        <div class="dashboard-kpi__hint">{{ $stats['games'] }} game · {{ $stats['keyboards'] }} bàn phím</div>
    </a>
    <a href="{{ route('admin.feedback.index') }}" class="dashboard-kpi dashboard-kpi--link">
        <div class="dashboard-kpi__value {{ $stats['feedback_new'] ? 'dashboard-kpi__value--danger' : '' }}">{{ $stats['feedback_new'] }}</div>
        <div class="dashboard-kpi__label">Góp ý mới</div>
        <div class="dashboard-kpi__hint">Cần xử lý</div>
    </a>
</div>

<div class="dashboard-charts">
    <div class="dashboard-chart-card">
        <h3>Phòng tạo mới</h3>
        <p class="dashboard-chart-card__sub">14 ngày gần nhất</p>
        <div class="dashboard-chart-wrap">
            @if ($hasDailyData)
                <canvas id="chartSessionsDaily" aria-label="Biểu đồ phòng tạo mới 14 ngày"></canvas>
            @else
                <div class="dashboard-empty-chart">Chưa có phòng nào trong 14 ngày qua</div>
            @endif
        </div>
    </div>
    <div class="dashboard-chart-card">
        <h3>Trạng thái phòng</h3>
        <p class="dashboard-chart-card__sub">Chờ · đang chơi · kết thúc</p>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--sm">
            @if ($hasStatusData)
                <canvas id="chartSessionStatus" aria-label="Biểu đồ trạng thái phòng"></canvas>
            @else
                <div class="dashboard-empty-chart">Chưa có dữ liệu phòng</div>
            @endif
        </div>
    </div>
</div>

@if ($hasTopGames)
<div class="dashboard-charts dashboard-charts--single">
    <div class="dashboard-chart-card">
        <h3>Game được chơi nhiều nhất</h3>
        <p class="dashboard-chart-card__sub">Theo số phiên đã kết thúc</p>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--sm">
            <canvas id="chartTopGames" aria-label="Biểu đồ top game"></canvas>
        </div>
    </div>
</div>
@endif

<div class="dashboard-section">
    <div class="dashboard-section__header">
        <h3>Phòng gần đây</h3>
        <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary btn-sm">Xem tất cả</a>
    </div>
    @if ($recentSessions->isEmpty())
        <div class="card admin-list-card">
            <div class="empty-state">Chưa có phòng nào. <a href="{{ route('admin.sessions.create') }}">Tạo phòng mới</a></div>
        </div>
    @else
        <div class="card admin-list-card">
            <div class="table-wrap admin-list-table-wrap">
                <table class="data-table admin-list-table">
                    <thead>
                        <tr>
                            <th>Tên phòng</th>
                            <th>PIN</th>
                            <th>Quiz / Game</th>
                            @if ($isAdmin)<th>Giáo viên</th>@endif
                            <th>Trạng thái</th>
                            <th>Tạo lúc</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentSessions as $session)
                        @php
                            $actions = [];
                            if ($session->status === 'ended') {
                                $actions[] = ['key' => 'detail', 'label' => 'Báo cáo', 'href' => route('admin.reports.show', $session)];
                                if ($session->is_active) {
                                    $actions[] = [
                                        'key' => 'navigate',
                                        'label' => 'Chơi lại',
                                        'href' => route('admin.sessions.reset', $session),
                                        'method' => 'POST',
                                        'confirm' => 'Chơi lại với cùng PIN '.$session->pin.'?',
                                    ];
                                }
                            } else {
                                $actions[] = ['key' => 'detail', 'label' => 'Vào phòng', 'href' => route('admin.sessions.show', $session)];
                            }
                            $actions[] = ['key' => 'edit', 'label' => 'Sửa', 'href' => route('admin.sessions.edit', $session)];
                        @endphp
                        <tr>
                            <td><strong>{{ $session->name ?? 'Phòng '.$session->pin }}</strong></td>
                            <td><span class="session-pin-badge">{{ $session->pin }}</span></td>
                            <td>{{ $session->quiz?->name ?? $session->game?->name ?? '—' }}</td>
                            @if ($isAdmin)<td>{{ $session->host?->name ?? '—' }}</td>@endif
                            <td><span class="badge badge-{{ $session->status }}">{{ $statusLabels[$session->status] ?? $session->status }}</span></td>
                            <td class="session-created-cell">{{ $session->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="actions-cell">
                                @include('admin.partials.row-action-menu', [
                                    'actions' => $actions,
                                    'dataAttrs' => ['item-label' => $session->name ?? $session->pin],
                                ])
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@if ($recentReports->isNotEmpty())
<div class="dashboard-section">
    <div class="dashboard-section__header">
        <h3>Báo cáo gần đây</h3>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary btn-sm">Tất cả báo cáo</a>
    </div>
    <div class="card admin-list-card">
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table">
                <thead>
                    <tr>
                        <th>PIN</th>
                        <th>Tên phòng</th>
                        <th>Game</th>
                        <th>Kết thúc</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentReports as $session)
                    <tr>
                        <td><span class="session-pin-badge">{{ $session->pin }}</span></td>
                        <td>{{ $session->name ?? '—' }}</td>
                        <td>{{ $session->game?->name ?? '—' }}</td>
                        <td class="session-created-cell">{{ $session->ended_at?->format('d/m/Y H:i') }}</td>
                        <td class="actions-cell">
                            @include('admin.partials.row-action-menu', [
                                'actions' => [
                                    ['key' => 'detail', 'label' => 'Chi tiết', 'href' => route('admin.reports.show', $session)],
                                    ['key' => 'navigate', 'label' => 'Tải CSV', 'href' => route('admin.reports.export', $session)],
                                ],
                                'dataAttrs' => [],
                            ])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="dashboard-section">
    <div class="dashboard-section__header">
        <h3>Thao tác nhanh</h3>
    </div>
    <div class="dashboard-quick-grid">
        <a href="{{ route('admin.keyboards.create') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">⌨</span>
            <span class="dashboard-quick-link__text">
                <strong>Tạo bàn phím</strong>
                <span>Layout hóa học cho quiz</span>
            </span>
        </a>
        <a href="{{ route('admin.games.create') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">🎮</span>
            <span class="dashboard-quick-link__text">
                <strong>Tạo game</strong>
                <span>Nhóm quiz theo chủ đề</span>
            </span>
        </a>
        <a href="{{ route('admin.quizzes.create') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">📝</span>
            <span class="dashboard-quick-link__text">
                <strong>Tạo quiz</strong>
                <span>Thêm câu hỏi và bàn phím</span>
            </span>
        </a>
        <a href="{{ route('admin.sessions.create') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">🚪</span>
            <span class="dashboard-quick-link__text">
                <strong>Tạo phòng</strong>
                <span>Nhận PIN + QR cho HS</span>
            </span>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">📊</span>
            <span class="dashboard-quick-link__text">
                <strong>Xem báo cáo</strong>
                <span>Kết quả &amp; tải CSV từng phiên</span>
            </span>
        </a>
        <a href="{{ route('admin.dashboard.export') }}" class="dashboard-quick-link">
            <span class="dashboard-quick-link__icon">⬇</span>
            <span class="dashboard-quick-link__text">
                <strong>Xuất CSV tổng quan</strong>
                <span>Chỉ số + phiên kết thúc gần đây</span>
            </span>
        </a>
    </div>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-dashboard.css')">
@endpush

@push('scripts')
@php
    $dashboardPayload = [
        'sessionsDaily' => $chartSessionsDaily,
        'status' => $chartStatus,
        'topGames' => $topGames,
    ];
@endphp
<script>
window.__ADMIN_DASHBOARD__ = @json($dashboardPayload);
</script>
<script src="@vasset('vendor/chart-4.4.7.umd.min.js')"></script>
<script src="@vasset('js/admin-dashboard.js')"></script>
@endpush
