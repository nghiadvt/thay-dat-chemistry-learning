# App Style — Hóa Thầy Đạt

> Design tokens, layout, component patterns cho **UI học sinh (mobile only)**.
> **Admin + Teacher:** web desktop — Laravel `/admin` nhúng prototype qua iframe (`embedded=admin`); CSS embed ghi ở §12.

**Cập nhật lần cuối:** 2026-07-08 (PWA standalone — học sinh cài màn hình chính)

---

## 1. Design principles

> **Phạm vi:** chỉ áp dụng cho `prototype/index.html` (học sinh). Admin (`php-admin`) và teacher (`teacher.html`) là web desktop.

- **Mobile-only:** thiết kế cho ≥375px, full viewport height — không target desktop cho học sinh
- **Không scroll** trên màn Question (layout cố định theo %)
- **1 ngón cái** thao tác được bàn phím hóa học
- **Placeholder assets** cho đến khi user cung cấp icon/ảnh thật
- **PWA (app-like):** học sinh **thêm vào Màn hình chính** để ẩn thanh URL trình duyệt (`display: standalone`). Mở link thường trong Chrome/Safari/Google vẫn có chrome trình duyệt — không thể tắt bằng code.

---

## 1b. PWA — hiển thị như app (không thanh URL)

| Cách mở | Thanh URL / header trình duyệt |
|---|---|
| Link thường (Google, Zalo, Chrome tab) | **Có** — trình duyệt bắt buộc hiển thị |
| **Thêm vào Màn hình chính** (PWA) | **Không** — full màn hình app (`standalone`) |

**Cài đặt cho học sinh:**

| Nền tảng | Thao tác |
|---|---|
| **Android (Chrome)** | Menu ⋮ → *Cài đặt ứng dụng* / *Thêm vào Màn hình chính* |
| **iPhone (Safari)** | Chia sẻ ↗ → *Thêm vào Màn hình chính* |
| Mở từ app Google / Facebook | Thường có thanh URL riêng → chọn *Mở bằng Chrome/Safari* hoặc cài PWA từ trình duyệt hệ thống |

**File kỹ thuật:** `prototype/manifest.webmanifest` (`start_url: /join`, `display: standalone`), `prototype/sw.js`, meta `apple-mobile-web-app-*` + `theme-color` trong `index.html`. Route Laravel: `/manifest.webmanifest`, `/sw.js`.

**Production:** cần **HTTPS** để Chrome cho phép cài PWA (localhost được miễn).

---

## 2. Color palette

| Token | Hex | Dùng cho |
|---|---|---|
| `--bg-gradient-start` | `#1a1a4e` | Nền app (trên) |
| `--bg-gradient-end` | `#2D3192` | Nền app (dưới) |
| `--primary` | `#3B5BDB` | Nút chính, accent |
| `--primary-dark` | `#2D3192` | Nút pressed, header |
| `--card-bg` | `#FFFFFF` | Card, input area |
| `--text-on-dark` | `#FFFFFF` | Text trên nền tối |
| `--text-on-light` | `#1b333333` | Text trên card |
| `--text-muted` | `#666666` | Text phụ |
| `--success` | `#22c55e` | Đúng, rank up |
| `--success-dark` | `#1A5C38` | Submit button gradient start |
| `--success-light` | `#2ECC71` | Submit button gradient end |
| `--error` | `#ef4444` | Sai, rank down |
| `--warning` | `#f59e0b` | Timer ≤5s |
| `--keyboard-key` | `#1F4E79` | Phím bàn phím nguyên tố |
| `--keyboard-key-shadow` | `#0D3052` | Depth shadow phím |
| `--formula-border` | `#2E75B6` | Border dưới input công thức |
| `--gold` | `#FFD700` | Hạng 1 |
| `--silver` | `#C0C0C0` | Hạng 2 |
| `--bronze` | `#CD7F32` | Hạng 3 |

---

## 3. Typography

| Element | Font | Size | Weight |
|---|---|---|---|
| App title | `'Be Vietnam Pro', sans-serif` | 28–32px | 800 |
| Screen heading | `'Be Vietnam Pro', sans-serif` | 22–24px | 700 |
| Body text | `'Be Vietnam Pro', sans-serif` | 16px | 400 |
| Button | `'Be Vietnam Pro', sans-serif` | 16–18px | 600 |
| Question text | `'Be Vietnam Pro', sans-serif` | clamp(16px, 4vw, 20px) | 600 |
| Formula display | `'STIX Two Text', 'Times New Roman', serif` | 28px | 400 |
| Keyboard key | `'Be Vietnam Pro', sans-serif` | 16px | 700 |
| Timer | `'Be Vietnam Pro', sans-serif` | 18px | 700 |
| Score / rank | `'Be Vietnam Pro', sans-serif` | 14–16px | 600 |

Fonts load qua Google Fonts CDN.

---

## 4. Spacing & radius

| Token | Value |
|---|---|
| `--radius-sm` | 8px |
| `--radius-md` | 12px |
| `--radius-lg` | 16px |
| `--radius-xl` | 24px |
| `--radius-full` | 50% |
| `--padding-screen` | 20px |
| `--padding-card` | 24px |
| `--gap-sm` | 8px |
| `--gap-md` | 12px |
| `--gap-lg` | 16px |
| `--shadow-card` | `0 4px 24px rgba(0,0,0,0.15)` |
| `--shadow-button` | `0 4px 0 rgba(0,0,0,0.2)` |

---

## 5. Question screen layout (spec 3.1)

Full viewport height, flex column, **không scroll**:

| Vùng | Chiều cao | Nội dung |
|---|---|---|
| Header | 8% | `Câu X/Y`, progress bar, điểm hiện tại |
| Timer | 12% | SVG countdown tròn |
| Câu hỏi | 25% | Text căn giữa, scroll nội bộ nếu dài |
| Input display | 15% | Formula preview, cursor nhấp nháy |
| Bàn phím | 40% | `keyboards.config` full-width neo đáy màn (giống numpad PIN); không thu gọn |

### Timer colors

| Thời gian còn lại | Màu vòng |
|---|---|
| >5s | Xanh `#22c55e` |
| ≤5s | Vàng `#f59e0b` |
| ≤3s | Đỏ `#ef4444` + rung nhẹ (`animation: shake`) |

---

## 6. Component patterns

### 6.1 Button — Primary

```css
.btn-primary {
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  padding: 14px 24px;
  font-size: 16px;
  font-weight: 600;
  width: 100%;
  box-shadow: 0 4px 0 #1e3a8a;
  transition: transform 0.08s;
}
.btn-primary:active {
  transform: translateY(2px);
  box-shadow: 0 2px 0 #1e3a8a;
}
```

### 6.2 Button — Secondary (outline)

```css
.btn-secondary {
  background: transparent;
  color: white;
  border: 2px solid rgba(255,255,255,0.5);
  border-radius: var(--radius-md);
  padding: 12px 24px;
}
```

### 6.3 Card

```css
.card {
  background: var(--card-bg);
  border-radius: var(--radius-lg);
  padding: var(--padding-card);
  box-shadow: var(--shadow-card);
}
```

### 6.4 PIN input (6 ô)

- 6 ô vuông, gap 8px, flex center
- Mỗi ô: 44×52px, border 2px `#ddd`, radius 8px
- Focus/active: border `--primary`, background `#f0f4ff`
- Font size 24px, text-align center

### 6.5 MC option button

- Full width, padding 16px, radius 12px
- Border 2px `#e5e7eb`, background white
- Label A/B/C/D trong circle màu `--primary`
- Selected: border `--primary`, background `#eff6ff`
- Correct highlight: border `--success`, background `#f0fdf4`

### 6.6 Keyboard key

```css
.key-element {
  min-width: 44px;
  min-height: 44px;
  background: var(--keyboard-key);
  color: white;
  border-radius: var(--radius-sm);
  font-size: 16px;
  font-weight: 700;
  box-shadow: 0 3px 0 var(--keyboard-key-shadow);
  transition: transform 0.08s, box-shadow 0.08s;
}
.key-element:active {
  transform: translateY(2px);
  box-shadow: 0 1px 0 var(--keyboard-key-shadow);
}
```

### 6.7 Submit button

```css
.key-submit {
  width: 100%;
  height: 52px;
  background: linear-gradient(135deg, #1A5C38, #2ECC71);
  color: white;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 1px;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 0 #0D3A22;
  border: none;
}
```

### 6.8 Formula display

```css
.formula-display {
  font-family: 'STIX Two Text', 'Times New Roman', serif;
  font-size: 28px;
  min-height: 48px;
  border-bottom: 2px solid var(--formula-border);
  text-align: center;
}
.formula-display sub {
  font-size: 0.65em;
  vertical-align: sub;
}
```

### 6.9 Rank badge

| Hạng | Style |
|---|---|
| 1 | Circle `--gold`, text dark |
| 2 | Circle `--silver`, text dark |
| 3 | Circle `--bronze`, text white |
| 4+ | Circle `#e5e7eb`, text `#666` |
| Rank up | Arrow `↑` màu `--success` |
| Rank down | Arrow `↓` màu `--error` |

### 6.10 Avatar

- Circle 80–100px, border 3px white
- Placeholder: emoji hoặc gradient + initials
- Camera preview: `<video>` trong circle, object-fit cover (chỉ HTTPS/`localhost`)
- **HTTP LAN (mặc định khi test phone):** `<input type="file" accept="image/*" capture>` — mở camera native / chọn ảnh; không phụ thuộc `getUserMedia`

---

## 7. Screen-specific notes

### Welcome
- Gradient background full screen
- Illustration placeholder (emoji 🧪 hoặc SVG)
- 2 nút stacked: Primary "Tham gia phòng", Secondary "Hướng dẫn"

### Waiting room
- Card trắng center
- Icon success ✓ "Thành công!"
- Tên phòng UPPERCASE, tên GV
- Illustration học sinh placeholder
- Text "Hãy chờ giáo viên bắt đầu trò chơi"
- Pulse animation trên waiting indicator

### Result overlay
- Full screen overlay, backdrop blur
- Circle lớn: xanh ✓ hoặc đỏ ✗
- Text "Đúng rồi!" / "Chưa đúng!"
- Điểm `+XXX điểm`
- Confetti khi đúng (canvas-confetti CDN)

### Leaderboard
- Banner "Cập nhật bảng xếp hạng" + trophy icon
- Tab "Toàn phòng" / "Của bạn" (visual only in prototype)
- List item: rank circle + avatar + name + score + delta
- FLIP animation khi reorder (`transition: transform 0.5s ease`)

### Final / Podium
- Top 3 podium visual: #1 cao nhất giữa
- Gold/silver/bronze styling
- Nút "Chơi lại" + "Về trang chủ"

---

## 8. Animation

| Animation | CSS/JS | Dùng cho |
|---|---|---|
| Screen fade | `opacity 0.3s` | Chuyển màn hình |
| Button press | `translateY(2px)` | Tap feedback |
| Timer shake | `@keyframes shake` | ≤3s còn lại |
| Rank slide | FLIP technique JS | Leaderboard reorder |
| Waiting pulse | `@keyframes pulse` | Phòng chờ |
| Confetti | canvas-confetti CDN | Result đúng |
| Cursor blink | `@keyframes blink` | Formula input |

---

## 9. Responsive

- **Base:** 375px width (iPhone SE / standard mobile)
- **Max width:** 480px — app container centered trên desktop preview
- Touch targets: tối thiểu **44×44px**
- Safe area: `padding-bottom: env(safe-area-inset-bottom)` cho iPhone notch

---

## 10. Assets placeholder

| Asset | Placeholder hiện tại | Thay bằng |
|---|---|---|
| Logo / illustration Welcome | Emoji 🧪👨‍🏫 | User cung cấp |
| Waiting room illustration | Emoji 👨‍🎓👩‍🎓 | User cung cấp |
| Avatar default | Initials / emoji | User cung cấp |
| Trophy | Emoji 🏆 | User cung cấp |
| Icons (back, camera, delete) | Unicode / inline SVG | User cung cấp |

---

## 12. Admin tools (Laravel Blade + `public/htd-admin/`)

GV dùng **Blade trong `/admin`**, JS/CSS tại `php-admin/public/htd-admin/` — không trùng URL `/admin` (nginx 403 nếu có thư mục `public/admin/`).

| Tool | Blade | Assets |
|---|---|---|
| Keyboard editor | `admin/keyboards/editor` + `_editor-body` | `htd-admin/css/keyboard-editor.css`, `htd-admin/js/keyboard-editor.js` |
| Host phòng | `admin/sessions/show` + `_host-panel` | `htd-admin/css/session-host.css`, `htd-admin/js/teacher.js` |

Init qua `window.ADMIN_BOOT` + `admin-boot.js` (cùng origin API, `config('services.ws.url')` cho Socket.io).

---

## 13. Tài liệu liên quan

- [`docs/SYSTEM_DESIGN.md`](SYSTEM_DESIGN.md) — Kiến trúc hệ thống
- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — Logic ứng dụng
- [`prototype/index.html`](../prototype/index.html) — UI prototype học sinh
- [`prototype/teacher.html`](../prototype/teacher.html) — Host (embed admin)
- [`prototype/keyboard-editor.html`](../prototype/keyboard-editor.html) — Editor bàn phím (embed admin)
