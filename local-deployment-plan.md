# LOCAL SERVER DEPLOYMENT PLAN — Real-time Chemistry Quiz Website

> The frontend (HTML/CSS/JS) exists but is **not 100% complete** — don't wait for it to finish before starting. Recommended order: build infrastructure + backend logic + DB first (Phase 0-2), because once the API/events/schema are locked in, finishing the UI in Phase 3 has a concrete contract to build against (exact endpoints, exact events, exact payloads) instead of guessing on both sides at once.
> **Phase 3 order (updated):** 3A integration (done) → **3C Admin UI web đầy đủ** (ưu tiên tiếp theo — tạo data thật trong DB) → **3D Teacher + Student** hoàn thiện chức năng với data admin → **3B UI polish** → **Phase 4** test. Admin + Teacher = **web desktop**; Student = **mobile only** (`docs/APP_STYLE.md` chỉ cho HS).
> In Phase 3, if a UI screen doesn't exist yet or is incomplete, just create/finish it following the existing file structure (no need to ask) — only ask if it's unclear where the logic should live.
> This file is for an AI coding agent (Cursor, Claude Code...) implementing the backend + local infrastructure.
> Expected scale: **10 classes × 50 students** may join at the same time (~500 concurrent WebSocket connections, spread across 10 independent rooms).

---

## INSTRUCTIONS FOR THE AI (read before starting)

1. Work through tasks in the exact dependency order in the CHECKLIST. Do not skip ahead.
2. Before coding a task, read its corresponding entry in **TASK DETAILS**.
3. Follow the **SHARED CONVENTIONS** exactly — these are the contract between tasks; do not invent different names.
4. After finishing a task: check `[x]`, and write one short `> Done: ...` line right below it.
5. Missing information → stop and ask, don't guess and implement wrong — an early mistake cascades into every task that depends on it.
6. Do not change the locked-in tech stack: **PHP (Laravel) + MySQL** (admin) / **Node.js (Socket.io) + Redis** (real-time).

### UI platform targets (bắt buộc)

| Surface | Platform | Code location | Style reference |
|---|---|---|---|
| **Admin** | Web desktop | `php-admin/resources/views/` (Laravel Blade) | Form/table layout; không mobile-first |
| **Teacher (host)** | Web desktop | `prototype/teacher.html` (+ `teacher.css`) | `prototype/reference/teacher-website*.png` |
| **Student** | **Mobile only** | `prototype/index.html` (+ `student.css`) | `docs/APP_STYLE.md` |

- Không thiết kế admin/teacher responsive cho điện thoại.
- Không áp layout Question mobile của học sinh lên teacher/admin.
- Keyboard editor (`prototype/keyboard-editor.html`) thuộc **admin/web**.

### MANDATORY RULE: check if a file exists before creating one, and keep docs continuously updated

This project will be built across **multiple separate work sessions** (possibly different AI instances, or the same AI with limited context). To avoid re-reading the entire codebase every time while still understanding the correct context, follow this rule:

- **Before creating any design/reference file** (schema, API contract, keyboard config...), always `ls`/`view` the `docs/` folder first to check whether it **already exists**.
  - If it **exists** → read it and **edit the existing file** — never create a duplicate, never create a "v2", "final", or "updated" file next to it.
  - If it **doesn't exist** → create it under the exact name specified in the "Living docs" table below.
- **The files under `docs/` are the single source of truth** for schema, event contracts, and config structures. When starting a new task, the AI reads the relevant `docs/` file **instead of** re-reading the entire old codebase to infer context — faster, and uses far fewer tokens.
- **Whenever code changes in a way that makes it diverge from the docs** (a new table, a renamed field, a new event, a changed JSON structure...), **update the corresponding `docs/` file in the same pass** — never leave the docs stale or out of sync with the real code. Treat updating `docs/` as a required part of "task done," not optional extra work.
- Don't scatter documentation around the repo (READMEs in every subfolder) — use only the 3 living files listed below, and consolidate related information into the one file instead of splitting it up.

**Living docs table — create these under `docs/`:**

| File | Content | First created by | Update whenever |
|---|---|---|---|
| `docs/DATA_MODEL.md` | Full schema, ERD (mermaid), relationships between tables | Task 1.1 | Any table/field is added, changed, or removed |
| `docs/API_CONTRACTS.md` | List of WebSocket events, PHP endpoints, Redis keys — with sample payloads | Task 2.1 (initial skeleton), extended in 1.x/2.x/3.x | Any new/changed event, endpoint, or Redis key |
| `docs/KEYBOARD_SCHEMA.md` | JSON config for keyboard layout (`rows[]`, `defaults`, `smart_context`) | Aligned with prototype editor (pre-1.5) | Layout or key type changes |

---

## SHARED CONVENTIONS (mandatory for every task)

### Project folder structure
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
│   │   └── reports/        (score management)
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
└── prototype/  (UI archive — không serve trong Phase 0; wire ở Phase 3)
```

> `prototype/` chứa HTML/CSS/JS prototype chưa hoàn chỉnh. Không đổi cấu trúc khi archive; khi triển khai UI thật (Phase 3) sẽ copy/adapt sang `public/` hoặc framework app.

### Locked-in tech stack
| Component | Technology | Why |
|---|---|---|
| Admin backend | **PHP (Laravel)** | Auth/CSRF/session built in, Eloquent ORM maps cleanly onto the schema, `predis/predis` package for Redis |
| WebSocket server | **Node.js + Socket.io** | Built-in rooms keyed by PIN, client auto-reconnect, `@socket.io/redis-adapter` handles multi-worker broadcast for you (no need to hand-roll pub/sub) |
| Durable data | **MySQL 8** | Questions, accounts, history |
| Real-time data | **Redis 7** | Room state, leaderboard, double-submit guard, TTL auto-cleanup |

### Local dev ports (custom — avoid conflicts with other Docker stacks)

| Service | Host port | Internal (Docker network) |
|---|---|---|
| PHP Admin | `38480` | nginx `8080` |
| Node.js WS (Socket.io) | `38581` | `38581` |
| MySQL | `38306` | `3306` |
| Redis | `38637` | `6379` |

Production deploy: update host ports in `.env` as needed.

### Environment variables (`.env`)
```
DB_HOST=mysql
DB_PORT=3306
DB_NAME=chem_quiz
DB_USER=app_user
DB_PASSWORD=changeme
REDIS_HOST=redis
REDIS_PORT=6379
WS_PORT=38581
SESSION_SECRET=changeme
APP_KEY=base64:changeme-generate-with-artisan-key-generate

# Host port mapping (docker-compose only)
PHP_HOST_PORT=38480
MYSQL_HOST_PORT=38306
REDIS_HOST_PORT=38637
WS_HOST_PORT=38581
```
`DB_HOST`/`REDIS_HOST` use the Docker service names (`mysql`, `redis`), not `localhost`.

### Data relationships (summary — full detail in `docs/DATA_MODEL.md`)
```
users (1) ──< games (N)              // created_by
games (1) ──< quizzes (N)
keyboards (1) ──< quizzes (N)
quizzes (1) ──< questions (N)
games (1) ──< game_sessions (N)
users (1) ──< game_sessions (N)      // host_id
game_sessions (1) ──< game_results (N)
game_sessions (1) ──< session_answers (N)
questions (1) ──< session_answers (N)
```
- `users`: extend Laravel default table + `role` — do not create a new users table.
- `questions.content`: single LONGTEXT HTML column (text + image + video via rich text editor; sanitize on save).
- `questions.answer_type`: `mc` | `formula` | `structured`.
Why **keyboard** is its own table instead of hardcoded per quiz: a keyboard is a reusable config (e.g. "Inorganic chemistry keyboard", "Organic chemistry keyboard") — create it once, assign it to multiple quizzes, edit it once and every quiz using it updates.

### WebSocket events (client ↔ server)
| Event | Direction | Payload | Notes |
|---|---|---|---|
| `join_room` | client → server | `{pin, name}` | Socket.io: server calls `socket.join(pin)` |
| `ntp_ping` / `ntp_pong` | both | `{t0}` / `{t0,t1,t2}` | Time sync, see task 2.3 |
| `game_started` | server → client | `{}` | |
| `new_question` | server → client | `{quiz_id, question_id, content, answer_type, options?, template?, keyboard_config, time_limit, server_time}` | `content` = HTML (text+img+video); `keyboard_config` from quiz's keyboard |
| `submit_answer` | client → server | `{question_id, answer, hybrid_timestamp}` | |
| `question_result` | server → client | `{correct, correct_answer, score_earned, rank}` | |
| `leaderboard_update` | server → client | `{top5: [{name, score, delta}]}` | |
| `game_ended` | server → client | `{final_leaderboard}` | |
| `submit_count_update` | server → client (host) | `{submitted, total}` | |

The full list with real sample JSON payloads lives in `docs/API_CONTRACTS.md` — the table above is just a planning-time summary.

### Redis keys
| Key | Type | TTL | Meaning |
|---|---|---|---|
| `room:<PIN>` | Hash | 2h | `status`, `game_id`, `current_quiz_id`, `current_question_id` |
| `room:<PIN>:players` | Hash | 2h | Students currently in the room |
| `leaderboard:<PIN>` | ZSET | 2h | Total score, sorted |
| `submitted:<PIN>:<question_id>` | Set | 2h | Blocks double-submit |

### General coding rules
- Node.js: Socket.io + `@socket.io/redis-adapter` + `ioredis`.
- PHP: Laravel, Eloquent for MySQL, `predis/predis` for Redis.
- No hardcoded config — always read from `.env`.
- Every PHP API returns a consistent JSON shape: `{"success": true/false, "data": {...}, "error": "..."}`.

---

## OVERVIEW OF STEPS

```
Step 0: Docker Compose (MySQL + Redis + Node + PHP)
            │
            ▼
Step 1: Database (games/keyboards/quizzes/questions) + Laravel Admin   ─┐
                                                                          │ in parallel
Step 2: WebSocket server (Node.js + Socket.io)                          ─┘
            │
            ▼
Step 3A: Integration layer (prototype ↔ API/WS) — plumbing only
            │
            ▼
Step 3C: Admin UI web đầy đủ (CRUD → data thật trong MySQL)   ← ƯU TIÊN TIẾP
            │
            ▼
Step 3D: Teacher (web) + Student (mobile) — chức năng với data admin
            │
            ▼
Step 3B: UI polish (teacher web + student mobile theo mockup)
            │
            ▼
Step 4: Full local testing (chỉ khi Phase 3 xong)
```

---

## TASK CHECKLIST

### Phase 0 — Local Docker infrastructure
- [x] 0.1 Write `docker-compose.yml` (MySQL + Redis + Node + PHP) — *depends on: none*
> Done: 4 services on custom ports 38480/38581/38306/38637; healthchecks enabled.
- [x] 0.2 Create `.env` — *depends on: none*
> Done: `.env` + `.env.example` with internal Docker vars and host port mapping.
- [x] 0.3 Verify containers can reach each other — *depends on: 0.1, 0.2*
> Done: ws-server Redis PONG, `php artisan db:show` OK, HTTP 200 on :38480, `/health` on :38581.

### Phase 1 — Database & Laravel Admin
- [x] 1.1 Migrations: app tables + extend `users.role` — *depends on: 0.3*
> Done: 8 migrations batch [2]; tables keyboards, games, quizzes, questions, game_sessions, game_results, session_answers; FK restrict/cascade verified.
- [x] 1.2 Seed sample data (1 game, 2 keyboards, a few quizzes, questions, teacher account) — *depends on: 1.1*
> Done: SampleDataSeeder — teacher@hoadat.local, 2 keyboards, 1 game, 3 quizzes, 5+ câu/quiz, session mẫu PIN 123456.
- [x] 1.3 Laravel MySQL connection (Eloquent config) — *depends on: 1.1*
> Done: 8 Eloquent models + User.role; docker env DB mysql + predis.
- [x] 1.4 Teacher auth (login/session/CSRF) — *depends on: 1.3*
> Done: LoginController, session 480 phút, dashboard; CSRF trên form/API web.
- [x] 1.5 Keyboard CRUD + `docs/KEYBOARD_SCHEMA.md` — *depends on: 1.3, 1.4* (`KEYBOARD_SCHEMA.md` **done** — implement CRUD API)
> Done: `/api/keyboards` CRUD + KeyboardValidator (MAX_UNITS, space row, delete/space/send).
- [x] 1.6 Game CRUD — *depends on: 1.3, 1.4*
> Done: `/api/games` CRUD; RESTRICT xóa khi còn quiz.
- [x] 1.7 Quiz + Question CRUD (assign `game_id`, `keyboard_id`) — *depends on: 1.5, 1.6*
> Done: `/api/quizzes`, nested `/api/quizzes/{id}/questions`; validate answer_type + HTML sanitize.
- [x] 1.8 "Create new game session" endpoint (pick a Game → generate PIN, write to Redis) — *depends on: 1.4, 1.7, 0.3*
> Done: POST `/api/game-sessions` → PIN 6 số + Redis `room:{pin}` TTL 7200s.
- [x] 1.9 Score management & reporting (history per game/session/student) — *depends on: 1.7*
> Done: GET `/api/reports/sessions`, `/api/reports/sessions/{id}`, `/api/reports/students/aggregate`.

### Phase 2 — WebSocket server (Node.js + Socket.io)
- [x] 2.1 Setup Node.js + Socket.io + Redis adapter + bootstrap `docs/API_CONTRACTS.md` — *depends on: 0.3*
> Done: index.js + redis adapter + mysql2; health `/health`; API_CONTRACTS §9–10 mở rộng.
- [x] 2.2 Room manager (`join_room`, reconnect, using `socket.join`) — *depends on: 2.1*
> Done: `ws/room.js` — join_room, room_joined, players_update, disconnect giữ score.
- [x] 2.3 NTP time sync — *depends on: 2.2*
> Done: `ws/ntp.js` — ntp_ping/pong; reject submit lệch >500ms.
- [x] 2.4 Gameplay & scoring (`submit_answer` → `question_result`, double-submit guard) — *depends on: 2.2, 2.3*
> Done: `ws/gameplay.js` + `ws/scoring.js`; SADD submitted, ZINCRBY leaderboard, persist session_answers.
- [x] 2.5 Real-time broadcast via Socket.io rooms (`leaderboard_update`, `new_question`) — *depends on: 2.4*
> Done: host_start/next/end; emit game_started, new_question, leaderboard_update, submit_count_update, game_ended.

### Phase 3C — Admin UI web đầy đủ (ưu tiên tiếp theo)
> Admin là source of truth cho nội dung chơi. API Phase 1 đã có; cần **giao diện Blade** thay dashboard placeholder. Làm xong 3C → có data thật trong DB → triển khai teacher/student chuẩn hơn.

- [x] 3C.1 Dashboard layout + navigation (sidebar) — *phụ thuộc: 1.4*
> Done: `layouts/admin.blade.php` + sidebar nav; `/admin` dashboard với stats.
- [x] 3C.2 Keyboard CRUD UI (+ editor config theo `KEYBOARD_SCHEMA.md`) — *phụ thuộc: 1.5*
> Done: CRUD Blade + JSON config; link `/app/keyboard-editor.html` để chỉnh trực quan.
- [x] 3C.3 Game CRUD UI — *phụ thuộc: 1.6*
> Done: CRUD Blade `admin/games`.
- [x] 3C.4 Quiz CRUD UI (chọn `game_id`, `keyboard_id`) — *phụ thuộc: 1.7*
> Done: Form dropdown game/keyboard; list lọc theo game.
- [x] 3C.5 Question CRUD UI (`mc`, `formula`, `structured`) — *phụ thuộc: 1.7*
> Done: Form nested dưới quiz; toggle theo `answer_type`.
- [x] 3C.6 Tạo phòng từ admin (chọn Game → PIN + Redis) + link sang host — *phụ thuộc: 1.8*
> Done: `admin/sessions` + link host `?pin=&game_id=&session_id=`; `teacher.js` join phòng có sẵn.
- [x] 3C.7 Báo cáo: lịch sử session, chi tiết điểm, export CSV — *phụ thuộc: 1.9*
> Done: `admin/reports` + `GET /admin/reports/{id}/export` CSV UTF-8.

**Acceptance 3C:** GV login → tạo keyboard + game + quiz + câu hỏi mix loại trên UI (không curl/seed) → tạo phòng thấy PIN → sau game xem báo cáo + tải CSV.

### Phase 3D — Teacher (web) + Student (mobile): chức năng với data admin
> Sau 3C. Bám data thật từ DB; hoàn thiện luồng chơi trước khi polish UI (3B).

- [x] 3D.1 Teacher: chọn game từ admin (bỏ hardcode `game_id=1`), host flow ổn — *phụ thuộc: 3C.6, 3A.4*
> Done: dropdown game trên teacher setup; admin link `?pin&game_id`; teacher nhận `new_question` từ WS.
- [x] 3D.2 Student: MC + formula + structured từ data admin — *phụ thuộc: 3C.5, 3A.3*
> Done: adapter + `input_mode` trong `new_question`; fix `getFocusedSlotType`, structured template.
- [x] 3D.3 Final + export CSV (wire nút, dùng reports API) — *phụ thuộc: 3C.7, 3A*
> Done: teacher final + `HTDApi.exportSessionCsv` → `/admin/reports/{id}/export`; student `game_ended` podium.

### Phase 3B — UI polish (sau 3C + 3D)
- [x] 3B.1 **Student mobile:** style/layout theo `docs/APP_STYLE.md` + mockup
> Done: tokens `shared.css`, MC labels, timer shake, waiting pulse.
- [x] 3B.2 **Teacher web:** style/layout theo `prototype/reference/teacher-website*.png`
> Done: sidebar active accent, room card gradient/shadow.
- [x] 3B.3 Keyboard động từ `keyboard_config` trên màn HS (thay mock tĩnh)
> Done: `keyboard-runtime.js` + CSS; fallback static nếu không có config.
- [x] 3B.4 Teacher presentation mode đồng bộ server events
> Done: sync `new_question`/`game_started`/`game_ended`; presentation tick timer backend.

### Phase 3A — Integration (wire UI ↔ backend) — đã xong plumbing
- [x] 3A.0 Integration layer: `config.js`, `api.js`, `socket.js`, `game-adapter.js`, `backend-bridge.js` — *phụ thuộc: Phase 2*
> Done: Lớp tích hợp tách khỏi UI; prototype mount tại `/app/` qua Docker.
- [x] 3.1 Join screen → API PIN check → open Socket.io → NTP sync — *phụ thuộc: 1.8, 2.3*
> Done: `GET /api/rooms/{pin}` + `join_room` + NTP 3 vòng; demo mode `?demo=1`.
- [x] 3.2 Waiting room → listen for `game_started` — *phụ thuộc: 2.2*
> Done: `HTDBridge.on('gameStarted')` thay polling localStorage.
- [x] 3.3 Game screen → `new_question`, submit qua WS — *phụ thuộc: 2.4, 2.5*
> Done: MC + formula/structured cơ bản qua adapter; timer theo `server_time`.
- [x] 3.4 Host screen → tạo session Laravel + điều khiển WS — *phụ thuộc: 1.8, 2.5*
> Done: `POST /api/game-sessions`, host join, Start/Next/End qua WS.
- [x] 3.5 Final screen → `game_ended`, export CSV — *phụ thuộc: 2.5, 1.9*
> Done: `game_ended` podium HS + GV; export CSV qua admin reports (3D.3).

### Phase 3 — Legacy checklist
> Xem 3A (done), **3C** (admin), **3D** (teacher+student), **3B** (polish).

### Phase 4 — Full local testing
> **Chỉ bắt đầu khi Phase 3 xong** (3C + 3D + 3B tối thiểu chức năng; polish có thể song song nhưng test nghiêm túc sau khi luồng đủ).
- [ ] 4.1 Full play-through test, 1 room — *depends on: all of Phase 3*
- [ ] 4.2 Load test: 10 rooms × 50 students (~500 concurrent connections) — *depends on: 4.1*
- [ ] 4.3 Reconnect test — *depends on: 4.1*
- [ ] 4.4 Double-submit & clock skew test — *depends on: 4.1*

---

## TASK DETAILS

### 0.1 — Write `docker-compose.yml`
**What to do:** 4 services: `mysql` (`mysql:8`), `redis` (`redis:7-alpine`), `ws-server` (build from `./ws-server`, host port `38581`), `php-admin` (Laravel — `serversideup/php:8.2-fpm-nginx`, host port `38480`). Named volume for MySQL. Host ports use block `38xxx` to avoid conflicts with other local Docker stacks.

**Acceptance criteria:** `docker-compose up` runs with no errors, `docker ps` shows all 4 containers `Up`, `http://localhost:38480` and `ws://localhost:38581` are both reachable.

---

### 0.2 — `.env` file
**What to do:** Copy the environment variable table from SHARED CONVENTIONS exactly.

**Acceptance criteria:** Change one value, restart the containers, the system picks up the change without any code edit.

---

### 0.3 — Verify containers can reach each other
**What to do:** From `ws-server`: `redis-cli -h redis ping` → `PONG`. From `php-admin`: Eloquent connects to MySQL via host `mysql`.

**Acceptance criteria:** No `ECONNREFUSED` errors between any services. First boot: run `docker compose exec php-admin php artisan migrate --force` once so Laravel session tables exist.

---

### 1.1 — Migrations & `docs/DATA_MODEL.md`
**Before starting:** read [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) — schema is already locked; implement migrations to match it exactly.

**Files to create:** Laravel migration files under `php-admin/database/migrations/`. Update `docs/DATA_MODEL.md` only if implementation diverges.

**What to do:** Implement 8 app tables (+ extend Laravel `users`):

- **`users`** — add `role ENUM('admin','teacher') DEFAULT 'teacher'` to existing Laravel table
- **`keyboards`** — id, name, subject NULL, config JSON, timestamps
- **`games`** — id, name, description, created_by FK→users, timestamps
- **`quizzes`** — id, game_id FK, keyboard_id FK, name, subject, grade, sort_order, is_active, timestamps (delete game: RESTRICT)
- **`questions`** — id, quiz_id FK, **content LONGTEXT** (HTML), answer_type ENUM('mc','formula','structured'), options JSON, correct_index, correct_answer_normalized, input_mode, template JSON, correct_answer JSON, time_limit_seconds, sort_order, timestamps
- **`game_sessions`** — id, pin CHAR(6) UNIQUE, host_id FK, game_id FK, status ENUM, started_at, ended_at, timestamps
- **`game_results`** — id, session_id FK, student_name, player_token NULL, score, rank, timestamps
- **`session_answers`** — id, session_id FK, question_id FK, student_name, answer_submitted JSON, is_correct, score_earned, answered_at; UNIQUE(session_id, question_id, student_name)

ERD in `docs/DATA_MODEL.md`:
```
erDiagram
  USERS ||--o{ GAMES : creates
  USERS ||--o{ GAME_SESSIONS : hosts
  GAMES ||--o{ QUIZZES : contains
  KEYBOARDS ||--o{ QUIZZES : "used by"
  QUIZZES ||--o{ QUESTIONS : contains
  GAMES ||--o{ GAME_SESSIONS : "played as"
  GAME_SESSIONS ||--o{ GAME_RESULTS : produces
  GAME_SESSIONS ||--o{ SESSION_ANSWERS : records
  QUESTIONS ||--o{ SESSION_ANSWERS : answered
```

**Acceptance criteria:** `SHOW TABLES;` lists all app tables; FK violations rejected. `docs/DATA_MODEL.md` matches live schema.

---

### 1.2 — Seed sample data
**What to do:** 2 sample keyboards (e.g. "Inorganic", "Organic"), 1 game ("Semester 1 review"), 2-3 quizzes assigned to that game + a keyboard, 5-10 questions per quiz, 1 test teacher account.

**Acceptance criteria:** Querying each table returns readable data with the correct relationships (a quiz correctly belongs to its game and keyboard). Logging in with the seeded account works in 1.4.

---

### 1.3 — Laravel MySQL connection
**What to do:** Point Laravel's `.env` at the variables from SHARED CONVENTIONS, create Eloquent Models for all app tables (8 tables + extended User).

**Acceptance criteria:** `php artisan tinker` running `Game::all()` returns data with no errors.

---

### 1.4 — Teacher auth
**What to do:** Use Laravel's built-in auth scaffolding (session-based), bcrypt via `Hash::make`, Laravel's default CSRF token (`@csrf`), 8-hour session timeout.

**Acceptance criteria:** Correct login → reaches dashboard. Wrong login → error. Request missing CSRF → 419. Expired session → redirect to login.

---

### 1.5 — Keyboard CRUD & `docs/KEYBOARD_SCHEMA.md`
**Before starting:** check whether `docs/KEYBOARD_SCHEMA.md` already exists.

**What to do:** API to create/edit/delete a keyboard. Save `name` + `subject` to columns; layout JSON to `config` per [`docs/KEYBOARD_SCHEMA.md`](docs/KEYBOARD_SCHEMA.md) (row-based layout from `prototype/keyboard-editor.js`, not the old `tabs[]` format). Validate with editor rules (MAX_UNITS=10, space row at end, delete/space/send required). Default `smart_context` if omitted.

Example `config` skeleton:
```json
{
  "schema_version": 1,
  "defaults": { "keySize": "M", "fontSize": "M", "textColor": "#000000", "background": "#FFFFFF", "border": "#D0D0D0" },
  "rows": [],
  "smart_context": { "after_element": "subscript", "after_plus": "coefficient" }
}
```
Write this structure into `docs/KEYBOARD_SCHEMA.md` (this is a living document — every time a new keyboard type has a different structure, update this file, don't create a separate description file per keyboard type).

**Acceptance criteria:** Create/edit/delete a keyboard via the API succeeds. `docs/KEYBOARD_SCHEMA.md` describes the `config` JSON structure well enough for a different AI to read it and understand it exactly, without looking at the code.

---

### 1.6 — Game CRUD
**What to do:** API to create/edit/delete a game (just `name`, `description` — quizzes aren't assigned at this step).

**Acceptance criteria:** curl tests: create/edit/delete a game succeeds, data is correct in the DB.

---

### 1.7 — Quiz + Question CRUD
**What to do:** API to create/edit/delete a quiz — must select an existing `game_id` and an existing `keyboard_id`. API to create/edit/delete questions belonging to a quiz. Validation: `game_id` and `keyboard_id` must exist before a quiz can be created; deleting a game must not orphan its quizzes — either block the delete while quizzes are still assigned, or cascade (pick one, document the choice clearly in a code comment and in `docs/DATA_MODEL.md`).

**Acceptance criteria:** Creating a quiz correctly links it to the chosen game and keyboard. Querying it back shows the correct relationships. Creating a quiz with a non-existent `game_id` is rejected with a clear error.

---

### 1.8 — "Create new game session" endpoint
**What to do:** Teacher picks a `game_id` → generate a unique 6-digit PIN (check against `game_sessions` currently `waiting`/`playing`) → insert into `game_sessions` → write Redis `room:<PIN>` (Hash: `status=waiting`, `game_id`, TTL 7200s).

**Acceptance criteria:** Calling the API returns a valid, non-duplicate PIN. `redis-cli HGETALL room:<PIN>` shows the correct data, TTL ~7200.

---

### 1.9 — Score management & reporting
**What to do:** API to view: list of past `game_sessions` (by game, by date), per-session detail of each student's score (from `game_results`), and an aggregate per student if the same student played multiple sessions (grouped by `student_name` — note this is not a login account, so aggregation is only approximate by name).

**Acceptance criteria:** The list of past sessions is viewable; clicking into one session shows the exact score table stored in `game_results`, matching the final leaderboard from when the game was played.

---

### 2.1 — Setup Node.js + Socket.io + Redis adapter, bootstrap `docs/API_CONTRACTS.md`
**Before starting:** check whether `docs/API_CONTRACTS.md` already exists.

**Files to create:** `ws-server/package.json`, `ws-server/index.js`, `ws-server/ws/redis.js`, `docs/API_CONTRACTS.md`

**What to do:** Install `socket.io`, `@socket.io/redis-adapter`, `ioredis`. Initialize the Socket.io server, attach the Redis adapter (`io.adapter(createAdapter(pubClient, subClient))`). Create `docs/API_CONTRACTS.md` with a skeleton: the WS event table (copied from SHARED CONVENTIONS as a starting point), the Redis key table, and the list of PHP endpoints — later tasks (1.x, 2.x, 3.x) will extend/update this file as new events or endpoints are added, instead of rewriting it from scratch.

**Acceptance criteria:** `node index.js` runs, logs "Connected to Redis" + "Socket.io listening on 8080". Stopping Redis mid-session doesn't crash the server. `docs/API_CONTRACTS.md` exists with the initial skeleton filled in.

---

### 2.2 — Room manager
**File to create:** `ws-server/ws/room.js`

**What to do:** `join_room {pin, name}` → validate the PIN exists in Redis → `socket.join(pin)` (Socket.io groups clients by room automatically, no need to manage a list manually) → add to `room:<PIN>:players`. On unexpected disconnect: mark as `disconnected`, keep the state so reconnecting with the same `pin`+`name` restores the correct position.

**Acceptance criteria:** 2 clients join the same PIN → both appear in that Socket.io room (`io.sockets.adapter.rooms.get(pin)` shows both). Closing a tab and reopening with the same name → state is restored correctly, no score lost, not treated as a new player.

---

### 2.3 — NTP time sync
**File to create:** `ws-server/ws/ntp.js`

**What to do:** Same design as before: `ntp_ping{t0}` → server replies `ntp_pong{t0,t1,t2}`, client repeats 3 times and takes the median offset. On submit, compute `hybrid_timestamp`; if it deviates >500ms from server time, reject with a clear message.

**Acceptance criteria:** Offset is reasonable locally (tens of ms). Simulating a >500ms clock skew results in a rejection with a specific message.

---

### 2.4 — Gameplay & scoring
**Files to create:** `ws-server/ws/gameplay.js`, `ws-server/ws/scoring.js`

**What to do:** `submit_answer{question_id, answer, hybrid_timestamp}` → `SADD submitted:<PIN>:<question_id>` to block double-submit → compute the score:
```
score = 1000 × (time_remaining / time_limit) × accuracy_bonus
streak of ≥3 correct in a row → +50 per subsequent question
```
Write via `ZINCRBY leaderboard:<PIN>`. Return `question_result` to just the client that submitted.

**Acceptance criteria:** Submitting correctly at second 3 of a 30-second question → ~900 points. Submitting twice for the same question → the second attempt is blocked. `ZRANGE leaderboard:<PIN> 0 -1 WITHSCORES` returns the correct order.

---

### 2.5 — Real-time broadcast
**What to do:** Use Socket.io's `io.to(pin).emit(...)` (the Redis adapter from 2.1 already makes this correct regardless of which worker a client is connected to — no need to hand-roll a pub/sub mechanism) to send `new_question`, `leaderboard_update`, `submit_count_update`, `game_ended` to the right room.

**Acceptance criteria:** 3 student tabs + 1 host tab on the same PIN — when the host clicks Next or time runs out, all 3 student tabs receive `new_question` nearly simultaneously.

---

### 3.1 — Join screen
**What to do:** Call the API to check the PIN is valid → open `io('http://localhost:8080')` (Socket.io client) → send `join_room` → run NTP sync.

**Acceptance criteria:** Wrong PIN → error shown on the existing UI. Correct PIN → moves to the Waiting Room, NTP offset is computed before the screen transition.

---

### 3.2 — Waiting room
**What to do:** Listen for `game_started`, auto-transition the screen.

**Acceptance criteria:** Host clicks Start → every student tab in the waiting room transitions in under 1 second.

---

### 3.3 — Game screen (student)
**What to do:** Receive `new_question` (includes `keyboard_config`) → render the correct keyboard type for the current quiz (not a fixed keyboard — each quiz may differ) → run the timer based on `server_time` + offset → submit → lock input → receive `question_result`, `leaderboard_update`.

**Acceptance criteria:** The keyboard displayed matches the current quiz's `keyboard_config` (switching to a different quiz in the same game → the keyboard changes if the two quizzes use different keyboards). Timer doesn't drift. Can't submit twice.

---

### 3.4 — Host screen
**What to do:** Create the game via Laravel (1.8) → open Socket.io as host → show `submit_count_update` → Next/End buttons send commands via Socket.io.

**Acceptance criteria:** Submit count increments correctly in real time. Next/End work under the correct conditions.

---

### 3.5 — Final screen
**File to create:** `php-admin/api/export-csv.php` (or the equivalent Laravel route)

**What to do:** Listen for `game_ended` → show the top-3 podium. The Export CSV button calls an endpoint that reads `game_results` for that `session_id` and returns a downloadable CSV (reuse the logic from task 1.9).

**Acceptance criteria:** The final board matches the Redis leaderboard before its TTL expires. The downloaded CSV opens correctly with the right columns and data.

---

### 3C.1 — Dashboard layout + navigation
**What to do:** Replace `dashboard.blade.php` placeholder with admin shell: sidebar nav (Keyboards, Games, Quizzes, Sessions, Reports), header with user + logout. Web desktop layout (min width ~1024px).

**Acceptance criteria:** After login, navigate all admin sections from sidebar. No API-only placeholder page.

---

### 3C.2 — Keyboard CRUD UI
**Before starting:** read `docs/KEYBOARD_SCHEMA.md`.

**What to do:** Blade views for list/create/edit/delete keyboard. Form field `config` as JSON or integrate/adapt `prototype/keyboard-editor.html` patterns. Call existing `/api/keyboards` or use controllers directly in web routes.

**Acceptance criteria:** Create/edit/delete keyboard on UI; `config` validates per schema; data persists in `keyboards` table.

---

### 3C.3 — Game CRUD UI
**What to do:** List/create/edit/delete games (`name`, `description`).

**Acceptance criteria:** CRUD works on UI; RESTRICT delete when quizzes still assigned (show clear error).

---

### 3C.4 — Quiz CRUD UI
**What to do:** Quiz form must select existing `game_id` and `keyboard_id` (dropdowns from DB).

**Acceptance criteria:** Quiz saved with correct FKs; list shows parent game and keyboard name.

---

### 3C.5 — Question CRUD UI
**What to do:** Nested under quiz: support `answer_type` = `mc` | `formula` | `structured` with correct fields (`options`, `correct_index`, `correct_answer_normalized`, `template`, `input_mode`, `time_limit_seconds`, HTML `content`).

**Acceptance criteria:** Create at least one question of each type via UI; validation errors shown clearly.

---

### 3C.6 — Create game session from admin
**What to do:** Page or modal: pick a Game → `POST /api/game-sessions` → show PIN + link/button **Mở màn host** → ` /app/teacher.html` (pass `game_id` or session context if needed).

**Acceptance criteria:** PIN created without curl; Redis `room:{pin}` exists; host page can use that session.

---

### 3C.7 — Reports + CSV export
**What to do:** List past `game_sessions`, drill into per-student scores, download CSV (reuse ReportController logic).

**Acceptance criteria:** After a played session, scores visible and CSV downloads with correct columns.

---

### 3D.1 — Teacher host with admin data
**What to do:** Remove hardcoded `defaultGameId` in `config.js` / `createTeacherRoom`; teacher selects or receives game from admin session flow.

**Acceptance criteria:** Room created from admin-chosen game; questions in play match that game's quizzes in DB.

---

### 3D.2 — Student with all question types
**What to do:** Verify `game-adapter.js` renders MC, formula, structured from admin-created questions end-to-end.

**Acceptance criteria:** One session plays through admin-created mixed quiz without fake data.

---

### 3D.3 — Final screen + CSV button
**What to do:** Wire export on teacher final or admin reports; student final shows `game_ended` leaderboard.

**Acceptance criteria:** CSV matches `game_results`; final podium matches last leaderboard.

---

## PROMPT MẪU — Phase 3C (conversation mới)

```
Phase 3C — Laravel Admin UI đầy đủ (web desktop).

Đọc: local-deployment-plan.md (3C), docs/DATA_MODEL.md, docs/API_CONTRACTS.md, docs/KEYBOARD_SCHEMA.md.

Mục tiêu: thay dashboard placeholder bằng CRUD Blade đủ 3C.1–3C.7.
API/controllers Phase 1 đã có — ưu tiên web UI gọi đúng contract, không đổi schema.

Platform: Admin = web desktop only. KHÔNG polish prototype/teacher.html hay index.html (Phase 3D/3B).

Làm lần lượt 3C.1 → 3C.7; tick checklist + cập nhật docs nếu lệch.
```

---

### 4.1 — Full play-through test (1 room)
**What to do:** 1 Host tab + 2-3 student tabs, play through one sample quiz start to finish.

**Acceptance criteria:** No tab hangs, disconnects, or shows a wrong score. Correct order: Join → Waiting → Question → Result → Leaderboard → (repeat) → Final.

---

### 4.2 — Load test: 10 rooms × 50 students (~500 connections)
**What to do:** Use k6 or Artillery to simulate 10 different PINs, each with 50 clients connecting + joining + submitting nearly simultaneously right at the moment the timer runs out (this is the peak load moment — many submits arrive at once).

**Acceptance criteria:** No client gets dropped. p99 latency from `submit_answer` to `question_result` stays under an acceptable threshold (suggest <200ms locally, record the actual measured number). The `ws-server` container's RAM/CPU doesn't grow abnormally or leak across many consecutive questions. Redis isn't bottlenecked (check `redis-cli INFO` for `connected_clients`, `used_memory`).

---

### 4.3 — Reconnect test
**What to do:** Disconnect the network / refresh 1 student tab mid-question, then reconnect.

**Acceptance criteria:** Returns to the current question correctly, no score lost, not treated as a new player.

---

### 4.4 — Double-submit & clock skew test
**What to do:** Call `submit_answer` twice in a row via the DevTools console. Change the system clock by >500ms and then submit.

**Acceptance criteria:** The second submit is blocked, no extra points awarded. A >500ms clock skew is rejected clearly, not with a generic 500 error.

---

*This file tracks implementation progress. Schema/API/config details live in `docs/` — always read and update those files rather than re-deriving them from scratch. The original technical spec (`dac-ta-ky-thuat-v4`) remains the reference for UI/UX (sections 3, 5) and the roadmap (section 7).*
