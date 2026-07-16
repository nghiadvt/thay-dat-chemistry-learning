@extends('layouts.admin')

@section('title', 'Săn Rồng Hóa Học — Demo game mới')
@section('page-title', 'Game mới: Săn Rồng Hóa Học')

@push('head')
<link rel="stylesheet" href="@vasset('css/dragon-hunt-demo.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>🐲 Săn Rồng Hóa Học</h2>
        <span class="drg-page-note">🧪 Bản demo — dữ liệu giả lập bằng JS, chưa nối database</span>
    </div>
    <a href="{{ route('admin.games.index') }}" class="btn btn-secondary">← Quay lại danh sách game</a>
</div>

<div class="card drg-intro">
    <h3>Luật chơi — cả lớp hợp sức săn boss</h3>
    <p>Cả lớp là một tổ đội thợ săn rồng, cùng nhau hạ gục <strong>Hắc Long Dung Nham</strong>.
       Mỗi học sinh nhập vai một anh hùng với tuyệt kỹ riêng: Kiếm Sĩ Lửa lao vào chém, Cung Thủ Băng bắn tên,
       Pháp Sư Lôi gọi sét, Giả Kim Sư ném bình độc dược. Trả lời đúng → tung tuyệt kỹ trừ máu rồng
       (có tỉ lệ <strong>CHÍ MẠNG ×2</strong>, nổ vòng benzen + ký hiệu nguyên tố văng ra).
       Trả lời sai → Hắc Long <strong>điều chế axit</strong>: phun lửa nấu vạc thuốc, vạc sôi trào rồi
       bắn axit vào cả đội, mất 1 trái tim.
       Tích đủ năng lượng tổ đội → tự kích hoạt siêu kỹ năng <strong>🧪 Bão Phản Ứng Nhiệt Màu</strong>:
       6 bình hóa chất Li–Na–K–Cu–Ba–Sr rơi xuống, nổ đúng màu ngọn lửa đặc trưng của từng nguyên tố!
       Khi máu rồng dưới 40%, nó <strong>nổi giận</strong> — mạch độc trên ngực phát sáng, hiệu ứng dữ dội hơn.</p>
    <div class="drg-intro__rules">
        <span class="drg-rule-chip">✅ Đúng → <strong>tuyệt kỹ trừ máu rồng</strong></span>
        <span class="drg-rule-chip">💥 May mắn → <strong>chí mạng ×2 + vòng benzen</strong></span>
        <span class="drg-rule-chip">🧪 Đủ combo → <strong>bão phản ứng nhiệt màu</strong></span>
        <span class="drg-rule-chip">❌ Sai → <strong>rồng nấu vạc axit tấn công, mất 1 ❤️</strong></span>
    </div>
</div>

<div class="card" id="dragonHuntDemo">
    <h3>Hang Dung Nham (mô phỏng)</h3>

    <div class="drg-bossbar" id="drgBossBar">
        <span class="drg-bossbar__avatar">🐲</span>
        <div class="drg-bossbar__main">
            <div class="drg-bossbar__head">
                <span class="drg-bossbar__name">☠️ HẮC LONG DUNG NHAM</span>
                <span class="drg-bossbar__value">300 / 300</span>
            </div>
            <div class="drg-bossbar__track">
                <div class="drg-bossbar__ghost"></div>
                <div class="drg-bossbar__fill"></div>
            </div>
        </div>
    </div>

    <div class="drg-teambar">
        <div class="drg-teambar__group">Mạng đội: <span id="drgHearts"></span></div>
        <div class="drg-teambar__group">Năng lượng tổ đội: <span class="drg-combo-cells" id="drgCombo"></span></div>
    </div>

    <div class="drg-stage" id="drgStage">
        <div class="drg-rocks-top"></div>
        <div class="drg-lavaglow"></div>
        <div class="drg-cracks"></div>
        <div class="drg-rocks-bottom"></div>
        <div class="drg-lava"></div>

        <div class="drg-boss" id="drgBoss"></div>
        <div class="drg-heroes" data-heroes></div>

        <div class="drg-fx" id="drgFx"></div>
        <canvas class="drg-particles" id="drgParticles"></canvas>
        <div class="drg-announce" id="drgAnnounce"></div>

        <div class="drg-overlay" id="drgOverlay" hidden>
            <div class="drg-overlay__rain"></div>
            <div class="drg-overlay__card">
                <span class="drg-overlay__icon">🏆</span>
                <div class="drg-overlay__title">HẠ GỤC HẮC LONG!</div>
                <p class="drg-overlay__sub">Cả lớp nhận rương báu vật</p>
                <button type="button" class="btn btn-primary" data-drg="replay">🔄 Chơi lại</button>
            </div>
        </div>
    </div>

    <div class="drg-controls">
        <button type="button" class="btn btn-sm drg-btn-attack" data-drg="correct">Trả lời đúng ✔ — tấn công</button>
        <button type="button" class="btn btn-sm drg-btn-danger" data-drg="wrong">Trả lời sai ✘ — rồng phản công</button>
        <span class="drg-controls__spacer"></span>
        <label class="drg-speed-label">Tốc độ
            <select id="drg_speed">
                <option value="1">x1</option>
                <option value="1.5">x1.5</option>
                <option value="2">x2</option>
            </select>
        </label>
        <button type="button" class="btn btn-primary btn-sm" data-drg="auto">▶ Tự động demo</button>
        <button type="button" class="btn btn-secondary btn-sm" data-drg="sound">🔊 Âm thanh: bật</button>
        <button type="button" class="btn btn-secondary btn-sm" data-drg="reset">Đặt lại</button>
    </div>
    <p class="drg-config-hint">💡 Mẹo: bấm trực tiếp vào một anh hùng để em đó tung tuyệt kỹ riêng (chém / tên băng / sét / độc dược).</p>
</div>

<div class="card">
    <h3>Cấu hình gameplay (thử trực tiếp)</h3>
    <div class="drg-config-grid">
        <div class="form-group form-group--flush">
            <label for="drg_boss_hp">Máu boss</label>
            <input type="number" id="drg_boss_hp" min="50" max="5000" step="10" value="300">
        </div>
        <div class="form-group form-group--flush">
            <label for="drg_dmg">Sát thương khi đúng</label>
            <input type="number" id="drg_dmg" min="1" max="100" value="12">
        </div>
        <div class="form-group form-group--flush">
            <label for="drg_crit">Tỉ lệ chí mạng (%)</label>
            <input type="number" id="drg_crit" min="0" max="100" value="20">
        </div>
        <div class="form-group form-group--flush">
            <label for="drg_hearts">Số mạng của đội</label>
            <input type="number" id="drg_hearts" min="1" max="10" value="5">
        </div>
        <div class="form-group form-group--flush">
            <label for="drg_combo_need">Combo cần cho siêu kỹ năng</label>
            <input type="number" id="drg_combo_need" min="2" max="12" value="5">
        </div>
    </div>
    <p class="drg-config-hint">
        Đổi "Máu boss", "Số mạng" hoặc "Combo cần" sẽ đặt lại trận; các thông số còn lại áp dụng ngay lượt kế tiếp.
        Khi xây dựng database, thông số sẽ lưu vào <code>mode_config</code> như game Đua vịt
        (dự kiến play mode slug: <code>dragon_hunt</code>).
    </p>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/dragon-hunt-demo.js')"></script>
@endpush
