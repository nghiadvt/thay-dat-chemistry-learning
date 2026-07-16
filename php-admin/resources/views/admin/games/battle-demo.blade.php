@extends('layouts.admin')

@section('title', 'Đấu Trường Hóa Học — Demo game mới')
@section('page-title', 'Game mới: Đấu Trường Hóa Học')

@push('head')
<link rel="stylesheet" href="@vasset('css/battle-arena-demo.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>⚔️ Đấu Trường Hóa Học</h2>
        <span class="bat-page-note">🧪 Bản demo — dữ liệu giả lập bằng JS, chưa nối database</span>
    </div>
    <a href="{{ route('admin.games.index') }}" class="btn btn-secondary">← Quay lại danh sách game</a>
</div>

<div class="card bat-intro">
    <h3>Luật chơi</h3>
    <p>Học sinh chia làm 2 đội phù thủy hóa học. Mỗi học sinh nhập vai một nhân vật (Hỏa – Băng – Giả Kim – Lôi).
       Trả lời câu hỏi đúng → nhân vật phóng phép tấn công đội bạn. Trả lời sai → phép nổ ngược, đội mình mất máu.
       Trả lời đúng liên tiếp tạo <strong>COMBO</strong>, sát thương tăng dần. Đội nào cạn máu trước sẽ thua.</p>
    <div class="bat-intro__rules">
        <span class="bat-rule-chip">✅ Đúng → <strong>tấn công đội bạn</strong></span>
        <span class="bat-rule-chip">❌ Sai → <strong>tự mất máu</strong></span>
        <span class="bat-rule-chip">🔥 Combo → <strong>sát thương cộng dồn</strong></span>
        <span class="bat-rule-chip">🏆 Đội hết máu trước → <strong>thua</strong></span>
    </div>
</div>

<div class="card" id="battleArenaDemo">
    <h3>Đấu trường (mô phỏng)</h3>

    <div class="bat-scoreboard">
        <div class="bat-hpbar bat-hpbar--red" data-hpbar="red">
            <div class="bat-hpbar__head">
                <span class="bat-hpbar__name">🐉 Đội Rồng Lửa</span>
                <span class="bat-hpbar__value">100 / 100</span>
            </div>
            <div class="bat-hpbar__track">
                <div class="bat-hpbar__ghost"></div>
                <div class="bat-hpbar__fill"></div>
            </div>
        </div>
        <div class="bat-vs-badge">VS</div>
        <div class="bat-hpbar bat-hpbar--blue" data-hpbar="blue">
            <div class="bat-hpbar__head">
                <span class="bat-hpbar__name">🦅 Đội Phượng Băng</span>
                <span class="bat-hpbar__value">100 / 100</span>
            </div>
            <div class="bat-hpbar__track">
                <div class="bat-hpbar__ghost"></div>
                <div class="bat-hpbar__fill"></div>
            </div>
        </div>
    </div>

    <div class="bat-stage" id="batStage">
        <div class="bat-stage__sky">
            <div class="bat-moon"></div>
            <div class="bat-cloud bat-cloud--1"></div>
            <div class="bat-cloud bat-cloud--2"></div>
            <div class="bat-cloud bat-cloud--3"></div>
        </div>
        <div class="bat-stage__floor"></div>
        <div class="bat-torch bat-torch--left"></div>
        <div class="bat-torch bat-torch--right"></div>

        <div class="bat-side bat-side--red"><div class="bat-slots" data-slots="red"></div></div>
        <div class="bat-side bat-side--blue"><div class="bat-slots" data-slots="blue"></div></div>

        <div class="bat-fx-layer" id="batFx"></div>
        <div class="bat-combo" id="batCombo"></div>

        <div class="bat-victory" id="batVictory" hidden>
            <div class="bat-victory__confetti"></div>
            <div class="bat-victory__card">
                <span class="bat-victory__trophy">🏆</span>
                <div class="bat-victory__team">Đội ? chiến thắng!</div>
                <p class="bat-victory__sub">Cả đội nhận huy hiệu Nhà Vô Địch Hóa Học</p>
                <button type="button" class="btn btn-primary" data-act="replay">🔄 Chơi lại</button>
            </div>
        </div>
    </div>

    <div class="bat-controls">
        <button type="button" class="btn btn-sm bat-btn-red" data-act="red-correct">Đỏ trả lời đúng ✔</button>
        <button type="button" class="btn btn-sm bat-btn-red" data-act="red-wrong">Đỏ trả lời sai ✘</button>
        <button type="button" class="btn btn-sm bat-btn-blue" data-act="blue-correct">Xanh trả lời đúng ✔</button>
        <button type="button" class="btn btn-sm bat-btn-blue" data-act="blue-wrong">Xanh trả lời sai ✘</button>
        <span class="bat-controls__spacer"></span>
        <label class="bat-speed-label">Tốc độ
            <select id="bat_speed">
                <option value="1">x1</option>
                <option value="1.5">x1.5</option>
                <option value="2">x2</option>
            </select>
        </label>
        <button type="button" class="btn btn-primary btn-sm" data-act="auto">▶ Tự động demo</button>
        <button type="button" class="btn btn-secondary btn-sm" data-act="sound">🔊 Âm thanh: bật</button>
        <button type="button" class="btn btn-secondary btn-sm" data-act="reset">Đặt lại</button>
    </div>
    <p class="bat-config-hint">💡 Mẹo: bấm trực tiếp vào một nhân vật để cho học sinh đó "trả lời đúng" và tấn công.</p>
</div>

<div class="card">
    <h3>Cấu hình gameplay (thử trực tiếp)</h3>
    <div class="bat-config-grid">
        <div class="form-group form-group--flush">
            <label for="bat_team_hp">Máu mỗi đội</label>
            <input type="number" id="bat_team_hp" min="20" max="1000" step="10" value="100">
        </div>
        <div class="form-group form-group--flush">
            <label for="bat_dmg">Sát thương khi đúng</label>
            <input type="number" id="bat_dmg" min="1" max="50" value="8">
        </div>
        <div class="form-group form-group--flush">
            <label for="bat_combo_step">Cộng thêm mỗi combo</label>
            <input type="number" id="bat_combo_step" min="0" max="10" value="2">
        </div>
        <div class="form-group form-group--flush">
            <label for="bat_penalty">Tự mất máu khi sai</label>
            <input type="number" id="bat_penalty" min="0" max="20" value="4">
        </div>
    </div>
    <p class="bat-config-hint">
        Đổi "Máu mỗi đội" sẽ đặt lại trận đấu; các thông số còn lại áp dụng ngay cho lượt kế tiếp.
        Khi xây dựng database, các thông số này sẽ lưu vào <code>mode_config</code> giống game Đua vịt
        (dự kiến play mode slug: <code>battle_arena</code>).
    </p>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/battle-arena-demo.js')"></script>
@endpush
