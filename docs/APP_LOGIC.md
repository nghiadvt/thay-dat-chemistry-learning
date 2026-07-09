# App Logic — Hóa Thầy Đạt

> Logic ứng dụng: luồng màn hình, state, scoring, bàn phím, WebSocket events.
> Nguồn gốc: [`dac-ta-ky-thuat-v4.docx.md`](../dac-ta-ky-thuat-v4.docx.md) v4.0

**Cập nhật lần cuối:** 2026-07-09 (GV đua vịt: bục + BXH khi game_ended; token phím ghép công thức)

---

## 1. Luồng học sinh (state machine)

### Trang chủ & module

```
Home (hub) ──► Chơi game (/join) ──► PIN / Quét QR ──► Profile ──► Phòng chờ ──► ...
            ├── Đọc nguyên tố (sắp ra mắt)
            ├── Cân bằng phương trình (sắp ra mắt)
            └── Ôn trắc nghiệm (sắp ra mắt)
```

**URL:** `GET /home` = trang chủ hub · `GET /join` = luồng chơi game (PIN/QR) · `GET /join/{pin}` = deep-link QR (bỏ qua home + PIN).

### Luồng chơi game (realtime)

```
Join PIN / Quét QR → Nhập tên → Avatar (skip OK) → Phòng chờ
  → [GV START] → Question → Submit → Result → Leaderboard → Question ... → Final
```

### Chi tiết từng màn hình

| Màn hình | ID | Bắt buộc | Mô tả |
|---|---|---|---|
| Trang chủ | `home` | — | Hub 4 chức năng (icon + tên). **Chơi game** → `/join`; 3 module còn lại placeholder «sắp ra mắt» |
| Join PIN / QR | `join` | PIN 6 số | Tab **Nhập mã PIN** hoặc **Quét QR** (camera live trên HTTPS; HTTP LAN dùng camera native `<input capture>`). **Bỏ qua** khi mở `/join/{pin}` |
| Hướng dẫn | `guide` | — | Text tĩnh, nút quay lại trang chủ |
| Nhập tên | `name` / `profile` | Tên ≤20 ký tự | Không cho rỗng, trim whitespace (cùng màn với avatar) |
| Avatar | `avatar` / `profile` | Tùy chọn | HTTP LAN: `<input capture>` mở camera native; HTTPS: `getUserMedia`. Bỏ qua → emoji mặc định |
| Phòng chờ | `waiting` | — | Hiển thị tên phòng, GV, danh sách người join. Chờ event `START` |
| Question MC | `question-mc` | — | Trắc nghiệm 4 đáp án A–D |
| Question Formula | `question-formula` | — | Nhập công thức qua bàn phím ảo 3 tab |
| Lock-in (trên màn Question) | — | — | Sau nộp: hiện **đáp án của mình** + **thời gian nộp** (lần cập nhật gần nhất); **không** hiện điểm; có thể đổi đáp án — thời gian hiển thị cập nhật theo lần nộp mới; **chấm điểm** vẫn theo lần nộp đầu |
| Result | `result` | — | **Sau hết giờ câu:** đúng/sai, điểm, thời gian nộp, hạng nhanh trong nhóm đúng; confetti nếu đúng |
| Leaderboard | `leaderboard` | — | Top 5, ~5 giây sau Result, animation reorder |
| Final | `final` | — | Podium top 3, nút "Chơi lại" / "Về trang chủ" (`/home`) |

### Quy tắc join phòng

- **Không yêu cầu login** — ai có link/PIN đều vào được
- PIN: 6 chữ số
- Tên hiển thị: bắt buộc, tối đa 20 ký tự
- Avatar: tùy chọn — trên HTTP LAN (điện thoại Wi‑Fi) mở camera/thư viện native qua `<input type="file" capture>`; live preview `getUserMedia` chỉ khi HTTPS/`localhost`. Bỏ qua → emoji mặc định. Ảnh gửi kèm `join_room.avatar` → Redis + `players_update` / BXH (host + HS)
- **QR / deep-link** `GET /join/{pin}`: server inject `window.HTD_JOIN_PIN` + validate PIN → màn **profile** (tên + avatar) → phòng chờ. **Không** dừng ở màn nhập PIN. QR trên host admin luôn mã hóa `{APP_URL}/join/{pin}` (không dùng ảnh mock `qr-login.png`)
- **Join muộn** (game đã `playing`): HS vẫn vào được — server gửi `game_started` + `new_question` câu hiện tại (`server_time` = `question_started_at` gốc); điểm tích lũy từ leaderboard (mới vào = 0)
- Production: WebSocket handshake + NTP sync ngay tại bước join (sau khi bấm "Vào phòng")
- Danh sách HS (host + grid phòng chờ HS): dùng `players_update` có `avatar`; không pad fake

---

## 2. Luồng giáo viên (Laravel `/admin`)

| Bước | Path | Hành động |
|---|---|---|
| **Tổng quan** | `/admin` | KPI (phòng, quiz, HS, góp ý), biểu đồ 14 ngày + trạng thái phòng + top game, phòng/báo cáo gần đây, **Xuất CSV tổng quan** |
| Soạn nội dung | `/admin/keyboards`, `/admin/games`, … | CRUD trong Blade |
| Tạo phòng | `/admin/sessions/create` | Nhập **tên phòng** + lọc game → chọn quiz → PIN + QR |
| Sửa phòng | `/admin/sessions/{id}/edit` | Đổi **tên** mọi lúc; đổi **quiz** chỉ khi `waiting` (đồng bộ Redis). PIN/QR giữ nguyên |
| Danh sách phòng | `/admin/sessions` | Thanh tìm kiếm + **Bộ lọc** (panel thu gọn, chip xóa từng điều kiện); checkbox chọn nhiều + **Xóa đã chọn**; menu **Hành động** (gồm xóa từng dòng); **Hiển thị cột** (localStorage); chân bảng hiển thị dải kết quả + phân trang |
| Vào phòng | `/admin/sessions/{id}` | Host native (Blade + `public/htd-admin/js/teacher.js`) |
| Link HS | `/join/{pin}` | Màn học sinh mobile (Laravel serve `prototype/index.html` + `<base href="/app/">`) |

**Không dùng** `/app/teacher.html` hay `/app/keyboard-editor.html` cho GV — logic đã chuyển vào `php-admin/public/admin/` + views `resources/views/admin/`.

### Danh sách admin (UI thống nhất)

CSS/JS dùng chung: `admin-list.css`, `admin-list-page.js`, `admin-data-table.js`. Partial: `list-search`, `list-filter-toggle`, `list-active-filters`, `list-table-footer`, `table-column-picker`, `row-action-menu`.

| Trang | Path | Tìm kiếm | Bộ lọc | Hiển thị cột | Phân trang |
|---|---|---|---|---|---|
| Bàn phím | `/admin/keyboards` | Tên, môn | Môn | Có | 20/trang |
| Game | `/admin/games` | Tên | Chế độ chơi | Không (card grid) | 12/trang |
| Quiz | `/admin/quizzes` | Tên | Game, chủ đề | Có | 20/trang · **CSV** xuất/nhập |
| Bộ câu hỏi | `/admin/question-bank` | Nội dung | Chủ đề, loại câu | Có | 20/trang · **CSV** xuất/nhập |
| Phòng chơi | `/admin/sessions` | Tên, PIN | Đầy đủ | Có | 20/trang |
| Báo cáo | `/admin/reports` | PIN, tên phòng | Game, ngày | Có | 20/trang |
| Góp ý | `/admin/feedback` | Nội dung, trang | Ưu tiên, trạng thái | Có | 20/trang |

Chip bộ lọc + menu **Hành động** từng dòng trên mọi bảng (trừ Game dùng nút trên card).

### CSV Quiz & Bộ câu hỏi

Nút **CSV** trên toolbar (cạnh **Hiển thị cột**):

| Thao tác | Mô tả |
|---|---|
| **Xuất CSV** | UTF-8 BOM; chỉ các cột đang hiển thị (theo column picker / localStorage); áp dụng bộ lọc hiện tại |
| **Tải file mẫu** | CSV example: header + 1–2 dòng mẫu; gồm cột đang hiển thị + cột import-only (VD: Đáp án MC) |
| **Nhập CSV** | Modal liệt kê cột bắt buộc / tùy chọn; thiếu cột bắt buộc → flash lỗi |

**Quiz — cột bắt buộc khi import:** Tên, Game, Bàn phím. Tùy chọn: Chủ đề, Lớp, Bật.

**Bộ câu hỏi — cột bắt buộc:** Loại, Nội dung. Tùy chọn: Chủ đề, Điểm, Thời gian. MC cần thêm Đáp án MC (+ index đúng); essay cần Đáp án mẫu. **Phương trình (structured) chưa hỗ trợ import CSV.**

### Bàn phím

1. `/admin/keyboards/create` — nhập tên
2. `/admin/keyboards/{id}/editor` — kéo thả layout, **Save** → `PUT /api/keyboards/{id}`
3. Không import/export JSON trong admin

### Host phòng

- Blade: `admin/sessions/_host-panel.blade.php` (markup host, asset `public/htd-admin/`)
- Init: `admin-session-init.js` → `joinExistingRoomFromAdmin()`
- Scripts: **`equation-ui.js` bắt buộc** trước `teacher.js` (render MC/structured)
- Config: `window.ADMIN_BOOT` (pin, roomName, quizName, gameName, quizId, sessionId, joinUrl, wsUrl)
- Layout: full viewport (`admin-body--session-host`) — **sidebar admin trái tự động thu gọn** khi vào trang này (`admin-sidebar.js`)
- Trong game: panel HS bên phải **ẩn**; đề bài full-width; đáp án đúng / barem **chỉ hiện sau khi hết giờ câu** (không lộ sớm)
- **Timer (backend):** đếm theo `server_time` + NTP offset; giữ `liveTimerEndsAt` trên memory (tránh race `setRoom` stale từ promise `hostStartGame`); `formatTimer` luôn `Math.ceil` → giây nguyên (`00:31`, không thập phân)
- **Hết giờ câu:** host tự gọi `host_next_question` (auto-advance). **Không** có nút «Câu tiếp theo» — chỉ chuyển câu khi hết giờ (hoặc sau leaderboard nếu bật)
- **Hành động → Hiện bảng điểm** (mặc định tắt, lưu `localStorage`): khi bật, sau hết giờ → phase leaderboard ~5s với **modal bảng điểm** (giống màn HS), rồi mới `host_next_question`. Không dùng panel phải nữa
- Kết thúc quiz: WS `game_ended` → màn **Kết thúc trò chơi** (podium top 3 + bảng điểm + Tải CSV); `room.status = ended` vẫn giữ `teacherGameView` hiển thị
- **Kết thúc trò chơi** (menu Hành động): `host_end_game` → `game_ended` → bảng điểm ngay; MySQL `status=ended`
- **Kết thúc phòng:** `host_close_room` + `POST /admin/sessions/{id}/close` → `is_active=false`, purge Redis, HS nhận `room_closed`, GV về danh sách phòng
- **GV quay lại trang host:** `syncLateJoinHost` khôi phục UI theo Redis (`playing` / `ended`)
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

## 3.2 Play mode — Đua vịt (`duck_race`)

Gắn ở `games.play_mode_id` + `mode_config`; snapshot vào `game_sessions` và Redis `room:{pin}`.

| Khía cạnh | Quiz Kahoot (`kahoot_sync`) | Đua vịt (`duck_race`) |
|---|---|---|
| Đồng bộ câu | Cả phòng | **Mỗi HS riêng** |
| Timer | Có | **Không** |
| Chấm điểm | Hết giờ, Kahoot formula | **Ngay khi submit** |
| Đúng / sai | 0 hoặc +theo tốc độ | **+3 / -5** (config) |
| Thắng | Hết câu, điểm cao | **Chạm 30 điểm** — top 3 về đích |
| Host UI | Đề bài + timer | **Đường đua** + vịt (tên, avatar, điểm) |
| HS UI | Timer + lock-in | **Điểm vịt** + feedback ngay, tự sang câu mới |

**Vị trí vịt trên đường đua:** map từ `score` nhưng **chỉ bước dương** (`max(0, score) / target_score`). Trả lời sai khi đang tiến → vịt **lùi** (animation). `score < 0` → vịt **dừng ở vạch xuất phát**; badge vẫn hiển thị điểm âm. Chạm `target_score` → vịt ở vạch đích + `finish_rank`.

**Layout host:** ảnh `background.png` `object-fit: contain` trong khung; vùng vịt căn theo `mode_config.visual.lane_bounds` + `track_bounds` — admin chỉnh **một khung** (4 cạnh: xuất phát/đích ngang, trên/dưới dọc). Kích thước vịt: `visual.duck_sprite_px`. **Mỗi HS một vịt:** cùng điểm xuất phát theo trục X (`track_bounds.start`); Y cố định theo từng người (gán lần đầu, nằm trong `lane_bounds`); tiến/lùi **chỉ đổi X** (animate `left`), không đổi Y. Sprite: thư mục `htd-admin/assets/duck-race/ducks/` — server xáo trộn không trùng cho đến khi hết pool rồi xáo lại (`duck_sprite` trên player Redis + `race_update`). Panel **Preview** mô phỏng bước đúng/sai và hiệu ứng về đích.

**Luồng HS:** `game_started` → `new_question` (riêng từng HS) → `submit_answer` → `answer_feedback` (+ `race_update` broadcast) → `new_question` tiếp (nếu chưa về đích). Chạm `target_score` → `player_finished`; đủ `min(podium_size, số HS trong phòng)` người về đích → `game_ended` (1 HS solo → về đích là kết thúc).

**Màn kết thúc (host + HS):** ảnh bục `ket-thuc-tro-choi.png` — **3 người về đích sớm nhất** (theo `finish_elapsed_s`) hiển thị **sprite vịt** (asset `duck-race/ducks/*.gif` đã crop padding) trên từng bậc (#1 giữa, #2 trái, #3 phải). **Card** tên + điểm + thời gian nằm **phía trên** vịt (pill badge, viền vàng/bạc/đồng theo hạng). **Host:** khi `game_ended` → ẩn đường đua, hiện `#teacherFinalPhase` + modal bục + BXH; bỏ qua `race_update` sau khi kết thúc. **HS:** bục + BXH trên cùng màn final.

**Xếp hạng cuối:** (1) thứ tự về đích `finish_rank` 1–3 — **cùng `finish_elapsed_s` (4 chữ số thập phân) = đồng hạng**; (2) còn lại theo `score` tại kết thúc (có thể âm).

**Thời gian về đích:** server lưu `race_started_hr` (monotonic) khi START; mỗi HS chạm `target_score` → `finish_elapsed_s` = giây từ lúc START (VD `20.0123`). Hiển thị trên host (badge vịt, podium), HS (feedback về đích), bảng kết thúc GV.

**Assets:** `htd-admin/assets/duck-race/ducks/*.gif` (pool sprite), `background.png`. Ảnh đại diện game: `htd-admin/assets/games/dua-vit.png`. Prototype HS: `prototype/assets/duck-race/`.

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

> **Keyboard editor Test:** `formulaAppendToken` + `formulaAppendChemString` trong `keyboard-editor.js` / `equation-ui.js`.
> **Runtime học sinh:** `equation-ui.js` (`FormulaController` + `blankTokens`) dùng cùng quy tắc `smart_context` và tách phím ghép như Test editor.

---

## 5. WebSocket events (production — đồng bộ `local-deployment-plan.md`)

| Event | Direction | Payload | Mô tả |
|---|---|---|---|
| `join_room` | C→S | `{ pin, name, avatar? }` | HS vào phòng; `avatar` data URL tùy chọn |
| `ntp_ping` / `ntp_pong` | both | `{ t0 }` / `{ t0, t1, t2 }` | Đồng bộ thời gian |
| `game_started` | S→C | `{}` | GV bắt đầu |
| `new_question` | S→C | xem payload bên dưới | Câu hỏi mới |
| `submit_answer` | C→S | `{ question_id, answer, hybrid_timestamp }` | Nộp / cập nhật đáp án (có thể đổi cho đến hết giờ) |
| `submit_answer` ack | S→C | `{ locked, elapsed_seconds, answer_display, can_change }` | Lock-in — **không** chấm điểm; `elapsed_seconds` = lần nộp **gần nhất** (đổi đáp án → cập nhật thời gian hiển thị) |
| `question_result` | S→C | xem §5.1 | **Khi hết câu** (finalize); kèm điểm + thời gian nộp |
| `leaderboard_update` | S→C | `{ top5: [...] }` | Sau finalize câu (~4s trước khi HS xem BXH) |
| `game_ended` | S→C | `{ final_leaderboard }` | Kết thúc (kèm `avatar` từng người) |
| `submit_count_update` | S→C (host) | `{ submitted, total }` | Số người đã nộp |
| `players_update` | S→C | `{ players: [{ name, connected, score, avatar }] }` | Danh sách HS trong phòng |

### 5.1 Payload `question_result` (sau hết giờ câu)

Gửi **riêng từng HS** khi host `host_next_question` finalize câu hiện tại. Thời gian chấm = `first_submit_at` (lần nộp đầu). Xếp hạng câu: đúng trước (nhanh → chậm), sai sau.

```json
{
  "correct": true,
  "correct_answer": "C. CaO",
  "score_earned": 900,
  "rank": 2,
  "total_score": 2400,
  "elapsed_seconds": 5,
  "my_answer": "C. CaO",
  "question_rank_correct": 1,
  "question_total": 12,
  "fastest_correct": { "name": "Lan", "elapsed_seconds": 3 },
  "explanation": "<p>...</p>"
}
```

- `my_answer` / `elapsed_seconds`: chỉ của HS nhận event — **không** broadcast đáp án HS khác trong lúc câu đang mở
- `question_rank_correct`: hạng trong nhóm trả lời **đúng** (null nếu sai)
- `fastest_correct`: HS đúng nhanh nhất câu này (tên + giây)

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
  screen: 'home',
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

**Serve UI học sinh:** `http://localhost:38480/home` (trang chủ) · `/join` (chơi game) · `/join/{PIN}` (deep-link QR) — cùng port với admin/API; static assets (`css/`, `js/`) vẫn tại `/app/` (Docker mount `prototype/`).

**Test trên điện thoại (cùng Wi‑Fi):** set trong root `.env`:
`APP_URL=http://<IP-LAN>:38480` và `WS_PUBLIC_URL=http://<IP-LAN>:38581` (vd. `192.168.1.9`), rồi recreate `php-admin`. Join/QR/link copy từ admin đều dùng `APP_URL` (không phụ thuộc việc GV mở admin bằng localhost). Phone mở `/join/{PIN}` hoặc quét QR. Firewall Windows: inbound TCP `38480` + `38581`. Production: chỉ đổi `APP_URL` / `WS_PUBLIC_URL` sang domain HTTPS — cùng code path, không nhánh riêng.

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
| Chuyển câu tự động (demo) | Host đếm hết giờ → tự `host_next_question` |
| Hiện bảng điểm panel phải | Tùy chọn **Hiện bảng điểm** → modal ~5s sau hết giờ câu |

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

**Quy tắc:** Teacher host + keyboard editor chạy **trong** `/admin` (iframe). Học sinh: trang chủ `/home`; chơi game `/join` hoặc deep-link `/join/{pin}` (URL cũ `/app/index.html?pin=` vẫn hoạt động qua query `pin`).

Chi tiết checklist: [`local-deployment-plan.md`](../local-deployment-plan.md).

---

## 10. Tài liệu liên quan

- [`docs/DATA_MODEL.md`](DATA_MODEL.md) — Schema DB, ERD
- [`docs/SYSTEM_DESIGN.md`](SYSTEM_DESIGN.md) — Kiến trúc hệ thống
- [`docs/APP_STYLE.md`](APP_STYLE.md) — Design tokens, layout
- [`prototype/index.html`](../prototype/index.html) — UI prototype
