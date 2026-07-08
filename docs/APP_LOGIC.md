# App Logic — Hóa Thầy Đạt

> Logic ứng dụng: luồng màn hình, state, scoring, bàn phím, WebSocket events.
> Nguồn gốc: [`dac-ta-ky-thuat-v4.docx.md`](../dac-ta-ky-thuat-v4.docx.md) v4.0

**Cập nhật lần cuối:** 2026-07-08 (chơi lại phòng ended từ danh sách / host)

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

## 2. Luồng giáo viên (Laravel `/admin`)

| Bước | Path | Hành động |
|---|---|---|
| Soạn nội dung | `/admin/keyboards`, `/admin/games`, … | CRUD trong Blade |
| Tạo phòng | `/admin/sessions/create` | Nhập **tên phòng** + lọc game → chọn quiz → PIN + QR |
| Danh sách phòng | `/admin/sessions` | Tên, PIN, QR, quiz, GV, bật/tắt, **Chơi thử** (modal xem quiz như HS) |
| Vào phòng | `/admin/sessions/{id}` | Host native (Blade + `public/htd-admin/js/teacher.js`) |
| Link HS | `/join/{pin}` | Màn học sinh mobile (Laravel serve `prototype/index.html` + `<base href="/app/">`) |

**Không dùng** `/app/teacher.html` hay `/app/keyboard-editor.html` cho GV — logic đã chuyển vào `php-admin/public/admin/` + views `resources/views/admin/`.

### Bàn phím

1. `/admin/keyboards/create` — nhập tên
2. `/admin/keyboards/{id}/editor` — kéo thả layout, **Save** → `PUT /api/keyboards/{id}`
3. Không import/export JSON trong admin

### Host phòng

- Blade: `admin/sessions/_host-panel.blade.php` (markup host, asset `public/htd-admin/`)
- Init: `admin-session-init.js` → `joinExistingRoomFromAdmin()`
- Scripts: **`equation-ui.js` bắt buộc** trước `teacher.js` (render MC/structured)
- Config: `window.ADMIN_BOOT` (pin, roomName, quizName, gameName, quizId, sessionId, joinUrl, wsUrl)
- Layout: full viewport (`admin-body--session-host`) — đề bài HTML + đáp án MC (highlight đúng) + barem/giải thích sidebar
- Kết thúc quiz: WS `game_ended` → màn **Kết thúc trò chơi** (podium top 3 + bảng điểm + Tải CSV); `room.status = ended` vẫn giữ `teacherGameView` hiển thị
- **Chơi lại:** `POST /admin/sessions/{id}/reset` hoặc nút **Chơi lại** — reset MySQL `waiting` + xóa Redis; cùng PIN; báo cáo lần trước vẫn trong `/admin/reports`
- WS `new_question` host nhận thêm `correct_index`, `correct_answer_normalized`, `correct_answer`, `explanation`
- Danh sách HS: chỉ học sinh join thật qua WS `players_update` (không pad fake)

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

## 3.1 Loại câu hỏi & đáp án (schema — xem `DATA_MODEL.md`)

### Nội dung câu hỏi — 1 cột `content`

Đề bài (text, ảnh, video) gộp trong **`questions.content`** (LONGTEXT HTML).

- Admin: rich text editor (Tiptap/TinyMCE) + upload ảnh/video
- Server: **sanitize HTML** trước khi lưu
- Client: render `content` trong vùng câu hỏi (không ghép field riêng)

Ví dụ HTML lưu trong DB:

```html
<p>Cho mẫu dung dịch trong ống nghiệm như hình...</p>
<img src="/storage/q42.png" alt="ống nghiệm">
<video src="/storage/demo.mp4" poster="/storage/demo-poster.jpg" controls></video>
```

### Loại đáp án — `answer_type`

| `answer_type` | Mô tả | UI học sinh | Field đáp án đúng |
|---|---|---|---|
| `mc` | Trắc nghiệm 2–6 đáp án | Chọn A–D (hoặc nhiều hơn) | `options` JSON + `correct_index` |
| `formula` | Nhập công thức (bàn phím hóa học) | Bàn phím ảo | `correct_answer_normalized` |
| `structured` | Điền blank / hệ số theo template | Ô blank trên phương trình | `input_mode`, `template`, `correct_answer` JSON |

`input_mode` (structured): `product` | `balance` | `blank` | `blank_balance`

Mọi câu đều có `content` HTML; `answer_type` quyết định phần tương tác phía dưới.

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

> **Keyboard editor Test:** đã có token logic (`formulaAppendToken` trong `keyboard-editor.js`).
> Runtime học sinh: implement sau → `keyboard.js` + `keyboard-config.js`.

---

## 5. WebSocket events (production — đồng bộ `local-deployment-plan.md`)

| Event | Direction | Payload | Mô tả |
|---|---|---|---|
| `join_room` | C→S | `{ pin, name }` | HS vào phòng |
| `ntp_ping` / `ntp_pong` | both | `{ t0 }` / `{ t0, t1, t2 }` | Đồng bộ thời gian |
| `game_started` | S→C | `{}` | GV bắt đầu |
| `new_question` | S→C | xem payload bên dưới | Câu hỏi mới |
| `submit_answer` | C→S | `{ question_id, answer, hybrid_timestamp }` | Nộp đáp án |
| `question_result` | S→C | `{ correct, correct_answer, score_earned, rank, total_score, explanation? }` | Kết quả câu; `explanation` (HTML) chỉ khi quiz bật `show_explanation` |
| `leaderboard_update` | S→C | `{ top5: [{ name, score, delta }] }` | Top 5 |
| `game_ended` | S→C | `{ final_leaderboard }` | Kết thúc |
| `submit_count_update` | S→C (host) | `{ submitted, total }` | Số người đã nộp |

### Payload `new_question`

```json
{
  "quiz_id": 1,
  "question_id": 42,
  "content": "<p>Đề bài...</p><img src=\"...\">",
  "answer_type": "mc",
  "options": ["A", "B", "C", "D"],
  "template": null,
  "keyboard_config": {
    "schema_version": 1,
    "defaults": { "keySize": "M", "fontSize": "M", "textColor": "#000000", "background": "#FFFFFF", "border": "#D0D0D0" },
    "rows": [],
    "smart_context": { "after_element": "subscript", "after_plus": "coefficient" }
  },
  "time_limit": 30,
  "server_time": 1710000000000
}
```

- `content`: HTML đã sanitize (text + ảnh + video)
- `options` / `template`: gửi khi `answer_type` tương ứng; null nếu không dùng
- `keyboard_config`: từ quiz → keyboard; client render bàn phím cho `formula` / `structured`
- **Xáo trộn đáp án** (`quizzes.shuffle_options = true`): ws-server gửi `new_question` riêng từng HS với `options` đã shuffle; map gốc lưu Redis `room:{PIN}:option_order:{question_id}:{student_name}`; HS submit index theo thứ tự đã hiển thị → server map về `correct_index` gốc trước khi chấm
- **Giải thích đáp án** (`quizzes.show_explanation = true`): sau submit, `question_result` kèm `explanation` (HTML từ `questions.explanation`) nếu câu có nội dung giải thích

Admin cấu hình hai toggle này tại `/admin/quizzes/{id}` → mục **Thông tin quiz** → **Tùy chọn khi chơi**.

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

## 8. Phase 3A — Integration layer (prototype ↔ backend)

**Mục tiêu:** chạy end-to-end với UI hiện tại; **không** redesign CSS/HTML (Phase 3B polish sau).

**Serve UI học sinh:** `http://localhost:38480/join` hoặc `/join/{PIN}` — cùng port với admin/API; static assets (`css/`, `js/`) vẫn tại `/app/` (Docker mount `prototype/`).

**Demo mode (localStorage cũ):** thêm `?demo=1` vào URL.

### File tích hợp (`prototype/js/`)

| File | Vai trò |
|---|---|
| `config.js` | `HTD_CONFIG`: `apiBase`, `wsUrl`, `useBackend` |
| `api.js` | `HTDApi`: check PIN, session, keyboard CRUD, CSRF/session |
| `socket.js` | `HTDSocket`: Socket.io + NTP median offset |
| `game-adapter.js` | Map `new_question` payload → model UI hiện tại |
| `backend-bridge.js` | Đăng ký WS events → callbacks student/teacher |
| `keyboard-editor.js` | Editor bàn phím; API save khi `keyboard_id` + `embedded=admin` |
| `teacher.js` | Host lobby/gameplay; `embedded=admin` → join phòng từ admin |

### Mapping prototype → backend

| Prototype (demo) | Backend thật |
|---|---|
| `HTD.getRoom()` + polling | `GET /api/rooms/{pin}` + `join_room` + `players_update` |
| `FAKE_QUESTIONS[i]` | `new_question` (qua `HTDGameAdapter.mapNewQuestion`) |
| `type: 'mc'` | `answer_type: 'mc'` |
| `prompt` (text) | `content` (HTML → strip text) |
| Timer sync localStorage | `server_time` + NTP offset |
| `teacherStartGame()` local | `host_start_game` |
| Chuyển câu tự động (demo) | Host bấm **Câu tiếp theo** → `host_next_question` |

### MVP Phase 3A

- Ưu tiên **MC** chạy full luồng trước.
- `formula` / `structured`: adapter cơ bản; polish input UI ở Phase 3B.
- CSV export (3.5): dùng `/api/reports/sessions/{id}` — wire ở **3C.7** / **3D.3**.

### `HTDApi` bổ sung (keyboard)

| Method | API |
|---|---|
| `getKeyboard(id)` | `GET /api/keyboards/{id}` |
| `updateKeyboard(id, body)` | `PUT /api/keyboards/{id}` |

---

## 9. Phase 3 — Thứ tự triển khai & platform

| Phase | Nội dung | Platform |
|---|---|---|
| **3A** ✅ | Integration plumbing (API/WS) | prototype |
| **3C** ✅ | Admin UI — CRUD + embed editor/host | Laravel **web desktop** |
| **3D** ✅ | Teacher + Student với data admin | Teacher embed · Student **mobile** |
| **3B** ✅ | UI polish theo mockup | Teacher web · Student mobile |
| **4** ✅ | Test end-to-end (`phase4-test.js`) | — |

**Quy tắc:** Teacher host + keyboard editor chạy **trong** `/admin` (iframe). Học sinh luôn `/join` hoặc `/join/{pin}` (URL cũ `/app/index.html?pin=` vẫn hoạt động qua query `pin`).

Chi tiết checklist: [`local-deployment-plan.md`](../local-deployment-plan.md).

---

## 10. Tài liệu liên quan

- [`docs/DATA_MODEL.md`](DATA_MODEL.md) — Schema DB, ERD
- [`docs/SYSTEM_DESIGN.md`](SYSTEM_DESIGN.md) — Kiến trúc hệ thống
- [`docs/APP_STYLE.md`](APP_STYLE.md) — Design tokens, layout
- [`prototype/index.html`](../prototype/index.html) — UI prototype
