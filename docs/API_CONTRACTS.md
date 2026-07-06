# API Contracts — Hóa Thầy Đạt

> WebSocket events, PHP endpoints, Redis keys — single source of truth.
> WS events skeleton mở rộng ở Phase 2; Phase 1 ghi đầy đủ PHP admin endpoints.

**Cập nhật lần cuối:** 2026-07-06 (Admin native — Blade + `public/htd-admin/`)

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
| GET | `/admin/keyboards/{id}/editor` | Trình chỉnh sửa layout (Blade + `public/htd-admin/js/keyboard-editor.js`) |
| GET/POST | `/admin/games` | CRUD game |
| GET/POST | `/admin/quizzes` | CRUD quiz (+ `show` xem câu hỏi) |
| GET/POST | `/admin/quizzes/{quiz}/questions` | CRUD câu hỏi nested |
| GET/POST | `/admin/sessions` | Tạo phòng |
| GET | `/admin/sessions/{id}` | Điều khiển phòng (host Blade + WS) |
| GET | `/admin/reports` | Lịch sử session `ended` |
| GET | `/admin/reports/{id}` | Chi tiết điểm |
| GET | `/admin/reports/{id}/export` | Tải CSV UTF-8 |

| GET | `/join/{pin}` | Redirect học sinh → `/app/index.html?pin=` |

Học sinh tham gia: `http://localhost:38480/join/123456` (hoặc nhập PIN trên `/app/`).

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
| POST | `/api/quizzes` | `{ game_id, keyboard_id, name, subject?, grade?, sort_order?, is_active? }` |
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
| POST | `/api/game-sessions` | `{ game_id }` | `{ session, pin }` + Redis `room:{pin}` |
| GET | `/api/game-sessions/{id}` | — | Chi tiết session |

**Redis sau POST thành công:**

```
HGETALL room:482910
1) "status"
2) "waiting"
3) "game_id"
4) "1"
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
| `join_room` | `{ pin, name, is_host? }` | `pin` 6 số; `name` ≤20 ký tự; host set `is_host: true` |
| `ntp_ping` | `{ t0 }` | Client timestamp (ms); lặp 3 lần, lấy median offset |
| `host_start_game` | `{}` | **Host only** — bắt đầu game + câu đầu tiên |
| `host_next_question` | `{}` | **Host only** — câu tiếp theo hoặc kết thúc nếu hết |
| `host_end_game` | `{}` | **Host only** — kết thúc sớm, lưu `game_results` |
| `submit_answer` | `{ question_id, answer, hybrid_timestamp }` | **Student only** |

**Lỗi `submit_answer` (ack `{ success: false, error }` hoặc `room_error`):**

| Điều kiện | `error` mẫu |
|---|---|
| Nộp lại cùng câu | `Bạn đã nộp câu này rồi.` |
| `\|hybrid_timestamp - server_now\| > 500ms` | `Đồng hồ lệch {N}ms (cho phép tối đa 500ms). Hãy đồng bộ NTP lại.` |

Guard double-submit: Redis set `submitted:<PIN>:<question_id>` (xem §10).

**`answer` theo `answer_type`:**

| `answer_type` | `answer` mẫu |
|---|---|
| `mc` | `0` hoặc `{ "index": 0 }` |
| `formula` | `"H2O"` hoặc `{ "text": "H2O" }` |
| `structured` | `{ "coef": { "c0": "2" }, "blank": { "b0": "O2" } }` |

**Host events** dùng cho Phase 3.4; chưa có trong bảng plan gốc nhưng là contract chính thức từ Phase 2.

### 9.2 Server → Client

| Event | Payload | Ghi chú |
|---|---|---|
| `room_joined` | `{ pin, name, player_token, reconnected, score, is_host, room_status }` | Sau `join_room` thành công |
| `room_error` | `{ message }` | Lỗi join / gameplay |
| `players_update` | `{ players: [{ name, connected, score }] }` | Cập nhật danh sách HS |
| `ntp_pong` | `{ t0, t1, t2 }` | Phản hồi NTP |
| `game_started` | `{}` | GV bấm Start |
| `new_question` | xem §9.3 | Câu hỏi mới |
| `question_result` | `{ correct, correct_answer, score_earned, rank, total_score }` | Chỉ client vừa submit |
| `leaderboard_update` | `{ top5: [{ name, score, delta }] }` | Broadcast phòng |
| `submit_count_update` | `{ submitted, total }` | **Chỉ host** |
| `game_ended` | `{ final_leaderboard: [{ name, score, rank, player_token? }] }` | Kết thúc game |

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

- `server_time` = thời điểm server bắt đầu đếm giờ câu này (ms epoch)
- `options` / `template`: `null` nếu không dùng
- `keyboard_config`: từ `quizzes.keyboard_id` → `keyboards.config`

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
  → students submit_answer → question_result + leaderboard_update
  → host_next_question → new_question (lặp)
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
| `room:<PIN>` | Hash | 2h | `status`, `game_id`, `current_quiz_id`, `current_question_id` |
| `room:<PIN>:players` | Hash | 2h | Students in room |
| `leaderboard:<PIN>` | ZSET | 2h | Total scores |
| `submitted:<PIN>:<question_id>` | Set | 2h | Double-submit guard |

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
