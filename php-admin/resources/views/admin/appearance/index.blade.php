@extends('layouts.admin')

@section('title', 'Giao diện — Hóa Thầy Đạt')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-appearance.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Chọn giao diện admin</h2>
        <p class="page-header__meta">Lưu trên trình duyệt này — mỗi máy có thể chọn theme riêng, đổi là áp dụng ngay không cần tải lại trang.</p>
    </div>
</div>

<div class="theme-pick-grid" id="themePickGrid">

    <article class="theme-pick-card" data-theme-option="default">
        <div class="theme-pick-preview theme-pick-preview--default" aria-hidden="true">
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>Mặc định — Sáng</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Giao diện gốc nền sáng, gọn gàng, quen thuộc.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="default">Dùng theme này</button>
        </div>
    </article>

    <article class="theme-pick-card" data-theme-option="lab">
        <div class="theme-pick-preview theme-pick-preview--lab" aria-hidden="true">
            <span class="tpp-lab-bubble" style="left:18%; animation-delay:-1s"></span>
            <span class="tpp-lab-bubble" style="left:52%; animation-delay:-3.4s; width:7px; height:7px"></span>
            <span class="tpp-lab-bubble" style="left:82%; animation-delay:-5.2s; width:5px; height:5px"></span>
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>🧪 Phòng Thí Nghiệm — Neon tối</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Theme hóa học riêng của Hóa Thầy Đạt: nền tối kính mờ, viền phát sáng cyan–lục,
               bong bóng khí bay, phân tử trôi lơ lửng, nút neon có vệt sáng quét, menu như kệ hóa chất.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="lab">Dùng theme này</button>
        </div>
    </article>

    <article class="theme-pick-card" data-theme-option="notebook">
        <div class="theme-pick-preview theme-pick-preview--notebook" aria-hidden="true">
            <span class="tpp-nb-doodle">✏️</span>
            <span class="tpp-nb-doodle tpp-nb-doodle--2">⭐</span>
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>📓 Sổ Tay Học Trò — Giấy ấm</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Thể loại khác hẳn: nền vở ô ly, sidebar như bìa sổ da khâu chỉ, menu active là miếng
               washi tape vàng, card giấy dán băng dính, nút kiểu sticker dập nổi, doodle bút chì –
               máy bay giấy – H₂O bay lơ lửng, font tròn trịa Baloo.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="notebook">Dùng theme này</button>
        </div>
    </article>

    <article class="theme-pick-card" data-theme-option="arcade">
        <div class="theme-pick-preview theme-pick-preview--arcade" aria-hidden="true">
            <span class="tpp-arc-star" style="left:30%; top:18%"></span>
            <span class="tpp-arc-star" style="left:58%; top:64%; animation-delay:-.7s"></span>
            <span class="tpp-arc-star" style="left:86%; top:30%; animation-delay:-1.3s"></span>
            <span class="tpp-arc-invader">👾</span>
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>🕹️ Arcade 8-bit — Game thùng retro</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Cả trang admin thành máy game thùng: font pixel VT323, màn hình CRT có scanline,
               viền vuông cứng + bóng đổ pixel, menu kiểu "▶ chọn màn" nhấp nháy INSERT COIN,
               sao pixel lấp lánh, phi thuyền 👾 – đồng xu – trái tim 8-bit trôi ngang màn hình.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="arcade">Dùng theme này</button>
        </div>
    </article>

    <article class="theme-pick-card" data-theme-option="chalk">
        <div class="theme-pick-preview theme-pick-preview--chalk" aria-hidden="true">
            <span class="tpp-chalk-formula">2H₂ + O₂ → 2H₂O</span>
            <span class="tpp-chalk-dust" style="left:40%; animation-delay:-2s"></span>
            <span class="tpp-chalk-dust" style="left:72%; animation-delay:-5s"></span>
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>🧑‍🏫 Bảng Phấn Lớp Học — Bảng xanh</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Cả trang admin là tấm bảng xanh có khung gỗ quanh màn hình: chữ viết tay kiểu phấn,
               card viền phấn nét đứt góc lệch, menu active được khoanh tròn bằng phấn vàng,
               nút hover như được tô kín phấn, bụi phấn rơi và công thức hóa học viết phấn trôi phía sau.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="chalk">Dùng theme này</button>
        </div>
    </article>

    <article class="theme-pick-card" data-theme-option="galaxy">
        <div class="theme-pick-preview theme-pick-preview--galaxy" aria-hidden="true">
            <span class="tpp-gal-star" style="left:34%; top:20%"></span>
            <span class="tpp-gal-star" style="left:60%; top:66%; animation-delay:-.9s"></span>
            <span class="tpp-gal-star" style="left:88%; top:44%; animation-delay:-1.6s"></span>
            <span class="tpp-gal-shoot"></span>
            <span class="tpp-gal-planet">🪐</span>
            <div class="tpp-sidebar">
                <span class="tpp-brand"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav tpp-nav--active"></span>
                <span class="tpp-nav"></span>
                <span class="tpp-nav"></span>
            </div>
            <div class="tpp-main">
                <div class="tpp-topbar"></div>
                <div class="tpp-cards">
                    <span class="tpp-card"></span>
                    <span class="tpp-card"></span>
                    <span class="tpp-card tpp-card--wide"></span>
                </div>
            </div>
        </div>
        <div class="theme-pick-body">
            <div class="theme-pick-title-row">
                <h3>🌌 Vũ Trụ Galaxy — Tinh vân tím</h3>
                <span class="theme-pick-badge" data-active-badge hidden>Đang dùng</span>
            </div>
            <p>Đài quan sát giữa dải ngân hà: nền tinh vân tím–hồng–xanh, sao lấp lánh khắp màn hình,
               sao băng vụt qua định kỳ, hành tinh 🪐 có vành đai trôi lơ lửng, card khoang tàu kính mờ,
               nút gradient tím–hồng có vệt sáng quét.</p>
            <button type="button" class="btn btn-primary" data-choose-theme="galaxy">Dùng theme này</button>
        </div>
    </article>

</div>

<div class="card theme-pick-note">
    <h3>Ghi chú</h3>
    <ul>
        <li>Lựa chọn lưu bằng <code>localStorage</code> của trình duyệt — chưa lưu vào tài khoản/database.</li>
        <li>Theme "Phòng Thí Nghiệm" chỉ đổi giao diện khu admin; màn hình host phòng chơi và màn học sinh không bị ảnh hưởng.</li>
        <li>Muốn thêm theme mới: tạo file CSS scoped theo <code>html[data-theme="tên"]</code> rồi khai báo thêm thẻ chọn ở trang này.</li>
    </ul>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/admin-theme.js')"></script>
<script>
(function () {
  function syncCards() {
    var current = window.AdminTheme.get();
    document.querySelectorAll('[data-theme-option]').forEach(function (card) {
      var name = card.dataset.themeOption;
      var isActive = name === current;
      card.classList.toggle('is-active', isActive);
      var badge = card.querySelector('[data-active-badge]');
      var btn = card.querySelector('[data-choose-theme]');
      if (badge) badge.hidden = !isActive;
      if (btn) {
        btn.disabled = isActive;
        btn.textContent = isActive ? '✓ Đang dùng' : 'Dùng theme này';
      }
    });
  }

  document.querySelectorAll('[data-choose-theme]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      window.AdminTheme.set(btn.dataset.chooseTheme);
      syncCards();
      if (window.AdminToast) {
        var messages = {
          lab: 'Đã bật theme Phòng Thí Nghiệm 🧪',
          notebook: 'Đã bật theme Sổ Tay Học Trò 📓',
          arcade: 'Đã bật theme Arcade 8-bit 🕹️ INSERT COIN!',
          chalk: 'Đã bật theme Bảng Phấn Lớp Học 🧑‍🏫',
          galaxy: 'Đã bật theme Vũ Trụ Galaxy 🌌',
          'default': 'Đã về theme mặc định',
        };
        window.AdminToast.show(messages[btn.dataset.chooseTheme] || 'Đã đổi theme', 'success');
      }
    });
  });

  document.addEventListener('DOMContentLoaded', syncCards);
  syncCards();
})();
</script>
@endpush
