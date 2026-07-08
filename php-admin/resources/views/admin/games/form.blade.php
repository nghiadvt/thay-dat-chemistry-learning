@extends('layouts.admin')

@section('title', ($game ? 'Sửa' : 'Tạo').' game — Hóa Thầy Đạt')
@section('page-title', $game ? 'Sửa game' : 'Tạo game')

@section('content')
@php
    $modeConfig = old('mode_config', $game?->mode_config ?? []);
    $scoring = $modeConfig['scoring'] ?? [];
    $win = $modeConfig['win'] ?? [];
    $selectedModeId = old('play_mode_id', $game?->play_mode_id ?? $playModes->firstWhere('slug', 'kahoot_sync')?->id);
    $duckModeId = $playModes->firstWhere('slug', 'duck_race')?->id;
@endphp

<div class="page-header">
    <h2>{{ $game ? 'Sửa: '.$game->name : 'Tạo game mới' }}</h2>
    <a href="{{ route('admin.games.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ $game ? route('admin.games.update', $game) : route('admin.games.store') }}" id="gameForm">
        @csrf
        @if ($game) @method('PUT') @endif

        <div class="form-group">
            <label for="name">Tên game *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $game?->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="description">Mô tả</label>
            <textarea id="description" name="description" rows="3">{{ old('description', $game?->description) }}</textarea>
            @error('description')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="play_mode_id">Kiểu chơi *</label>
            <select id="play_mode_id" name="play_mode_id" required>
                @foreach ($playModes as $mode)
                    <option value="{{ $mode->id }}" data-slug="{{ $mode->slug }}" @selected((int)$selectedModeId === (int)$mode->id)>
                        {{ $mode->name }}
                    </option>
                @endforeach
            </select>
            @error('play_mode_id')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div id="duckRaceConfig" class="card" style="margin-top:16px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;@if((int)$selectedModeId !== (int)$duckModeId) display:none @endif">
            <h3 style="margin:0 0 12px;font-size:16px">Cấu hình Đua vịt</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
                <div class="form-group" style="margin:0">
                    <label for="correct_delta">Đúng (+bước)</label>
                    <input type="number" id="correct_delta" name="correct_delta" value="{{ old('correct_delta', $scoring['correct_delta'] ?? 3) }}">
                </div>
                <div class="form-group" style="margin:0">
                    <label for="wrong_delta">Sai (bước)</label>
                    <input type="number" id="wrong_delta" name="wrong_delta" value="{{ old('wrong_delta', $scoring['wrong_delta'] ?? -5) }}">
                </div>
                <div class="form-group" style="margin:0">
                    <label for="target_score">Về đích (điểm)</label>
                    <input type="number" id="target_score" name="target_score" value="{{ old('target_score', $win['target_score'] ?? 30) }}">
                </div>
                <div class="form-group" style="margin:0">
                    <label for="podium_size">Top về đích</label>
                    <input type="number" id="podium_size" name="podium_size" value="{{ old('podium_size', $win['podium_size'] ?? 3) }}">
                </div>
            </div>
            <p style="margin:12px 0 0;font-size:13px;color:#64748b">Học sinh trả lời ngay, không đếm giờ. Ai chạm mốc điểm trước được xếp top về đích.</p>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:16px">{{ $game ? 'Cập nhật' : 'Tạo game' }}</button>
    </form>
</div>

@push('scripts')
<script>
(function () {
  const select = document.getElementById('play_mode_id');
  const panel = document.getElementById('duckRaceConfig');
  const duckModeId = @json($duckModeId);
  function sync() {
    if (!select || !panel) return;
    panel.style.display = Number(select.value) === Number(duckModeId) ? '' : 'none';
  }
  select?.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
@endsection
