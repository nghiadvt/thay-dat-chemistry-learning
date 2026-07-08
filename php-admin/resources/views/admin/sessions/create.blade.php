@extends('layouts.admin')

@section('title', 'Tạo phòng — Hóa Thầy Đạt')
@section('page-title', 'Tạo phòng chơi')

@section('content')
<div class="page-header">
    <h2>Tạo phòng mới</h2>
    <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary">← Danh sách phòng</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.sessions.store') }}" id="sessionCreateForm">
        @csrf
        <div class="form-group">
            <label for="name">Tên phòng *</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
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
                <select id="filter_game_id">
                    <option value="">Tất cả game</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}">{{ $game->name }}</option>
                    @endforeach
                </select>
                <p class="hint">Chọn game để thu hẹp danh sách quiz bên dưới.</p>
            </div>
            <div class="form-group">
                <label for="quiz_id">Chọn quiz *</label>
                <select id="quiz_id" name="quiz_id" required>
                    <option value="">— Chọn quiz —</option>
                    @foreach ($quizzes as $quiz)
                        <option
                            value="{{ $quiz->id }}"
                            data-game-id="{{ $quiz->game_id }}"
                            @selected(old('quiz_id') == $quiz->id)
                        >
                            {{ $quiz->name }}
                            ({{ $quiz->game?->name }} — {{ $quiz->active_questions_count }} câu)
                        </option>
                    @endforeach
                </select>
                <p class="hint">Quiz cần có ít nhất 1 câu hỏi đang kích hoạt. PIN và QR được tạo ngay khi bấm tạo phòng.</p>
                @error('quiz_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Tạo phòng</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterGame = document.getElementById('filter_game_id');
    const quizSelect = document.getElementById('quiz_id');
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
