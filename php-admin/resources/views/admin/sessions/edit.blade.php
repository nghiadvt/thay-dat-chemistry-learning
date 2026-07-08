@extends('layouts.admin')

@section('title', 'Sửa phòng — Hóa Thầy Đạt')
@section('page-title', 'Sửa phòng chơi')

@section('content')
<div class="page-header">
    <h2>Sửa: {{ $session->name ?? 'Phòng '.$session->pin }}</h2>
    <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary">← Danh sách phòng</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.sessions.update', $session) }}" id="sessionEditForm">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>PIN</label>
            <p class="session-pin-cell"><strong>{{ $session->pin }}</strong></p>
            <p class="hint">PIN và QR không đổi khi sửa phòng.</p>
        </div>

        <div class="form-group">
            <label for="name">Tên phòng *</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $session->name) }}"
                required
                maxlength="255"
                placeholder="VD: Lớp 10A — Kiểm tra chương 1"
                autofocus
            >
            <p class="hint">Tên hiển thị trong danh sách phòng để giáo viên dễ nhận biết.</p>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="filter_game_id">Lọc theo game</label>
                <select id="filter_game_id" {{ $canChangeQuiz ? '' : 'disabled' }}>
                    <option value="">Tất cả game</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected((int) old('filter_game_id', $session->game_id) === (int) $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                <p class="hint">Chọn game để thu hẹp danh sách quiz bên dưới.</p>
            </div>
            <div class="form-group">
                <label for="quiz_id">Quiz *</label>
                @if ($canChangeQuiz)
                    <select id="quiz_id" name="quiz_id" required>
                        <option value="">— Chọn quiz —</option>
                        @foreach ($quizzes as $quiz)
                            <option
                                value="{{ $quiz->id }}"
                                data-game-id="{{ $quiz->game_id }}"
                                @selected((int) old('quiz_id', $session->quiz_id) === (int) $quiz->id)
                            >
                                {{ $quiz->name }}
                                ({{ $quiz->game?->name }} — {{ $quiz->active_questions_count }} câu)
                            </option>
                        @endforeach
                    </select>
                    <p class="hint">Đổi quiz khi phòng còn <strong>waiting</strong>. Redis room được cập nhật theo quiz mới.</p>
                @else
                    <input type="text" value="{{ $session->quiz?->name ?? '—' }} ({{ $session->game?->name }})" disabled>
                    <p class="hint">Không đổi quiz khi phòng đang <strong>{{ $session->status }}</strong> (tránh lệch state realtime). Có thể đổi tên.</p>
                @endif
                @error('quiz_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <p class="hint">Trạng thái: <span class="badge badge-{{ $session->status }}">{{ $session->status }}</span>
            · Bật: {{ $session->is_active ? 'có' : 'không' }}</p>

        <button type="submit" class="btn btn-primary">Cập nhật phòng</button>
    </form>
</div>
@endsection

@if ($canChangeQuiz)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterGame = document.getElementById('filter_game_id');
    const quizSelect = document.getElementById('quiz_id');
    if (!filterGame || !quizSelect) return;

    const allOptions = Array.from(quizSelect.querySelectorAll('option[data-game-id]'));

    function applyFilter() {
        const gameId = filterGame.value;
        const selected = quizSelect.value;

        quizSelect.querySelectorAll('option[data-game-id]').forEach(function (opt) {
            opt.hidden = gameId && opt.dataset.gameId !== gameId;
        });

        const visible = allOptions.filter(function (opt) {
            return !gameId || opt.dataset.gameId === gameId;
        });

        if (selected && !visible.some(function (opt) { return opt.value === selected; })) {
            quizSelect.value = '';
        }
    }

    filterGame.addEventListener('change', applyFilter);
    applyFilter();
});
</script>
@endpush
@endif
