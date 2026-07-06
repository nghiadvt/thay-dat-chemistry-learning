@extends('layouts.admin')

@section('title', 'Báo cáo — Hóa Thầy Đạt')
@section('page-title', 'Báo cáo')

@section('content')
<div class="page-header">
    <h2>Lịch sử session đã kết thúc</h2>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div class="form-group">
            <label for="game_id">Game</label>
            <select id="game_id" name="game_id">
                <option value="">Tất cả</option>
                @foreach ($games as $game)
                    <option value="{{ $game->id }}" @selected(request('game_id') == $game->id)>{{ $game->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="date_from">Từ ngày</label>
            <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="form-group">
            <label for="date_to">Đến ngày</label>
            <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
        </div>
        <button type="submit" class="btn btn-secondary">Lọc</button>
    </form>

    @if ($sessions->isEmpty())
        <div class="empty-state">Chưa có session đã kết thúc.</div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>PIN</th>
                    <th>Game</th>
                    <th>Host</th>
                    <th>Kết thúc</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $session)
                <tr>
                    <td><strong>{{ $session->pin }}</strong></td>
                    <td>{{ $session->game?->name }}</td>
                    <td>{{ $session->host?->name }}</td>
                    <td>{{ $session->ended_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.reports.show', $session) }}" class="btn btn-secondary btn-sm">Chi tiết</a>
                        <a href="{{ route('admin.reports.export', $session) }}" class="btn btn-primary btn-sm">CSV</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $sessions->links() }}</div>
    @endif
</div>
@endsection

@push('head')
<style>
    nav[role="navigation"] { display: flex; gap: 8px; flex-wrap: wrap; }
    nav[role="navigation"] a, nav[role="navigation"] span { padding: 6px 10px; border-radius: 6px; font-size: 0.85rem; text-decoration: none; }
    nav[role="navigation"] a { background: #e5e7eb; color: #111827; }
    nav[role="navigation"] span { background: #2D46D6; color: #fff; }
</style>
@endpush
