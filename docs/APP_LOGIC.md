# App Logic — Hóa Thầy Đạt

> Logic ứng dụng: luồng màn hình, state, scoring, bàn phím, WebSocket events.
> Nguồn gốc: [`dac-ta-ky-thuat-v4.docx.md`](../dac-ta-ky-thuat-v4.docx.md) v4.0

**Cập nhật lần cuối:** 2026-06-30

---

## 1. Luồng học sinh (state machine)

```
Welcome → Join PIN → Nhập tên → Avatar (skip OK) → Phòng chờ
  → [GV START] → Question → Submit → Result → Leaderboard → Question ... → Final
```

### Chi tiết từng màn hình

| Màn hình | ID | Bắt buộc | Mô tả |
|---|---|---|---|
| Welcome | `welcome` | — | Landing, nút "Tham gia phòng" + "Hướng dẫn" |
| Join PIN | `join` | PIN 6 số | Validate PIN, tab QR placeholder |
| Hướng dẫn | `guide` | — | Text tĩnh, nút quay lại |
| Nhập tên | `name` | Tên ≤20 ký tự | Không cho rỗng, trim whitespace |
| Avatar | `avatar` | Tùy chọn | Camera chụp ảnh hoặc "Bỏ qua" → avatar ngẫu nhiên |
| Phòng chờ | `waiting` | — | Hiển thị tên phòng, GV, danh sách người join. Chờ event `START` |
| Question MC | `question-mc` | — | Trắc nghiệm 4 đáp án A–D |
| Question Formula | `question-formula` | — | Nhập công thức qua bàn phím ảo 3 tab |
| Submit | `submit` | — | Spinner "Đã nộp! Chờ kết quả...", bàn phím ẩn |
| Result | `result` | — | Đúng/sai, điểm, confetti nếu đúng |
| Leaderboard | `leaderboard` | — | Top 5, hiển thị 5 giây, animation reorder |
| Final | `final` | — | Podium top 3, nút "Chơi lại" / "Về trang chủ" |

### Quy tắc join phòng

- **Không yêu cầu login** — ai có link/PIN đều vào được
- PIN: 6 chữ số
- Tên hiển thị: bắt buộc, tối đa 20 ký tự
- Avatar: tùy chọn — chụp camera hoặc bỏ qua (server gán avatar ngẫu nhiên)
- Production: WebSocket handshake + NTP sync ngay tại bước join

---

## 2. Luồng giáo viên (tham khảo — chưa implement)

| Màn hình | Hành động |
|---|---|
| Dashboard | Tạo game, chọn bộ câu hỏi, cấu hình thời gian/số câu |
| Lobby | Nhận PIN + QR, chờ HS join, Start khi ≥2 người, kick |
| Host | Điều khiển câu hỏi, xem submit count, Next/End |

---

## 3. Hệ thống tính điểm

Công thức Kahoot (spec 3.2):

```
điểm = 1000 × (time_remaining / time_limit) × accuracy_bonus

time_remaining : giây còn lại khi submit (Hybrid Timestamp)
accuracy_bonus : 1.0 (đúng) | 0 (sai)
streak bonus   : đúng liên tiếp ≥3 câu → +50 mỗi câu tiếp theo
```

**Ví dụ:** Câu 30s, submit sau 3s, đúng → `1000 × (27/30) = 900 điểm`

> Prototype hiện tại: hiển thị điểm fake, chưa tính theo công thức.

---

## 4. Bàn phím hóa học (spec — chưa implement logic)

### 4.1 Layout — 3 tab

**Tab Nguyên tố (mặc định):**
```
[H] [O] [C] [N] [Na] [K]  [⌫]
[Ca][Fe][Cu][Al][Cl][S]  [Mg]
[P] [Zn][Ag][Mn][Cr][Ba][Br]
[Tab:Nguyên tố] [Tab:Số] [Tab:Ký hiệu]
              [ SUBMIT ]
```

**Tab Số & Hệ số:** hệ số 1–8 (số to) + subscript ₂–₁₂

**Tab Ký hiệu:** +, →, (, ), ·, =, ↑, ↓, ,, ;

### 4.2 Token model

```javascript
type TokenType = 'coefficient' | 'element' | 'subscript' | 'symbol'

interface Token {
  type: TokenType
  value: string    // raw: '2', 'H', '2', '+'
  display: string  // rendered: '2', 'H', '₂', '+'
}

// Ví dụ 2H₂O:
tokens = [
  { type: 'coefficient', value: '2', display: '2' },
  { type: 'element',     value: 'H', display: 'H' },
  { type: 'subscript',   value: '2', display: '₂' },
  { type: 'element',     value: 'O', display: 'O' },
]

serialize(tokens) → '2H2O'  // plain text, server normalize
```

### 4.3 Smart input context

| Vị trí con trỏ | Số tiếp theo là |
|---|---|
| Đầu công thức hoặc sau `+` | Hệ số (coefficient, số to) |
| Sau nguyên tố | Subscript (chỉ số dưới, nhỏ) |

- Backspace: xóa **1 token** (xóa `Na` = cả 2 ký tự)
- Highlight nguyên tố vừa nhập: 1 giây rồi mờ
- Emit event `formula-change` với `{ tokens, serialized }`

> Prototype hiện tại: bàn phím mock tĩnh (HTML/CSS), chưa có token logic.
> Implement sau khi chốt UI → `keyboard.js` + `keyboard-config.js`.

---

## 5. WebSocket events (dự kiến — production)

| Event | Direction | Payload | Mô tả |
|---|---|---|---|
| `JOIN` | C→S | `{ pin, name, avatar? }` | Học sinh vào phòng |
| `JOINED` | S→C | `{ playerId, room }` | Xác nhận join |
| `PLAYER_JOINED` | S→All | `{ player }` | Broadcast người mới |
| `START` | S→All | `{ questionIndex }` | GV bắt đầu game |
| `QUESTION` | S→All | `{ index, type, text, options?, timeLimit }` | Câu hỏi mới |
| `SUBMIT` | C→S | `{ answer, timestamp }` | Nộp đáp án |
| `SUBMITTED` | S→C | `{ ok }` | Xác nhận nộp |
| `RESULT` | S→All | `{ correct, points, rank }` | Kết quả câu |
| `LEADERBOARD` | S→All | `{ top5[], deltas[] }` | Bảng xếp hạng |
| `END` | S→All | `{ finalRanking[] }` | Kết thúc game |

### NTP sync (production)

- 3 vòng ping-pong khi join
- Tính median offset
- Hybrid timestamp: reject submit lệch >500ms

---

## 6. Fake data (prototype)

```javascript
const FAKE_ROOM = {
  pin: '123456',
  name: 'HÓA HỌC 10A1',
  teacher: 'Thầy Đạt',
};

const FAKE_QUESTIONS = [
  {
    type: 'mc',
    text: 'Nguyên tố nào có số hiệu nguyên tử bằng 1?',
    options: ['Heli', 'Hydro', 'Liti', 'Cacbon'],
    correct: 1,
    timeLimit: 20,
  },
  {
    type: 'mc',
    text: 'Công thức hóa học của nước là gì?',
    options: ['H2O', 'CO2', 'NaCl', 'O2'],
    correct: 0,
    timeLimit: 20,
  },
  {
    type: 'formula',
    text: 'Viết công thức hóa học của axit sunfuric',
    correct: 'H2SO4',
    timeLimit: 30,
  },
];

const FAKE_STUDENTS = [
  { id: 'me',  name: '',          score: 0,  avatar: null },
  { id: 's2',  name: 'Minh Anh',  score: 850, avatar: null },
  { id: 's3',  name: 'Tuấn Kiệt', score: 720, avatar: null },
  { id: 's4',  name: 'Lan Chi',   score: 680, avatar: null },
  { id: 's5',  name: 'Hoàng Nam', score: 540, avatar: null },
];
```

---

## 7. App state (prototype)

```javascript
const appState = {
  screen: 'welcome',
  pin: '',
  studentName: '',
  avatarDataUrl: null,
  questionIndex: 0,
  timer: 20,
  timeLimit: 20,
  myScore: 0,
  selectedAnswer: null,
  formulaInput: '',
  submitted: false,
  lastResult: null,       // { correct, points, answer }
  prevRanks: {},
  students: [],
  streak: 0,
};
```

Screen routing: `showScreen(id)` toggle class `active` trên `<section data-screen="...">`.

---

## 8. Tài liệu liên quan

- [`docs/SYSTEM_DESIGN.md`](SYSTEM_DESIGN.md) — Kiến trúc hệ thống
- [`docs/APP_STYLE.md`](APP_STYLE.md) — Design tokens, layout
- [`prototype/index.html`](../prototype/index.html) — UI prototype
