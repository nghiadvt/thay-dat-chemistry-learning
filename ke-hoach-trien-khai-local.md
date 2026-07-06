# KẾ HOẠCH TRIỂN KHAI SERVER LOCAL — Website Đố Vui Hóa Học Real-time

> UI (HTML/CSS/JS) đã có nhưng **chưa hoàn chỉnh 100%** — không cần chờ UI xong mới bắt đầu. Thứ tự khuyến nghị vẫn là: xây xong hạ tầng + logic backend + DB trước (Phase 0-2), vì khi đó API/event/schema đã rõ ràng, phần hoàn thiện UI ở Phase 3 sẽ có "khung" cụ thể để bám vào (biết chính xác endpoint nào, event nào, payload gì) thay vì vừa đoán vừa code cả 2 phía cùng lúc.
> Ở Phase 3, nếu 1 màn hình UI chưa có sẵn hoặc chưa đầy đủ, AI cứ tạo mới/hoàn thiện thêm theo đúng cấu trúc file đã có (không cần hỏi lại) — chỉ hỏi nếu không rõ nên đặt logic ở đâu.
> **Cập nhật Phase 3:** Thứ tự mới — 3A (integration, xong) → **3C Admin UI web đầy đủ** → 3D Teacher+Student → 3B Polish → Phase 4 test. **Admin + Teacher = web desktop; Học sinh = mobile only.** Chi tiết checklist 3C/3D/3B: xem [`local-deployment-plan.md`](local-deployment-plan.md) (bản tiếng Anh, đầy đủ nhất).
> Quy mô dự kiến: **10 lớp × 50 học sinh** có thể join cùng lúc (~500 kết nối WebSocket đồng thời, chia đều 10 room độc lập).

---

## HƯỚNG DẪN CHO AI (đọc trước khi bắt đầu)

1. Làm từng task theo đúng thứ tự phụ thuộc trong CHECKLIST. Không nhảy cóc.
2. Trước khi code 1 task, đọc mục tương ứng ở **CHI TIẾT TASK**.
3. Tuân thủ đúng **QUY ƯỚC DÙNG CHUNG** — đây là hợp đồng giữa các task, không tự đặt tên khác.
4. Sau khi xong 1 task: tick `[x]`, ghi 1 dòng `> Đã làm: ...` ngắn gọn ngay dưới task đó.
5. Thiếu thông tin → dừng lại hỏi, không tự đoán rồi implement sai.
6. Không tự đổi tech stack đã chốt: **PHP (Laravel) + MySQL** (admin) / **Node.js (Socket.io) + Redis** (realtime).

### QUY TẮC BẮT BUỘC: kiểm tra file trước khi tạo, và giữ tài liệu luôn cập nhật

Dự án này sẽ được code qua **nhiều phiên làm việc khác nhau** (có thể là AI khác nhau, hoặc cùng AI nhưng context bị giới hạn token). Để không phải đọc lại toàn bộ codebase mỗi lần mà vẫn hiểu đúng ngữ cảnh, áp dụng quy tắc sau:

- **Trước khi tạo bất kỳ file thiết kế/tài liệu nào** (schema, API contract, config bàn phím...), luôn `ls`/`view` thư mục `docs/` trước để kiểm tra file đó **đã tồn tại chưa**.
  - Nếu **đã tồn tại** → đọc file đó, **cập nhật (edit) vào file cũ**, không tạo file mới trùng nội dung, không tạo file "v2", "final", "updated" bên cạnh.
  - Nếu **chưa tồn tại** → tạo mới đúng theo tên đã quy định trong bảng "Tài liệu sống" bên dưới.
- **Các file trong `docs/` là nguồn thông tin duy nhất (single source of truth)** về schema, event contract, cấu trúc config. Khi bắt đầu 1 task mới, AI đọc file `docs/` liên quan **thay vì** phải đọc lại toàn bộ code cũ để suy ra ngữ cảnh — vừa nhanh hơn, vừa đỡ tốn token.
- **Bất cứ khi nào code thay đổi làm lệch với tài liệu** (thêm bảng, đổi tên field, đổi event, đổi cấu trúc JSON...), **PHẢI cập nhật ngay file `docs/` tương ứng trong cùng lúc code** — không để tài liệu cũ, sai lệch với code thật. Coi việc cập nhật `docs/` là một phần bắt buộc của "xong task", không phải việc làm thêm.
- Không tạo tài liệu tản mạn ngoài ý muốn (README rải rác trong mỗi thư mục con) — chỉ dùng đúng 3 file sống liệt kê dưới đây, gộp thông tin liên quan vào đúng 1 file thay vì tách nhỏ.

**Bảng tài liệu sống (living docs) — tạo trong thư mục `docs/`:**

| File | Nội dung | Ai tạo lần đầu | Cập nhật khi nào |
|---|---|---|---|
| `docs/DATA_MODEL.md` | Schema đầy đủ, ERD (mermaid), quan hệ giữa các bảng | Task 1.1 | Bất kỳ lúc nào thêm/sửa/xoá bảng hoặc field |
| `docs/API_CONTRACTS.md` | Danh sách event WebSocket, endpoint PHP, Redis key — kèm payload mẫu | Task 2.1 (khởi tạo khung), bổ sung dần ở 1.x/2.x/3.x | Bất kỳ lúc nào thêm/sửa event, endpoint, hoặc Redis key mới |
| `docs/KEYBOARD_SCHEMA.md` | Cấu trúc JSON config của 1 bàn phím (tabs, keys, smart-context rules) | Task 1.5 | Khi thêm loại bàn phím mới hoặc đổi cấu trúc config |

---

## QUY ƯỚC DÙNG CHUNG (bắt buộc áp dụng cho mọi task)

### Cấu trúc thư mục dự án
```
project-root/
├── docker-compose.yml
├── .env
├── docs/
│   ├── DATA_MODEL.md
│   ├── API_CONTRACTS.md
│   └── KEYBOARD_SCHEMA.md
├── migrations/
│   └── 001_init.sql
├── php-admin/
│   ├── auth/
│   ├── admin/
│   │   ├── games/
│   │   ├── quizzes/
│   │   ├── questions/
│   │   ├── keyboards/
│   │   └── reports/        (quản lý điểm số)
│   ├── api/                 (create-game.php, export-csv.php...)
│   └── config/db.php
├── ws-server/
│   ├── index.js
│   ├── ws/
│   │   ├── room.js
│   │   ├── redis.js
│   │   ├── ntp.js
│   │   ├── gameplay.js
│   │   └── scoring.js
│   └── package.json
└── public/     (UI có sẵn — không đổi cấu trúc)
```

### Tech stack đã chốt
| Thành phần | Công nghệ | Lý do |
|---|---|---|
| Admin backend | **PHP (Laravel)** | Auth/CSRF/session có sẵn, Eloquent ORM khớp schema, package `predis/predis` cho Redis |
| WebSocket server | **Node.js + Socket.io** | Room built-in theo PIN, client tự reconnect, `@socket.io/redis-adapter` lo sẵn việc broadcast đa-worker (không cần tự viết pub/sub thủ công) |
| Dữ liệu bền vững | **MySQL 8** | Câu hỏi, tài khoản, lịch sử |
| Dữ liệu realtime | **Redis 7** | State phòng chơi, leaderboard, chống double-submit, TTL tự dọn |

### Port cố định (local)
| Service | Port host |
|---|---|
| PHP Admin | `80` |
| Node.js WS (Socket.io) | `8080` |
| MySQL | `3306` |
| Redis | `6379` |

### Biến môi trường (`.env`)
```
DB_HOST=mysql
DB_PORT=3306
DB_NAME=chem_quiz
DB_USER=app_user
DB_PASSWORD=changeme
REDIS_HOST=redis
REDIS_PORT=6379
WS_PORT=8080
SESSION_SECRET=changeme
```
`DB_HOST`/`REDIS_HOST` dùng tên service Docker (`mysql`, `redis`), không dùng `localhost`.

### Quan hệ dữ liệu (tóm tắt — chi tiết đầy đủ nằm ở `docs/DATA_MODEL.md`, tạo ở task 1.1)
```
games (1) ──< quizzes (N)         // 1 game có nhiều bộ quiz
keyboards (1) ──< quizzes (N)     // 1 bàn phím dùng cho nhiều quiz
quizzes (1) ──< questions (N)     // 1 quiz có nhiều câu hỏi
games (1) ──< game_sessions (N)   // 1 game có thể mở nhiều ván chơi (nhiều PIN khác nhau, nhiều thời điểm)
game_sessions (1) ──< game_results (N)  // 1 ván chơi có nhiều kết quả học sinh
```
Vì sao **Keyboard** tách bảng riêng thay vì nhét cứng vào quiz: bàn phím là 1 cấu hình tái sử dụng (VD: "Bàn phím hoá vô cơ", "Bàn phím hoá hữu cơ") — tạo 1 lần, gán cho nhiều quiz khác nhau, sửa 1 chỗ áp dụng cho mọi quiz đang dùng nó.

### Event WebSocket (client ↔ server)
| Event | Chiều | Payload | Ghi chú |
|---|---|---|---|
| `join_room` | client → server | `{pin, name}` | Socket.io: server gọi `socket.join(pin)` |
| `ntp_ping` / `ntp_pong` | 2 chiều | `{t0}` / `{t0,t1,t2}` | Time sync, xem task 2.3 |
| `game_started` | server → client | `{}` | |
| `new_question` | server → client | `{quiz_id, question_id, text, image_url, keyboard_config, time_limit, server_time}` | Kèm `keyboard_config` để client render đúng bàn phím của quiz đang chơi |
| `submit_answer` | client → server | `{question_id, answer, hybrid_timestamp}` | |
| `question_result` | server → client | `{correct, correct_answer, score_earned, rank}` | |
| `leaderboard_update` | server → client | `{top5: [{name, score, delta}]}` | |
| `game_ended` | server → client | `{final_leaderboard}` | |
| `submit_count_update` | server → client (host) | `{submitted, total}` | |

Danh sách đầy đủ + payload mẫu thực tế (JSON) sống ở `docs/API_CONTRACTS.md` — bảng trên chỉ là bản tóm tắt lúc lập kế hoạch.

### Redis key
| Key | Kiểu | TTL | Ý nghĩa |
|---|---|---|---|
| `room:<PIN>` | Hash | 2h | `status`, `game_id`, `current_quiz_id`, `current_question_id` |
| `room:<PIN>:players` | Hash | 2h | Danh sách học sinh trong room |
| `leaderboard:<PIN>` | ZSET | 2h | Điểm tổng, sorted theo score |
| `submitted:<PIN>:<question_id>` | Set | 2h | Chặn double-submit |

### Quy tắc code chung
- Node.js: Socket.io + `@socket.io/redis-adapter` + `ioredis`.
- PHP: Laravel, Eloquent cho MySQL, `predis/predis` cho Redis.
- Không hardcode config — luôn đọc từ `.env`.
- API PHP trả JSON thống nhất: `{"success": true/false, "data": {...}, "error": "..."}`.

---

## TỔNG QUAN CÁC BƯỚC

```
Bước 0: Docker Compose (MySQL + Redis + Node + PHP)
            │
            ▼
Bước 1: Database (games/keyboards/quizzes/questions) + Laravel Admin   ─┐
                                                                          │ song song
Bước 2: WebSocket server (Node.js + Socket.io)                          ─┘
            │
            ▼
Bước 3: Nối UI có sẵn vào 2 backend
            │
            ▼
Bước 4: Test tổng thể — bao gồm test tải 500 kết nối (10 room × 50)
```

---

## CHECKLIST TASK

### Phase 0 — Hạ tầng Docker (local)
- [ ] 0.1 Viết `docker-compose.yml` (MySQL + Redis + Node + PHP) — *phụ thuộc: không*
- [ ] 0.2 Tạo `.env` — *phụ thuộc: không*
- [ ] 0.3 Kiểm tra container kết nối nhau — *phụ thuộc: 0.1, 0.2*

### Phase 1 — Database & Laravel Admin
- [ ] 1.1 Migration: `games`, `keyboards`, `quizzes`, `questions`, `game_sessions`, `game_results`, `users` + tạo `docs/DATA_MODEL.md` — *phụ thuộc: 0.3*
- [ ] 1.2 Seed dữ liệu mẫu (1 game, 2 keyboard, vài quiz, câu hỏi, tài khoản GV) — *phụ thuộc: 1.1*
- [ ] 1.3 Laravel kết nối MySQL (Eloquent config) — *phụ thuộc: 1.1*
- [ ] 1.4 Auth giáo viên (login/session/CSRF) — *phụ thuộc: 1.3*
- [ ] 1.5 CRUD Bàn phím (Keyboard) + tạo `docs/KEYBOARD_SCHEMA.md` — *phụ thuộc: 1.3, 1.4*
- [ ] 1.6 CRUD Game — *phụ thuộc: 1.3, 1.4*
- [ ] 1.7 CRUD Quiz + Câu hỏi (gán `game_id`, `keyboard_id`) — *phụ thuộc: 1.5, 1.6*
- [ ] 1.8 Endpoint "Tạo game mới" (chọn Game → sinh PIN, ghi Redis) — *phụ thuộc: 1.4, 1.7, 0.3*
- [ ] 1.9 Quản lý & xem điểm số (báo cáo lịch sử theo game/session/học sinh) — *phụ thuộc: 1.7*

### Phase 2 — WebSocket Server (Node.js + Socket.io)
- [ ] 2.1 Setup Node.js + Socket.io + Redis adapter + khởi tạo `docs/API_CONTRACTS.md` — *phụ thuộc: 0.3*
- [ ] 2.2 Room manager (`join_room`, reconnect, dùng `socket.join`) — *phụ thuộc: 2.1*
- [ ] 2.3 NTP time sync — *phụ thuộc: 2.2*
- [ ] 2.4 Gameplay & Scoring (`submit_answer` → `question_result`, chống double-submit) — *phụ thuộc: 2.2, 2.3*
- [ ] 2.5 Broadcast realtime qua Socket.io room (`leaderboard_update`, `new_question`) — *phụ thuộc: 2.4*

### Phase 3 — Nối UI có sẵn vào Backend
- [ ] 3.1 Join screen → API check PIN → mở Socket.io → NTP sync — *phụ thuộc: 1.8, 2.3*
- [ ] 3.2 Waiting room → lắng nghe `game_started` — *phụ thuộc: 2.2*
- [ ] 3.3 Game screen → nhận `keyboard_config` trong `new_question`, render đúng bàn phím, submit — *phụ thuộc: 2.4, 2.5*
- [ ] 3.4 Host screen → tạo game qua Laravel, điều khiển qua Socket.io — *phụ thuộc: 1.8, 2.5*
- [ ] 3.5 Final screen → `game_ended`, export CSV — *phụ thuộc: 2.5, 1.9*

### Phase 4 — Test tổng thể local
- [ ] 4.1 Test luồng chơi đầy đủ, 1 room — *phụ thuộc: Phase 3 xong hết*
- [ ] 4.2 Test tải: 10 room × 50 học sinh (~500 kết nối đồng thời) — *phụ thuộc: 4.1*
- [ ] 4.3 Test reconnect — *phụ thuộc: 4.1*
- [ ] 4.4 Test double-submit & clock skew — *phụ thuộc: 4.1*

---

## CHI TIẾT TASK

### 0.1 — Viết `docker-compose.yml`
**Làm gì:** 4 service: `mysql` (`mysql:8`), `redis` (`redis:7-alpine`), `ws-server` (build từ `./ws-server`, port `8080`), `php-admin` (Laravel — dùng image `php:8.2-fpm` + Nginx, hoặc `serversideup/php:8.2-fpm-nginx` cho gọn). Named volume cho MySQL.

**Tiêu chí hoàn thành:** `docker-compose up` không lỗi, `docker ps` đủ 4 container `Up`, truy cập được `http://localhost` và `ws://localhost:8080`.

---

### 0.2 — File `.env`
**Làm gì:** Copy đúng bảng biến môi trường ở QUY ƯỚC DÙNG CHUNG.

**Tiêu chí hoàn thành:** Đổi 1 giá trị, restart container, hệ thống nhận thay đổi không cần sửa code.

---

### 0.3 — Kiểm tra container kết nối nhau
**Làm gì:** Từ `ws-server`: `redis-cli -h redis ping` → `PONG`. Từ `php-admin`: Eloquent connect MySQL qua host `mysql`.

**Tiêu chí hoàn thành:** Không lỗi `ECONNREFUSED` giữa các service.

---

### 1.1 — Migration & tạo `docs/DATA_MODEL.md`
**Trước khi làm:** kiểm tra `docs/DATA_MODEL.md` đã tồn tại chưa (chưa tồn tại ở lần chạy đầu → tạo mới).

**File cần tạo:** `migrations/001_init.sql` (hoặc Laravel migration files chuẩn `database/migrations/*.php`), `docs/DATA_MODEL.md`

**Làm gì:** Tạo 7 bảng:
- `users` (id, username, password_hash, role ENUM('admin','teacher'))
- `keyboards` (id, name, config JSON, created_at) — `config` lưu cấu trúc tabs/keys, xem task 1.5
- `games` (id, name, description, created_at)
- `quizzes` (id, game_id FK→games, keyboard_id FK→keyboards, name, subject, grade, created_at)
- `questions` (id, quiz_id FK→quizzes, text, image_url NULL, correct_answer_normalized, time_limit_seconds)
- `game_sessions` (id, pin CHAR(6), host_id FK→users, game_id FK→games, status ENUM('waiting','playing','ended'), created_at)
- `game_results` (id, session_id FK→game_sessions, student_name, score INT, rank INT)

Sau khi migration chạy được, viết `docs/DATA_MODEL.md` gồm: bảng mô tả từng field, và 1 sơ đồ ERD dạng mermaid:
```
erDiagram
  GAMES ||--o{ QUIZZES : contains
  KEYBOARDS ||--o{ QUIZZES : "used by"
  QUIZZES ||--o{ QUESTIONS : contains
  GAMES ||--o{ GAME_SESSIONS : "played as"
  GAME_SESSIONS ||--o{ GAME_RESULTS : produces
  USERS ||--o{ GAME_SESSIONS : hosts
```

**Tiêu chí hoàn thành:** `SHOW TABLES;` ra đủ 7 bảng, foreign key hoạt động đúng (insert vi phạm FK bị từ chối). File `docs/DATA_MODEL.md` tồn tại, đọc hiểu được schema mà không cần mở migration file.

---

### 1.2 — Seed dữ liệu mẫu
**Làm gì:** 2 keyboard mẫu (VD: "Hoá vô cơ", "Hoá hữu cơ"), 1 game ("Ôn tập học kỳ 1"), 2-3 quiz gán vào game đó + gán keyboard, 5-10 câu hỏi mỗi quiz, 1 tài khoản GV test.

**Tiêu chí hoàn thành:** Query từng bảng ra dữ liệu đọc được, đúng quan hệ (quiz đúng thuộc về game, đúng gán keyboard). Login bằng tài khoản seed thành công ở 1.4.

---

### 1.3 — Laravel kết nối MySQL
**Làm gì:** Cấu hình `.env` Laravel trỏ đúng biến ở QUY ƯỚC DÙNG CHUNG, tạo Eloquent Model cho 7 bảng.

**Tiêu chí hoàn thành:** `php artisan tinker` query `Game::all()` ra dữ liệu không lỗi.

---

### 1.4 — Auth giáo viên
**Làm gì:** Dùng Laravel Auth scaffolding (session-based), bcrypt tự động qua `Hash::make`, CSRF token Laravel mặc định (`@csrf`), session timeout 8 giờ.

**Tiêu chí hoàn thành:** Login đúng → vào dashboard. Sai → báo lỗi. Request thiếu CSRF → 419. Hết hạn session → redirect login.

---

### 1.5 — CRUD Bàn phím (Keyboard) & `docs/KEYBOARD_SCHEMA.md`
**Trước khi làm:** kiểm tra `docs/KEYBOARD_SCHEMA.md` đã tồn tại chưa.

**Làm gì:** API tạo/sửa/xoá 1 bàn phím. Field `config` là JSON tự định nghĩa cấu trúc, ví dụ:
```json
{
  "tabs": [
    {"label": "Nguyên tố", "keys": ["H","O","Na","Cl","..."]},
    {"label": "Ký hiệu", "keys": ["+","=","(",")","→"]}
  ],
  "smart_context": {"after_element": "subscript", "after_plus": "coefficient"}
}
```
Ghi cấu trúc này vào `docs/KEYBOARD_SCHEMA.md` (đây là tài liệu sống — mỗi lần thêm loại bàn phím có cấu trúc khác, cập nhật file này, không tạo file mô tả riêng cho từng loại bàn phím).

**Tiêu chí hoàn thành:** Tạo/sửa/xoá bàn phím qua API thành công. `docs/KEYBOARD_SCHEMA.md` mô tả đủ để 1 AI khác đọc và biết chính xác cấu trúc JSON `config` mà không cần xem code.

---

### 1.6 — CRUD Game
**Làm gì:** API tạo/sửa/xoá 1 game (chỉ có `name`, `description` — chưa gán quiz ở bước này).

**Tiêu chí hoàn thành:** Test curl: tạo/sửa/xoá game thành công, dữ liệu đúng trong DB.

---

### 1.7 — CRUD Quiz + Câu hỏi
**Làm gì:** API tạo/sửa/xoá quiz — bắt buộc chọn `game_id` (game đã tồn tại) và `keyboard_id` (keyboard đã tồn tại). API tạo/sửa/xoá câu hỏi thuộc 1 quiz. Validate: `game_id`, `keyboard_id` phải tồn tại trước khi tạo quiz; xoá 1 game không được xoá "mồ côi" — chặn xoá nếu còn quiz đang gán vào, hoặc cho phép cascade (chọn 1 cách, ghi rõ trong code comment và trong `docs/DATA_MODEL.md`).

**Tiêu chí hoàn thành:** Tạo quiz gán đúng game + keyboard đã chọn. Query lại thấy đúng quan hệ. Tạo quiz với `game_id` không tồn tại → bị từ chối rõ ràng.

---

### 1.8 — Endpoint "Tạo game mới"
**Làm gì:** GV chọn 1 `game_id` → sinh PIN 6 số không trùng (kiểm tra trong `game_sessions` đang `waiting`/`playing`) → insert `game_sessions` → ghi Redis `room:<PIN>` (Hash: `status=waiting`, `game_id`, TTL 7200s).

**Tiêu chí hoàn thành:** Gọi API → nhận PIN hợp lệ, không trùng lặp. `redis-cli HGETALL room:<PIN>` đúng dữ liệu, TTL ~7200.

---

### 1.9 — Quản lý & xem điểm số
**Làm gì:** API xem lịch sử: danh sách `game_sessions` đã chơi (theo game, theo ngày), chi tiết điểm từng học sinh trong 1 session (từ `game_results`), tổng hợp điểm theo học sinh nếu 1 học sinh chơi nhiều session (dùng `student_name` để nhóm, lưu ý đây không phải tài khoản đăng nhập nên chỉ tổng hợp tương đối theo tên).

**Tiêu chí hoàn thành:** Xem được danh sách session đã chơi, click vào 1 session ra đúng bảng điểm đã lưu ở `game_results`, số liệu khớp với leaderboard cuối cùng lúc chơi.

---

### 2.1 — Setup Node.js + Socket.io + Redis adapter, khởi tạo `docs/API_CONTRACTS.md`
**Trước khi làm:** kiểm tra `docs/API_CONTRACTS.md` đã tồn tại chưa.

**File cần tạo:** `ws-server/package.json`, `ws-server/index.js`, `ws-server/ws/redis.js`, `docs/API_CONTRACTS.md`

**Làm gì:** Cài `socket.io`, `@socket.io/redis-adapter`, `ioredis`. Khởi tạo Socket.io server, gắn Redis adapter (`io.adapter(createAdapter(pubClient, subClient))`). Tạo `docs/API_CONTRACTS.md` với khung: bảng event WS (copy từ QUY ƯỚC DÙNG CHUNG làm điểm khởi đầu), bảng Redis key, danh sách endpoint PHP — các task sau (1.x, 2.x, 3.x) sẽ bổ sung/cập nhật file này khi thêm event/endpoint mới thay vì tạo bản ghi lại từ đầu.

**Tiêu chí hoàn thành:** `node index.js` chạy, log "Connected to Redis" + "Socket.io listening on 8080". Tắt Redis giữa chừng không crash server. `docs/API_CONTRACTS.md` tồn tại và có đủ khung ban đầu.

---

### 2.2 — Room manager
**File cần tạo:** `ws-server/ws/room.js`

**Làm gì:** `join_room {pin, name}` → validate PIN tồn tại trong Redis → `socket.join(pin)` (Socket.io tự nhóm client theo room, không cần tự quản lý danh sách) → thêm vào `room:<PIN>:players`. Khi disconnect bất ngờ: đánh dấu `disconnected`, giữ state để reconnect cùng `pin`+`name` khôi phục đúng vị trí.

**Tiêu chí hoàn thành:** 2 client join cùng PIN → cả 2 trong room Socket.io đó (`io.sockets.adapter.rooms.get(pin)` có đủ). Đóng tab, mở lại cùng tên → khôi phục đúng, không mất điểm, không bị tính người mới.

---

### 2.3 — NTP time sync
**File cần tạo:** `ws-server/ws/ntp.js`

**Làm gì:** Như thiết kế cũ: `ntp_ping{t0}` → server trả `ntp_pong{t0,t1,t2}`, client lặp 3 lần lấy median offset. Khi submit, tính `hybrid_timestamp`; lệch >500ms so với giờ server → reject với message rõ ràng.

**Tiêu chí hoàn thành:** Offset hợp lý trên local (vài chục ms). Giả lập lệch giờ >500ms → bị reject có message cụ thể.

---

### 2.4 — Gameplay & Scoring
**File cần tạo:** `ws-server/ws/gameplay.js`, `ws-server/ws/scoring.js`

**Làm gì:** `submit_answer{question_id, answer, hybrid_timestamp}` → `SADD submitted:<PIN>:<question_id>` chặn double-submit → tính điểm:
```
score = 1000 × (time_remaining / time_limit) × accuracy_bonus
streak ≥3 câu đúng liên tiếp → +50/câu tiếp theo
```
Ghi `ZINCRBY leaderboard:<PIN>`. Trả `question_result` riêng cho client vừa submit.

**Tiêu chí hoàn thành:** Submit đúng giây thứ 3/30 → ~900 điểm. Submit 2 lần cùng câu → lần 2 bị chặn. `ZRANGE leaderboard:<PIN> 0 -1 WITHSCORES` đúng thứ tự.

---

### 2.5 — Broadcast realtime
**Làm gì:** Dùng `io.to(pin).emit(...)` của Socket.io (đã có Redis adapter từ 2.1 nên tự động đúng dù client ở worker nào — không cần tự viết cơ chế pub/sub thủ công) để gửi `new_question`, `leaderboard_update`, `submit_count_update`, `game_ended` tới đúng room.

**Tiêu chí hoàn thành:** 3 tab học sinh + 1 tab host cùng PIN, khi Next/hết giờ, cả 3 tab nhận `new_question` gần như đồng thời.

---

### 3.1 — Join screen
**Làm gì:** Gọi API check PIN hợp lệ → mở `io('http://localhost:8080')` (Socket.io client) → gửi `join_room` → chạy NTP sync.

**Tiêu chí hoàn thành:** PIN sai → báo lỗi trên UI có sẵn. PIN đúng → chuyển Waiting Room, offset NTP tính xong trước khi chuyển màn hình.

---

### 3.2 — Waiting room
**Làm gì:** Lắng nghe `game_started`, tự động chuyển màn hình.

**Tiêu chí hoàn thành:** Host bấm Start → mọi tab học sinh chuyển màn hình <1 giây.

---

### 3.3 — Game screen (học sinh)
**Làm gì:** Nhận `new_question` (có kèm `keyboard_config`) → render đúng loại bàn phím của quiz đang chơi (không phải bàn phím cố định — mỗi quiz có thể khác nhau) → timer theo `server_time` + offset → submit → khoá input → nhận `question_result`, `leaderboard_update`.

**Tiêu chí hoàn thành:** Bàn phím hiển thị đúng theo `keyboard_config` của quiz hiện tại (đổi quiz khác trong cùng game → bàn phím đổi theo nếu 2 quiz gán 2 keyboard khác nhau). Timer không lệch. Không submit được 2 lần.

---

### 3.4 — Host screen
**Làm gì:** Tạo game qua Laravel (1.8) → mở Socket.io với vai trò host → hiện `submit_count_update` → nút Next/End gửi lệnh qua Socket.io.

**Tiêu chí hoàn thành:** Số đã nộp tăng đúng realtime. Next/End hoạt động đúng điều kiện.

---

### 3.5 — Final screen
**File cần tạo:** `php-admin/api/export-csv.php` (hoặc route Laravel tương ứng)

**Làm gì:** Lắng nghe `game_ended` → hiện podium top 3. Nút Export CSV gọi endpoint đọc `game_results` theo `session_id`, trả CSV tải về (tận dụng logic từ task 1.9).

**Tiêu chí hoàn thành:** Bảng cuối khớp leaderboard Redis trước khi hết TTL. File CSV mở được, đủ cột, đúng số liệu.

---

### 4.1 — Test luồng chơi đầy đủ (1 room)
**Làm gì:** 1 tab Host + 2-3 tab học sinh, chơi hết 1 quiz mẫu.

**Tiêu chí hoàn thành:** Không treo/mất kết nối/sai điểm. Đúng thứ tự: Join → Waiting → Question → Result → Leaderboard → (lặp) → Final.

---

### 4.2 — Test tải: 10 room × 50 học sinh (~500 kết nối)
**Làm gì:** Dùng script k6 hoặc Artillery giả lập 10 PIN khác nhau, mỗi PIN 50 client kết nối + join + submit gần như đồng thời ở mốc hết giờ (đây là lúc tải cao nhất — nhiều submit dồn cùng lúc).

**Tiêu chí hoàn thành:** Không client nào bị drop kết nối. p99 latency của `submit_answer` → `question_result` dưới ngưỡng chấp nhận được (đề xuất <200ms trên local, ghi lại số đo thực tế). RAM/CPU của container `ws-server` không tăng bất thường hay memory-leak sau nhiều câu hỏi liên tiếp. Redis không bị nghẽn (`redis-cli INFO` kiểm tra `connected_clients`, `used_memory`).

---

### 4.3 — Test reconnect
**Làm gì:** Ngắt mạng/F5 1 tab học sinh giữa câu hỏi rồi kết nối lại.

**Tiêu chí hoàn thành:** Quay lại đúng câu hiện tại, không mất điểm, không bị tính người chơi mới.

---

### 4.4 — Test double-submit & clock skew
**Làm gì:** Gọi `submit_answer` 2 lần liên tiếp qua DevTools Console. Đổi giờ hệ thống lệch >500ms rồi submit.

**Tiêu chí hoàn thành:** Lần 2 bị chặn, không cộng điểm thêm. Lệch giờ >500ms bị reject rõ ràng, không lỗi 500 chung chung.

---

*File này theo dõi tiến độ triển khai. Chi tiết schema/API/config sống trong `docs/` — luôn đọc và cập nhật các file đó thay vì suy đoán lại từ đầu. File đặc tả kỹ thuật gốc (`dac-ta-ky-thuat-v4`) vẫn là tài liệu tham khảo cho phần UI/UX (mục 3, 5) và roadmap (mục 7).*
