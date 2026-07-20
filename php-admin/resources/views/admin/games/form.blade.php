@extends('layouts.admin')

@section('title', ($game ? 'Sửa' : 'Tạo').' game — Hóa Thầy Đạt')
@section('page-title', $game ? 'Sửa game' : 'Tạo game')

@push('head')
<link rel="stylesheet" href="@vasset('css/duck-race-game-config.css')">
<link rel="stylesheet" href="@vasset('css/admin-duck-sprites.css')">
@endpush

@section('content')
@php
    use App\Support\DuckRaceAssets;
    $modeConfig = old('mode_config', $game?->mode_config ?? []);
    $scoring = $modeConfig['scoring'] ?? [];
    $win = $modeConfig['win'] ?? [];
    $visual = $modeConfig['visual'] ?? [];
    $trackBounds = $visual['track_bounds'] ?? [];
    $laneBounds = $visual['lane_bounds'] ?? [];
    $duckDefaults = $playModes->firstWhere('slug', 'duck_race')?->default_config ?? [];
    $defaultBounds = $duckDefaults['visual']['track_bounds'] ?? ['start_pct' => 20, 'end_pct' => 90];
    $defaultLaneBounds = $duckDefaults['visual']['lane_bounds'] ?? ['top_pct' => 50, 'bottom_pct' => 92];
    $duckSpritePx = old('duck_sprite_px', $visual['duck_sprite_px'] ?? $duckDefaults['visual']['duck_sprite_px'] ?? 64);
    $duckSwimMs = old('duck_swim_ms', $visual['duck_swim_ms'] ?? $duckDefaults['visual']['duck_swim_ms'] ?? 1150);
    $selectedModeId = old('play_mode_id', $game?->play_mode_id ?? $playModes->firstWhere('slug', 'kahoot_sync')?->id);
    $duckModeId = $playModes->firstWhere('slug', 'duck_race')?->id;
    $trackBgUrl = asset('htd-admin/assets/duck-race/background.png');
    $duckGifUrl = DuckRaceAssets::defaultSpriteUrl();
    $duckSpriteCount = count(DuckRaceAssets::listSpritePaths());
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

        <div id="duckRaceConfig" class="card drc-config-panel @if((int)$selectedModeId !== (int)$duckModeId) drc-hidden @endif" data-duck-mode-id="{{ $duckModeId }}" data-duck-src="{{ $duckGifUrl }}">
            <h3 class="drc-config-panel__title">Cấu hình Đua vịt</h3>
            <div class="drc-scoring-grid">
                <div class="form-group form-group--flush">
                    <label for="correct_delta">Đúng (+bước)</label>
                    <input type="number" id="correct_delta" name="correct_delta" value="{{ old('correct_delta', $scoring['correct_delta'] ?? 3) }}">
                </div>
                <div class="form-group form-group--flush">
                    <label for="wrong_delta">Sai (bước)</label>
                    <input type="number" id="wrong_delta" name="wrong_delta" value="{{ old('wrong_delta', $scoring['wrong_delta'] ?? -5) }}">
                </div>
                <div class="form-group form-group--flush">
                    <label for="target_score">Về đích (điểm)</label>
                    <input type="number" id="target_score" name="target_score" value="{{ old('target_score', $win['target_score'] ?? 30) }}">
                </div>
                <div class="form-group form-group--flush">
                    <label for="podium_size">Top về đích</label>
                    <input type="number" id="podium_size" name="podium_size" value="{{ old('podium_size', $win['podium_size'] ?? 3) }}">
                </div>
            </div>
            <p class="drc-note">Học sinh trả lời ngay, không đếm giờ. Ai chạm mốc điểm trước được xếp top về đích.</p>

            <div class="drc-section drc-frame-editor">
                <h4>Vùng đường đua (khung vịt)</h4>
                <div class="drc-bounds-inputs drc-bounds-inputs--frame">
                    <div class="form-group">
                        <label for="track_start_pct">Xuất phát (%)</label>
                        <input type="number" id="track_start_pct" name="track_start_pct" min="0" max="100" step="0.1"
                               value="{{ old('track_start_pct', $trackBounds['start_pct'] ?? $defaultBounds['start_pct'] ?? 20) }}">
                    </div>
                    <div class="form-group">
                        <label for="track_end_pct">Vạch đích (%)</label>
                        <input type="number" id="track_end_pct" name="track_end_pct" min="0" max="100" step="0.1"
                               value="{{ old('track_end_pct', $trackBounds['end_pct'] ?? $defaultBounds['end_pct'] ?? 90) }}">
                    </div>
                    <div class="form-group">
                        <label for="lane_top_pct">Mép trên (%)</label>
                        <input type="number" id="lane_top_pct" name="lane_top_pct" min="0" max="100" step="0.1"
                               value="{{ old('lane_top_pct', $laneBounds['top_pct'] ?? $defaultLaneBounds['top_pct'] ?? 50) }}">
                    </div>
                    <div class="form-group">
                        <label for="lane_bottom_pct">Mép dưới (%)</label>
                        <input type="number" id="lane_bottom_pct" name="lane_bottom_pct" min="0" max="100" step="0.1"
                               value="{{ old('lane_bottom_pct', $laneBounds['bottom_pct'] ?? $defaultLaneBounds['bottom_pct'] ?? 92) }}">
                    </div>
                </div>
                <div class="drc-track-surface" id="drcFrameSurface">
                    <img class="drc-track-surface__bg" src="{{ $trackBgUrl }}" alt="Đường đua">
                    <div class="drc-race-frame" aria-hidden="true"></div>
                    <div class="drc-frame-edge drc-frame-edge--start" data-edge="start" title="Kéo — vạch xuất phát">
                        <span class="drc-frame-edge__label">Xuất phát</span>
                    </div>
                    <div class="drc-frame-edge drc-frame-edge--end" data-edge="end" title="Kéo — vạch đích">
                        <span class="drc-frame-edge__label">Đích</span>
                    </div>
                    <div class="drc-frame-edge drc-frame-edge--top" data-edge="lane-top" title="Kéo — mép trên">
                        <span class="drc-frame-edge__label">Trên</span>
                    </div>
                    <div class="drc-frame-edge drc-frame-edge--bottom" data-edge="lane-bottom" title="Kéo — mép dưới">
                        <span class="drc-frame-edge__label">Dưới</span>
                    </div>
                </div>
                <p class="drc-hint">Kéo 4 cạnh khung (xanh = xuất phát, đỏ = đích, tím = trên, cam = dưới). Vịt chỉ hiển thị trong khung này. Sprite: <code>htd-admin/assets/duck-race/ducks/</code> ({{ $duckSpriteCount }} ảnh).</p>
            </div>

            <div class="drc-section drc-duck-size">
                <h4>Kích thước &amp; tốc độ bơi vịt</h4>
                <div class="drc-duck-size__layout">
                    <div class="drc-duck-size__canvas-wrap">
                        <canvas class="drc-duck-size__canvas" width="320" height="220" aria-label="Canvas chỉnh kích thước vịt"></canvas>
                        <p class="drc-hint drc-duck-size__canvas-hint">Kéo <strong>ô tròn xanh</strong> góc khung để resize</p>
                    </div>
                    <div class="drc-duck-size__controls">
                        <div class="form-group">
                            <label for="duck_sprite_px">Cạnh sprite (px)</label>
                            <input type="number" id="duck_sprite_px" name="duck_sprite_px"
                                   min="32" max="128" step="1"
                                   value="{{ $duckSpritePx }}">
                            <input type="range" id="duck_sprite_px_range" min="32" max="128" step="1"
                                   value="{{ $duckSpritePx }}" aria-labelledby="duck_sprite_px" class="drc-range">
                            <p id="duck_sprite_px_label" class="drc-duck-size__value">{{ $duckSpritePx }} px</p>
                        </div>
                        <div class="form-group">
                            <label for="duck_swim_ms">Tốc độ bơi (ms)</label>
                            <input type="number" id="duck_swim_ms" name="duck_swim_ms"
                                   min="400" max="3000" step="50"
                                   value="{{ $duckSwimMs }}">
                            <input type="range" id="duck_swim_ms_range" min="400" max="3000" step="50"
                                   value="{{ $duckSwimMs }}" aria-labelledby="duck_swim_ms" class="drc-range">
                            <p id="duck_swim_ms_label" class="drc-duck-size__value">{{ number_format($duckSwimMs / 1000, 2) }} giây / bước</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="drc-section drc-preview">
                <h4>Preview — mô phỏng di chuyển vịt</h4>
                <div class="drc-preview__toolbar">
                    <span class="drc-preview__score">Điểm: 0 / {{ old('target_score', $win['target_score'] ?? 30) }}</span>
                    <button type="button" class="btn btn-primary btn-sm" data-preview="correct">Đúng (+bước)</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-preview="wrong">Sai (bước)</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-preview="reset">Đặt lại</button>
                </div>
                <div class="drc-track-surface drc-preview-surface">
                    <img class="drc-track-surface__bg" src="{{ $trackBgUrl }}" alt="">
                    <div class="drc-preview-race-frame">
                        <div class="drc-preview-duck">
                            <img src="{{ $duckGifUrl }}" alt="Vịt">
                            <button type="button" class="drc-preview-duck__resize" title="Kéo để đổi kích thước vịt" aria-label="Resize vịt"></button>
                        </div>
                    </div>
                    <div class="drc-preview-finish" aria-hidden="true">
                        <div class="drc-preview-finish__banner">🏁 Về đích — Vô địch!</div>
                    </div>
                </div>
            </div>

            <div class="drc-section dsm" id="duckSpriteManager" data-api-base="/api/duck-sprites">
                <div class="dsm-head">
                    <div class="dsm-head__text">
                        <h4>🦆 Vịt chuyển động (frame animation)</h4>
                        <p class="dsm-head__hint">Mỗi vịt gồm 8–10 ảnh frame phát lần lượt để tạo chuyển động. Upload frame, kéo thả sắp thứ tự, chỉnh tốc độ và xem preview ngay.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm dsm-head__add" data-dsm="create">＋ Thêm vịt mới</button>
                </div>
                <div class="dsm-toolbar drc-hidden" data-dsm="toolbar">
                    <span class="dsm-toolbar__count" data-dsm="count"></span>
                    <button type="button" class="dsm-toolbar__toggle" data-dsm="toggle-all" aria-pressed="true">⏸ Dừng tất cả</button>
                </div>
                <div class="dsm-grid" data-dsm="grid"></div>
                <div class="dsm-empty drc-hidden" data-dsm="empty">
                    <span class="dsm-empty__icon">🦆</span>
                    <strong>Chưa có vịt chuyển động nào</strong>
                    <span>Bấm "＋ Thêm vịt mới" rồi upload 8–10 ảnh frame để tạo con vịt đầu tiên.</span>
                </div>
                <div class="dsm-loading" data-dsm="loading">Đang tải danh sách vịt…</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary drc-submit-btn">{{ $game ? 'Cập nhật' : 'Tạo game' }}</button>
    </form>
</div>

{{-- Modal editor vịt chuyển động — nằm ngoài <form> để input không submit theo form game --}}
<div class="dsm-modal drc-hidden" id="duckSpriteModal" role="dialog" aria-modal="true" aria-labelledby="dsmModalTitle">
    <div class="dsm-modal__backdrop" data-dsm="close"></div>
    <div class="dsm-modal__panel">
        <div class="dsm-modal__head">
            <h3 id="dsmModalTitle" data-dsm="modal-title">Thêm vịt mới</h3>
            <button type="button" class="dsm-modal__x" data-dsm="close" aria-label="Đóng">✕</button>
        </div>
        <div class="dsm-modal__body">
            <div class="dsm-editor">
                <div class="dsm-editor__left">
                    <div class="dsm-player">
                        <div class="dsm-player__stage">
                            <img data-dsm="stage-img" alt="Preview vịt" class="drc-hidden">
                            <div class="dsm-player__placeholder" data-dsm="stage-empty">
                                <span>🖼️</span>
                                Chưa có frame nào.<br>Upload ảnh để xem preview chuyển động.
                            </div>
                        </div>
                        <div class="dsm-player__controls">
                            <button type="button" class="dsm-play-btn" data-dsm="play" aria-label="Chạy / dừng animation">⏸</button>
                            <input type="range" data-dsm="scrub" min="1" max="1" value="1" step="1" aria-label="Chọn frame">
                            <span class="dsm-player__frame-label" data-dsm="frame-label">–/–</span>
                        </div>
                    </div>
                    <div class="dsm-speed">
                        <label for="dsmFps">⚡ Tốc độ animation</label>
                        <div class="dsm-speed__row">
                            <input type="range" id="dsmFps" min="1" max="30" step="1" value="10">
                            <span class="dsm-speed__value" data-dsm="fps-label">10 hình/giây</span>
                        </div>
                        <p class="dsm-speed__sub" data-dsm="fps-sub">1 giây phát 10 frame · mỗi frame hiện ≈ 100 ms</p>
                    </div>
                </div>
                <div class="dsm-editor__right">
                    <div class="form-group">
                        <label for="dsmName">Tên vịt *</label>
                        <input type="text" id="dsmName" maxlength="255" placeholder="VD: Vịt vàng đội nón" autocomplete="off">
                    </div>
                    <div class="dsm-drop" data-dsm="drop" tabindex="0" role="button" aria-label="Upload ảnh frame">
                        <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden data-dsm="file-input">
                        <strong>📂 Kéo thả 8–10 ảnh frame vào đây</strong>
                        <span>hoặc bấm để chọn nhiều ảnh cùng lúc — tự xếp thứ tự theo tên file (frame-1, frame-2…)</span>
                    </div>
                    <div class="dsm-frames" data-dsm="frames"></div>
                    <p class="dsm-frames-hint">💡 Kéo thả thumbnail để đổi thứ tự, hoặc sửa số trên góc ảnh. Bấm ảnh để xem frame đó. Tối đa 20 frame.</p>
                </div>
            </div>
        </div>
        <div class="dsm-modal__foot">
            <span class="dsm-modal__status" data-dsm="status"></span>
            <button type="button" class="btn btn-secondary" data-dsm="close">Hủy</button>
            <button type="button" class="btn btn-primary" data-dsm="save">💾 Lưu vịt</button>
        </div>
    </div>
</div>

@push('scripts')
<script src="@vasset('js/duck-race-game-config.js')"></script>
<script src="@vasset('js/games-form.js')"></script>
<script src="@vasset('js/duck-sprite-manager.js')"></script>
@endpush
@endsection
