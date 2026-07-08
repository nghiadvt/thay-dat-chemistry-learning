# API Contracts — Hóa Thầy Đạt

> WebSocket events, PHP endpoints, Redis keys — single source of truth.
> WS events skeleton mở rộng ở Phase 2; Phase 1 ghi đầy đủ PHP admin endpoints.

**Cập nhật lần cuối:** 2026-07-09 (lọc multi-tag: `tag_match` AND/OR + hiển thị đủ tên chủ đề)

---

## 1. Quy ước response PHP

Mọi endpoint JSON trả:

```json
{
  "success": true,
  "data": { },
  "error": null
}
```

Lỗi:

```json
{
  "success": false,
  "data": null,
  "error": "Mô tả lỗi"
}
```

**Auth:** session cookie + CSRF (`@csrf` form hoặc header `X-XSRF-TOKEN` từ cookie `XSRF-TOKEN`).

**Headless / script client:** `POST /login` phải gửi kèm **session cookie** lấy từ `GET /login` (nếu không → 419 CSRF). Sau login thành công (302), dùng cookie mới + `X-XSRF-TOKEN` cho mọi `/api/*`. Script mẫu: `ws-server/scripts/ws-smoke-test.js`, `ws-server/scripts/phase4-test.js`.

**Base URL local:** `http://localhost:38480`

---

## 2. Auth (web)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/login` | Form đăng nhập |
| POST | `/login` | `{ email, password }` → session |
| POST | `/logout` | Hủy session (cần auth) |
| GET | `/dashboard` | Redirect → `/admin` |
| GET | `/admin` | Dashboard admin (stats, quick links) |

### 2.2 Admin web UI (session auth, Blade)

Tất cả công cụ GV nằm trong `/admin` — **không** dùng iframe hay `/app/teacher.html`, `/app/keyboard-editor.html`.

| Method | Path | Mô tả |
|---|---|---|
| GET/POST | `/admin/keyboards` | Tạo tên bàn phím → redirect editor |
| GET | `/admin/keyboards/{id}/editor` | Trình chỉnh sửa layout (Blade + `public/htd-admin/js/keyboard-editor.js`, `keyboard-editor.css`). Thu phóng: `#kbeZoomSelect` (`50%`/`75%`/`100%`/`150%`). Nút **Test** mô phỏng nhập công thức theo `config.smart_context` — xem §3.1 |
| GET/POST | `/admin/games` | CRUD game |
| GET/POST | `/admin/quizzes` | CRUD quiz (+ `show` xem câu hỏi) |
| GET/POST | `/admin/quizzes/{quiz}/questions` | CRUD câu hỏi nested |
| POST | `/admin/quizzes/{quiz}/questions/from-bank` | JSON `{ bank_ids: [1,2,3] }` — copy từ bộ câu hỏi |
| PATCH | `/admin/quizzes/{quiz}/questions/reorder` | JSON `{ order: [qid, ...] }` — cập nhật `sort_order` |
| PATCH | `/admin/quizzes/{quiz}/questions/bulk` | JSON `{ question_ids, time_limit_seconds?, points?, is_active?, tag_ids?, action?: "delete" }` |
| PATCH | `/admin/quizzes/{quiz}/questions/{question}/tags` | JSON `{ tag_ids: [1,2] }` — đổi chủ đề nhanh (sync qua `source_bank_question_id`) |
| GET/POST | `/admin/question-bank` | CRUD bộ câu hỏi (tag multi-select) |
| PATCH | `/admin/question-bank/bulk-tags` | JSON `{ item_ids: [1,2], tag_ids: [3] }` — đổi chủ đề hàng loạt |
| PATCH | `/admin/question-bank/{id}/tags` | JSON `{ tag_ids: [1,2] }` — đổi chủ đề nhanh trên danh sách bộ |
| GET | `/admin/question-bank/search` | JSON lọc câu cho modal; query `tag_ids[]`, `tag_match=and\|or` (mặc định `and`), `tag_none=1`, `answer_type`, `q`, `quiz_id` (tương thích `tag_id` cũ) |
| GET | `/admin/tags` | JSON danh sách tag `{ id, name, color, text_color }` |
| POST | `/admin/tags` | JSON `{ name, color }` — tạo chủ đề mới (màu `#RRGGBB`) |
| PATCH | `/admin/tags/{id}` | JSON `{ name, color }` — sửa chủ đề |
| GET/POST | `/admin/sessions` | Tạo phòng |
| GET | `/admin/sessions/{id}/edit` | Sửa phòng: **tên** luôn; **quiz** chỉ khi `status=waiting` (cập nhật Redis `game_id`/`quiz_id`). PIN + QR không đổi |
| PUT | `/admin/sessions/{id}` | Cập nhật sau form edit |
| GET | `/admin/sessions/{id}` | Điều khiển phòng (host Blade + WS) |
| POST | `/admin/sessions/{id}/reset` | **Chơi lại** phòng `ended` → `waiting`, xóa Redis state, giữ kết quả cũ trong DB |
| GET | `/admin/reports` | Lịch sử session `ended` |
| GET | `/admin/reports/{id}` | Chi tiết điểm |
| GET | `/admin/reports/{id}/export` | Tải CSV UTF-8 |

| GET | `/home` | Trang chủ học sinh — hub 4 chức năng (inject `HTD_ENTRY_SCREEN=home`) |
| GET | `/join` | Chơi game — nhập PIN / quét QR camera (inject `HTD_ENTRY_SCREEN=join`) |
| GET | `/join/{pin}` | Deep-link QR: inject `HTD_JOIN_PIN` → validate → tên/avatar; **không** dừng ở nhập PIN |
| GET | `/app/index.html` | Legacy — redirect → `/home`, `/join/{pin}` nếu có `?pin=` |

Học sinh: trang chủ `http://192.168.x.x:38480/home` · chơi game `/join` · deep-link `/join/123456` (quét QR bằng app Camera điện thoại). Tab **Quét QR** trong app: live camera (HTTPS) hoặc chụp ảnh QR (HTTP LAN).

**QR trên trang host:** `SessionQrService::displayQrUrl()` — PNG lưu storage nếu tạo được; không được thì CDN `qrserver` với `data={APP_URL}/join/{pin}`. **Không** fallback `htd-admin/assets/qr-login.png` (ảnh mock). Dưới QR hiển thị text `joinUrl` để đối chiếu.

**Port:** Học sinh và admin **cùng** `38480` (PHP/Nginx). WebSocket realtime **riêng** `38581`.

Tài khoản seed: `teacher@hoadat.local` / `password123`

---

## 2.1 Public room lookup (không cần auth)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/rooms/{pin}` | Kiểm tra PIN 6 số còn hiệu lực trên Redis |

Response mẫu:

```json
{
  "success": true,
  "data": {
    "pin": "123456",
    "status": "waiting",
    "game_id": 1,
    "game_name": "Ôn tập học kỳ 1",
    "session_id": 12
  },
  "error": null
}
```

Lỗi 404: PIN không tồn tại hoặc đã hết TTL.

---

## 3. Keyboards

| Method | Path | Body / ghi chú |
|---|---|---|
| GET | `/api/keyboards` | Danh sách |
| POST | `/api/keyboards` | `{ name, subject?, config }` — validate theo [`KEYBOARD_SCHEMA.md`](KEYBOARD_SCHEMA.md) |
| GET | `/api/keyboards/{id}` | Chi tiết |
| PUT/PATCH | `/api/keyboards/{id}` | Cập nhật |
| DELETE | `/api/keyboards/{id}` | RESTRICT nếu quiz đang dùng |

**POST body mẫu:**

```json
{
  "name": "Bàn phím Hóa vô cơ",
  "subject": "chemistry",
  "config": {
    "schema_version": 1,
    "defaults": { "keySize": "M", "fontSize": "M", "textColor": "#000000", "background": "#FFFFFF", "border": "#D0D0D0" },
    "rows": [],
    "smart_context": { "after_element": "subscript", "after_plus": "coefficient" }
  }
}
```

### 3.1 Keyboard editor — Test overlay (`smart_context`)

Admin editor (`keyboard-editor.js`) có overlay **Test** để GV thử bàn phím trước khi gán quiz. Không có endpoint riêng — chỉ client-side.

| Hành vi | Chi tiết |
|---|---|
| Nguồn quy tắc | `config.smart_context` (merge default server nếu thiếu) |
| Phím số `0`–`9` | Tự thành **hệ số** (số to) hoặc **chỉ số dưới** (`<sub>`) theo ngữ cảnh — không cần phím subscript riêng |
| Ngữ cảnh hệ số | Đầu công thức, sau `+`, nối hệ số đa chữ số |
| Ngữ cảnh chỉ số | Sau nguyên tố (`H`, `Cl`, …), sau `)`, nối chỉ số đa chữ số |
| Backspace | Xóa 1 **token** (nguyên tố 2 ký tự `Cl` = 1 token) |
| Hiển thị | `#kbeTestOutput` — HTML hóa học (font STIX Two Text, `.kbe-test-output sub`) |
| Serialize | `data-serialized` trên output — plain ASCII (vd. `2CO2`) — **cùng format** `submit_answer` cho `answer_type: formula` |
| Layout | `.kbe-test-wrap`: cột (output trên, phone dưới). `max-height ≤ 820px` → **một hàng**, `align-items: flex-start` (output căn mép trên với device frame). **Không scroll** — phone `fitOverlayDevice()` scale vừa viewport |
| Interaction | Overlay gọi `renderPhoneKb(..., { editable: false })` — **không** gắn selectKey/row menu; chỉ handler nhập formula trên `#kbeTestPhoneKb` |
| Preview ảnh list | Save / lần đầu mở: clone `#kbePhoneKb` + bake RGB inline → `html2canvas` với `onclone` remove stylesheet (tránh `oklch`) → `POST /api/keyboards/{id}/preview` |

Token model đầy đủ: [`APP_LOGIC.md`](APP_LOGIC.md) §4.2–4.3, schema: [`KEYBOARD_SCHEMA.md`](KEYBOARD_SCHEMA.md) §`smart_context`.

---

## 4. Games

| Method | Path | Body |
|---|---|---|
| GET | `/api/games` | — |
| POST | `/api/games` | `{ name, description? }` |
| GET | `/api/games/{id}` | Kèm quizzes |
| PUT/PATCH | `/api/games/{id}` | `{ name?, description? }` |
| DELETE | `/api/games/{id}` | RESTRICT nếu còn quiz |

---

## 5. Quizzes

| Method | Path | Body / query |
|---|---|---|
| GET | `/api/quizzes` | Query: `?game_id=` |
| POST | `/api/quizzes` | `{ game_id, keyboard_id, name, subject?, grade?, sort_order?, is_active?, show_explanation?, shuffle_options? }` |
| GET | `/api/quizzes/{id}` | Kèm questions |
| PUT/PATCH | `/api/quizzes/{id}` | Cập nhật |
| DELETE | `/api/quizzes/{id}` | Cascade questions |

---

## 6. Questions (nested)

| Method | Path | Body |
|---|---|---|
| GET | `/api/quizzes/{quiz}/questions` | — |
| POST | `/api/quizzes/{quiz}/questions` | Xem mẫu bên dưới |
| GET | `/api/quizzes/{quiz}/questions/{id}` | — |
| PUT/PATCH | `/api/quizzes/{quiz}/questions/{id}` | — |
| DELETE | `/api/quizzes/{quiz}/questions/{id}` | — |

**MC mẫu:**

```json
{
  "content": "<p>Câu hỏi?</p>",
  "answer_type": "mc",
  "options": ["A", "B", "C", "D"],
  "correct_index": 0,
  "time_limit_seconds": 30,
  "sort_order": 1
}
```

**Formula mẫu:**

```json
{
  "content": "<p>Công thức nước?</p>",
  "answer_type": "formula",
  "correct_answer_normalized": "H2O"
}
```

---

## 7. Game sessions

| Method | Path | Body | Response |
|---|---|---|---|
| POST | `/api/game-sessions` | `{ name?, quiz_id }` hoặc legacy `{ game_id }` | `{ session, pin }` + Redis `room:{pin}` |
| GET | `/api/game-sessions/{id}` | — | Chi tiết session (kèm quiz) |
| POST | `/api/game-sessions/{id}/reset` | — | Phòng `ended` + `is_active` → reset `waiting`, xóa Redis `room:{pin}*`, tạo lại phòng chờ; **không** xóa `game_results` / `session_answers` lần trước |
| PATCH | `/admin/sessions/{id}/active` | — | Bật/tắt phòng; tắt → xóa Redis room |

**QR join:** Join URL luôn lấy từ `APP_URL` (`{APP_URL}/join/{pin}`) — **không** phụ thuộc host của request admin. Host UI gọi `displayQrUrl()` (PNG đã lưu hoặc CDN cùng `data`); **không** hiển thị mock. Khi `POST /admin/sessions` (hoặc API tạo session) thành công, Laravel gọi `SessionQrService` → PNG `storage/app/public/sessions/{pin}.png` + sidecar `{pin}.joinurl`. URL public ảnh: `/storage/sessions/{pin}.png` (accessor `qr_url`, kèm `?v=` mtime). Phòng thiếu QR hoặc `APP_URL` đổi → tạo lại khi `GET /admin/sessions/{id}`.

| Môi trường | `APP_URL` (ví dụ) | `WS_PUBLIC_URL` |
|---|---|---|
| Local PC | `http://localhost:38480` | `http://localhost:38581` |
| Test điện thoại cùng Wi‑Fi | `http://192.168.x.x:38480` | `http://192.168.x.x:38581` |
| Production | `https://your-domain.tld` | Thường same-origin với site (reverse proxy) hoặc URL WS công khai |

Đổi `APP_URL` / `WS_PUBLIC_URL` trong root `.env` → `docker compose up -d php-admin` (recreate) → mở lại trang phòng để QR regenerate.

**Redis sau POST thành công:**

```
HGETALL room:482910
1) "status"
2) "waiting"
3) "game_id"
4) "1"
5) "quiz_id"
6) "3"
TTL room:482910   → ~7200
```

---

## 8. Reports (score management)

| Method | Path | Query |
|---|---|---|
| GET | `/api/reports/sessions` | `game_id?`, `date_from?`, `date_to?`, `per_page?` |
| GET | `/api/reports/sessions/{id}` | Chi tiết + `game_results` + `session_answers` |
| GET | `/api/reports/students/aggregate` | `student_name` (required), `game_id?` |

---

## 9. WebSocket events

**WS URL local:** `http://localhost:38581`  
**Health:** `GET http://localhost:38581/health`

### 9.1 Client → Server

| Event | Payload | Ghi chú |
|---|---|---|
| `join_room` | `{ pin, name, is_host?, avatar? }` | `pin` 6 số; `name` ≤20 ký tự; host set `is_host: true`; HS tùy chọn `avatar` = data URL JPEG/PNG/WebP (≤~120KB) |
| `ntp_ping` | `{ t0 }` | Client timestamp (ms); lặp 3 lần, lấy median offset |
| `host_start_game` | `{}` | **Host only** — bắt đầu game + câu đầu tiên |
| `host_finalize_question` | `{}` | **Host only** — chốt câu, chấm điểm, gửi `question_result` |
| `host_next_question` | `{}` | **Host only** — câu tiếp theo hoặc kết thúc nếu hết |
| `host_end_game` | `{}` | **Host only** — kết thúc sớm, lưu `game_results` |
| `submit_answer` | `{ question_id, answer, hybrid_timestamp }` | **Student only** — nộp / đổi đáp án trong lúc câu mở |

**Ack `submit_answer` thành công:** `{ success: true, data: { locked, elapsed_seconds, answer_display, can_change } }` — **không** chấm điểm. `elapsed_seconds` = giây từ `question_started_at` đến `last_submit_at` (cập nhật khi HS đổi đáp án). Chấm điểm khi finalize vẫn dùng `first_submit_at`.

**Lỗi `submit_answer` (ack `{ success: false, error }` hoặc `room_error`):**

| Điều kiện | `error` mẫu |
|---|---|
| `\|hybrid_timestamp - server_now\| > 500ms` | `Đồng hồ lệch {N}ms (cho phép tối đa 500ms). Hãy đồng bộ NTP lại.` |

Redis: `room:{PIN}:answer:{question_id}:{student_name}` lưu `{ answer, first_submit_at, last_submit_at }`. Set `submitted:<PIN>:<question_id>` đếm HS đã nộp ít nhất một lần.

**`answer` theo `answer_type`:**

| `answer_type` | `answer` mẫu |
|---|---|
| `mc` | `0` hoặc `{ "index": 0 }` |
| `formula` | `"H2O"` hoặc `{ "text": "H2O" }` — plain text ASCII (số thường, không Unicode subscript). Client serialize từ token bàn phím theo `keyboard_config.smart_context` (xem §3.1) |
| `structured` | `{ "coef": { "c0": "2" }, "blank": { "b0": "O2" } }` |

**Host events** dùng cho Phase 3.4; chưa có trong bảng plan gốc nhưng là contract chính thức từ Phase 2.

### 9.2 Server → Client

| Event | Payload | Ghi chú |
|---|---|---|
| `room_joined` | `{ pin, name, player_token, reconnected, score, is_host, room_status, question_index? }` | Sau `join_room` thành công. `question_index` (0-based) chỉ khi `room_status === 'playing'`. Join muộn: server tiếp theo emit `game_started` + `new_question` câu hiện tại |
| `room_error` | `{ message }` | Lỗi join / gameplay |
| `players_update` | `{ players: [{ name, connected, score, avatar }] }` | Cập nhật danh sách HS; `avatar` = data URL hoặc `null` |
| `ntp_pong` | `{ t0, t1, t2 }` | Phản hồi NTP |
| `game_started` | `{}` | GV bấm Start |
| `new_question` | xem §9.3 | Câu hỏi mới |
| `question_result` | xem §9.3.1 | **Khi hết câu** (finalize); gửi riêng từng HS |
| `leaderboard_update` | `{ top5: [{ name, score, delta, avatar }] }` | Broadcast phòng; `avatar` từ Redis player |
| `submit_count_update` | `{ submitted, total }` | **Chỉ host** |
| `game_ended` | `{ final_leaderboard: [{ name, score, rank, player_token?, avatar }] }` | Kết thúc game |

Callback ack (tùy chọn): client truyền function làm tham số cuối → `{ success, data?, error? }`.

### 9.3 Payload `new_question`

```json
{
  "quiz_id": 1,
  "question_id": 42,
  "content": "<p>Đề bài...</p>",
  "answer_type": "mc",
  "options": ["A", "B", "C", "D"],
  "template": null,
  "input_mode": null,
  "keyboard_config": { "schema_version": 1, "rows": [] },
  "time_limit": 30,
  "server_time": 1710000000000
}
```

**Chỉ host** (`is_host: true`) nhận thêm barem — học sinh **không** nhận các field sau:

```json
{
  "correct_index": 0,
  "correct_answer_normalized": "CH4",
  "correct_answer": { "blank": { "b0": "NH4Cl" } },
  "explanation": "<p>Giải thích...</p>"
}
```

- `server_time` = thời điểm server bắt đầu đếm giờ câu này (ms epoch)
- `options` / `template` / `input_mode`: `null` nếu không dùng
- `template`: chỉ gửi HS khi `answer_type === structured`
- `keyboard_config`: từ `quizzes.keyboard_id` → `keyboards.config`
- **Shuffle** (`quizzes.shuffle_options`): broadcast riêng từng HS — mỗi client nhận `options` thứ tự khác nhau; host nhận thứ tự gốc. Redis key map: `room:{PIN}:option_order:{question_id}:{student_name}` → JSON mảng index gốc theo vị trí hiển thị

### 9.3.1 Payload `question_result` (sau finalize câu)

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

Thời gian chấm = `first_submit_at`. Xếp hạng câu: đúng (nhanh → chậm) trước, sai sau.

### 9.4 NTP & hybrid timestamp

1. Client gửi `ntp_ping { t0: Date.now() }` **3 lần**
2. Mỗi lần nhận `ntp_pong { t0, t1, t2 }`, tính `offset = ((t1 - t0) + (t2 - t0)) / 2`
3. Lấy **median** của 3 offset
4. Khi submit: `hybrid_timestamp = Date.now() + offset`
5. Server reject nếu `|hybrid_timestamp - server_now| > 500ms` — message: `Đồng hồ lệch …ms (cho phép tối đa 500ms). Hãy đồng bộ NTP lại.`

### 9.5 Scoring

```
score = round(1000 × (time_remaining / time_limit))   // chỉ khi đúng
streak ≥ 3 đúng liên tiếp → +50 từ câu thứ 4 trở đi
```

Ví dụ: câu 30s, submit đúng sau 3s → `1000 × (27/30) ≈ 900` điểm.

### 9.6 Luồng game (host)

```
POST /api/game-sessions (Laravel) → Redis room:{pin}
  → host join_room { is_host: true }
  → students join_room
  → host_start_game → game_started + new_question
  → students submit_answer → ack lock-in (đáp án + thời gian, chưa điểm)
  → hết giờ câu → finalize → question_result (từng HS) + leaderboard_update
  → hết giờ câu (host client đếm `server_time`+NTP) → [tuỳ chọn modal bảng điểm ~5s] → host_next_question → new_question (lặp)
     *(tự động khi hết giờ câu / sau leaderboard)*
  → host_end_game hoặc hết câu → game_ended + lưu MySQL
```

**Persist khi `game_ended` (ws-server `endGame` → `db.saveGameResults`):**

1. `game_sessions.status` → `ended`, `ended_at` = now
2. `session_answers` — đã ghi từng câu khi `submit_answer`
3. `game_results` — xóa bản ghi cũ theo `session_id`, insert lại từ leaderboard Redis:
   - `(session_id, student_name, player_token, score, rank)` — cột `rank` là reserved word MySQL, INSERT dùng backtick `` `rank` ``
   - `rank` = 1-based theo thứ tự điểm giảm dần

Sau khi kết thúc, client/teacher có thể đọc `GET /api/reports/sessions/{id}` (`status: ended`, kèm `results`, `answers`).

---

## 10. Redis keys

| Key | Type | TTL | Meaning |
|---|---|---|---|
| `room:<PIN>` | Hash | 2h | `status`, `game_id`, `quiz_id`, `current_quiz_id`, `current_question_id` |
| `room:<PIN>:players` | Hash | 2h | Students in room — JSON `{ name, player_token, connected, score fields, avatar? }` |
| `leaderboard:<PIN>` | ZSET | 2h | Total scores |
| `submitted:<PIN>:<question_id>` | Set | 2h | Double-submit guard |
| `room:<PIN>:option_order:<question_id>:<student_name>` | String (JSON) | 2h | Map index hiển thị → index gốc khi `shuffle_options` bật |

| `room:<PIN>:plan` | String (JSON) | 2h | Game plan cache (quizzes + questions) — nội bộ ws-server |

---

## 12. Automated tests (`ws-server`)

**Yêu cầu:** `docker compose up`, DB seeded (`teacher@hoadat.local` / `password123`). Cần **Node ≥18** (`fetch`, `getSetCookie`). Host Node cũ: chạy trong container Node 20.

```bash
cd ws-server
npm install          # cài deps gồm socket.io-client (cho scripts test)
npm run test:smoke   # Phase 2 smoke
npm run test:phase4  # Phase 4: 4.4 → 4.3 → 4.1
npm run test:phase4:load   # thêm 4.2 load 10 phòng × 50 HS
```

Hoặc từ repo root (không cần Node local):

```bash
docker run --rm --network host -v "$PWD/ws-server:/app" -w /app node:20-alpine \
  sh -c "npm install --omit=dev -q && npm run test:phase4"
```

### Biến môi trường

| Biến | Mặc định | Mô tả |
|---|---|---|
| `PHP_URL` | `http://localhost:38480` | Laravel admin |
| `WS_URL` | `http://localhost:38581` | Socket.io |
| `GAME_ID` | `1` | Game seed dùng trong test |
| `LOAD_ROOMS` | `10` | Số phòng (4.2) |
| `LOAD_STUDENTS` | `50` | HS mỗi phòng (4.2) |

### `scripts/ws-smoke-test.js` (Phase 2)

1. Login Laravel → `POST /api/game-sessions` → PIN
2. Host + 2 HS `join_room`
3. `host_start_game` → `new_question` (đăng ký listener **trước** khi host start)
4. 1× `submit_answer` MC → `question_result`, `submit_count_update`
5. Double-submit bị chặn; `hybrid_timestamp` lệch >500ms bị chặn

### `scripts/phase4-test.js` (Phase 4)

| Mục | Nội dung verify |
|---|---|
| **4.4** | Double-submit + clock skew (như smoke) |
| **4.3** | HS disconnect socket → `join_room` cùng tên → `reconnected: true`, `score` không giảm |
| **4.1** | Host + 3 HS, chơi hết câu seed (`GET /api/quizzes?game_id=` + questions), `host_next_question` đến `game_ended`, `GET /api/reports/sessions/{id}` → `status: ended` |
| **4.2** (`--load`) | `LOAD_ROOMS` × `LOAD_STUDENTS` join + submit burst; báo dropped joins và p99 latency |

**Lưu ý khi viết client test:** WS events (`game_started`, `new_question`, `game_ended`) có thể emit trong cùng tick với ack `host_start_game` / `host_next_question` — phải `socket.once(...)` **trước** khi host emit.

---

## 11. Tài liệu liên quan

- [`docs/DATA_MODEL.md`](DATA_MODEL.md)
- [`docs/KEYBOARD_SCHEMA.md`](KEYBOARD_SCHEMA.md)
- [`docs/APP_LOGIC.md`](APP_LOGIC.md)
