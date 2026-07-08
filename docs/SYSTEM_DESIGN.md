# System Design — Hóa Thầy Đạt

> Website đố vui hóa học real-time dạng Kahoot. **Học sinh: mobile only.** Admin + Giáo viên: web desktop.

**Cập nhật lần cuối:** 2026-07-08 (màn HS tại `/join/{pin}`, cùng port admin)

---

## 1. Tổng quan

Hệ thống cho phép giáo viên tạo phòng quiz hóa học realtime, học sinh join bằng PIN (không cần login), trả lời câu hỏi trắc nghiệm hoặc nhập công thức hóa học qua bàn phím ảo, xem bảng xếp hạng sau mỗi câu.

### Actor

| Actor | Vai trò | Platform | Trạng thái |
|---|---|---|---|
| **Admin / GV** | CRUD keyboard, game, quiz, câu hỏi; tạo phòng; báo cáo | **Web desktop** (Laravel `/dashboard`) | API xong; **UI chưa có** (Phase 3C) |
| **Giáo viên (host)** | Host lobby, PIN, điều khiển câu hỏi realtime | **Web desktop** (`/app/teacher.html`) | Integration 3A; polish 3B |
| **Học sinh** | Join PIN, trả lời, leaderboard | **Mobile only** (`/join`, `/join/{pin}`) | Integration 3A; polish 3B |

Admin tạo **source of truth** trong MySQL → teacher/student đọc qua WS/API khi chơi.

---

## 2. Stack production (mục tiêu)

| Thành phần | Công nghệ | Port (local dev) | Vai trò |
|---|---|---|---|
| Admin & API | PHP (Laravel + Nginx) | **38480** | CRUD câu hỏi, auth GV, tạo room → Redis |
| Realtime | Node.js + Socket.io | **38581** | WebSocket multi-worker, gameplay |
| Game state | Redis | **38637** (host) | Room state, leaderboard, TTL 2 giờ |
| Persistence | MySQL 8 | **38306** (host) | games, quizzes, questions (`content` HTML + `answer_type`), sessions, session_answers |

### Sơ đồ kiến trúc

```
Browser/Mobile (Học sinh)          Browser (Giáo viên)
      │ WebSocket                         │ HTTP + WebSocket
      ▼                                   ▼
┌─────────────────────────────────────────────────────────┐
│           Node.js / Go — WebSocket Server               │
│   Port 8080 · Multi-worker · Redis Pub/Sub              │
└─────────────────────────┬───────────────────────────────┘
                          │
          ┌───────────────┴───────────────┐
          ▼                               ▼
┌───────────────────┐         ┌───────────────────────┐
│  Redis (RAM)      │         │  PHP Admin Server     │
│  room:* (state)   │◄────────│  Nginx + PHP-FPM      │
│  leaderboard:*    │ init    │  Port 80/443          │
│  TTL: 2 giờ       │  room   └──────────┬────────────┘
└───────────────────┘                    │
                                         ▼
                              ┌──────────────────────┐
                              │  MySQL / MariaDB     │
                              │  users, questions,   │
                              │  game_results        │
                              └──────────────────────┘
```

---

## 3. Admin modules (PHP)

| Module | Mô tả |
|---|---|
| Quản lý câu hỏi | CRUD: `content` HTML (text+ảnh+video), `answer_type` (mc/formula/structured), thời gian |
| Quản lý bộ đề | Tạo/sửa/xoá bộ câu hỏi, tag môn/lớp, clone |
| Lịch sử game | Danh sách game, kết quả HS, export CSV |
| Auth giáo viên | Session, bcrypt, CSRF, role admin/teacher, expire 8h |
| Tạo game | PHP → Redis: sinh PIN, TTL 2h, redirect GV đến Lobby |
| Lobby / Host | WebSocket realtime (Node/Go), PHP chỉ khởi tạo room |

---

## 4. Redis data model (dự kiến)

| Key pattern | Nội dung | TTL |
|---|---|---|
| `room:{pin}` | Room config, question list, current index, status | 2h |
| `room:{pin}:players` | Hash playerId → {name, avatar, score} | 2h |
| `room:{pin}:leaderboard` | ZSET score ranking | 2h |
| `room:{pin}:submissions:{qIndex}` | ZSET submit time + Hash answers | 2h |

---

## 5. Implementation phases

| Phase | Nội dung | Trạng thái |
|---|---|---|
| **0** | Docker Compose: Redis + MySQL + WS + PHP | **Đã làm** |
| **1** | MySQL schema, PHP auth, CRUD API | **Đã làm** |
| **2** | WebSocket server: room, NTP, scoring | **Đã làm** |
| **3A** | Integration layer (prototype ↔ API/WS) | **Đã làm** (plumbing) |
| **3C** | **Admin UI web đầy đủ** (CRUD → data DB) | **Tiếp theo** |
| **3D** | Teacher web + Student mobile — chức năng với data admin | Chưa làm |
| **3B** | UI polish (teacher web + student mobile) | Chưa làm |
| **4** | Integration test, load test | Sau Phase 3 xong |

```
3A (done) → 3C Admin → 3D Teacher+Student → 3B Polish → Phase 4 Test
```

---

## 6. Prototype vs Production

| Khía cạnh | Prototype hiện tại | Production sau này |
|---|---|---|
| Vị trí | `prototype/` (UI archive) | `public/` hoặc framework app (Phase 3) |
| Stack | HTML + CSS + JS thuần, CDN | Vanilla JS hoặc framework (chưa quyết) |
| Backend | Fake data, `setTimeout` giả event | PHP + WS + Redis + MySQL |
| Join | PIN hardcode `123456` | PIN random từ Redis |
| Avatar | Camera API / skip / random fallback | Upload hoặc random |
| Bàn phím | Mock tĩnh (HTML/CSS, 3 tab visual) | `keyboard.js` + token model |
| Scoring | Hiển thị điểm fake | Kahoot formula + Hybrid Timestamp |
| NTP sync | Không có | 3 vòng ping-pong, reject lệch >500ms |
| Leaderboard | FLIP animation demo | WS broadcast realtime |

---

## 7. Roadmap

| Version | Tính năng |
|---|---|
| **v1.0 MVP** | GV tạo game, HS join PIN, bàn phím hóa học, leaderboard realtime |
| **v1.1** | Ảnh câu hỏi, spectator mode, export CSV, QR join |
| **v1.2** | Chế độ luyện tập, thống kê sai, dashboard phân tích lớp |
| **v2.0** | Mở rộng môn (Toán LaTeX, Vật lý SI), multi-language |
| **v3.0** | AI sinh câu hỏi, adaptive difficulty, API public |

---

## 8. Tài liệu liên quan

- [`docs/DATA_MODEL.md`](DATA_MODEL.md) — Schema DB, ERD, field definitions
- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — Luồng màn hình, scoring, keyboard logic
- [`docs/APP_STYLE.md`](APP_STYLE.md) — Design tokens, layout, component CSS
- [`prototype/index.html`](../prototype/index.html) — UI design sandbox học sinh
