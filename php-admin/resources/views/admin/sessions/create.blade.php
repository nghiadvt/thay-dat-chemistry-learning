@extends('layouts.admin')

@section('title', 'Tạo phòng — Hóa Thầy Đạt')
@section('page-title', 'Tạo phòng chơi')

@section('content')
<div class="page-header">
    <h2>Tạo phòng mới</h2>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.sessions.store') }}">
        @csrf
        <div class="form-group">
            <label for="game_id">Chọn game *</label>
            <select id="game_id" name="game_id" required>
                <option value="">— Chọn game —</option>
                @foreach ($games as $game)
                    <option value="{{ $game->id }}" @selected(old('game_id') == $game->id)>
                        {{ $game->name }}
                        ({{ $game->active_quizzes_count }} quiz active)
                    </option>
                @endforeach
            </select>
            <p class="hint">Game cần có ít nhất 1 quiz đang kích hoạt.</p>
            @error('game_id')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Tạo phòng</button>
    </form>
    <p class="hint" style="margin-top:12px;">Sau khi tạo, mở phòng để lấy link cho học sinh tham gia.</p>
</div>

@if ($recentSessions->isNotEmpty())
<div class="card">
    <h3>Phòng gần đây</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>PIN</th>
                    <th>Game</th>
                    <th>Host</th>
                    <th>Trạng thái</th>
                    <th>Tạo lúc</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentSessions as $session)
                <tr>
                    <td><strong>{{ $session->pin }}</strong></td>
                    <td>{{ $session->game?->name }}</td>
                    <td>{{ $session->host?->name }}</td>
                    <td><span class="badge badge-{{ $session->status }}">{{ $session->status }}</span></td>
                    <td>{{ $session->created_at?->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-primary btn-sm">Vào phòng</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
