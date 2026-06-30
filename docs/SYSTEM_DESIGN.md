# System Design — Hóa Thầy Đạt

> Website đố vui hóa học real-time dạng Kahoot. Mobile-first.
> Nguồn gốc: [`dac-ta-ky-thuat-v4.docx.md`](../dac-ta-ky-thuat-v4.docx.md) v4.0

**Cập nhật lần cuối:** 2026-06-30

---

## 1. Tổng quan

Hệ thống cho phép giáo viên tạo phòng quiz hóa học realtime, học sinh join bằng PIN (không cần login), trả lời câu hỏi trắc nghiệm hoặc nhập công thức hóa học qua bàn phím ảo, xem bảng xếp hạng sau mỗi câu.

### Actor

| Actor | Vai trò | Trạng thái |
|---|---|---|
| Học sinh | Join phòng, trả lời câu hỏi, xem kết quả | **Đang prototype UI** |
| Giáo viên | Tạo game, host lobby, điều khiển câu hỏi | Chưa implement |
| Admin | CRUD câu hỏi, quản lý tài khoản GV | Chưa implement |

---

## 2. Stack production (mục tiêu)

| Thành phần | Công nghệ | Port | Vai trò |
|---|---|---|---|
| Admin & API | PHP (Nginx + PHP-FPM) | 80/443 | CRUD câu hỏi, auth GV, tạo room → Redis |
| Realtime | Node.js hoặc Go | 8080 | WebSocket multi-worker, gameplay |
| Game state | Redis | — | Room state, leaderboard, TTL 2 giờ |
| Persistence | MySQL / MariaDB | — | users, questions, game_sessions, game_results |

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
| Quản lý câu hỏi | CRUD: text, hình ảnh, đáp án chuẩn hoá, thời gian. Import CSV/Excel |
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
| **0** | Docker Compose: Redis + MySQL + WS container | Chưa làm |
| **1** | MySQL schema, PHP auth, CRUD câu hỏi | Chưa làm |
| **2** | WebSocket server: room, NTP, scoring, Pub/Sub | Chưa làm |
| **3.1–3.3** | Frontend học sinh + bàn phím + game UI | **Prototype UI** (`prototype/index.html`) |
| **3.4** | Frontend giáo viên (host) | Chưa làm |
| **4** | Integration test, load test 50 concurrent | Chưa làm |

---

## 6. Prototype vs Production

| Khía cạnh | Prototype hiện tại | Production sau này |
|---|---|---|
| Vị trí | `prototype/index.html` | `public/` hoặc framework app |
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

- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — Luồng màn hình, scoring, keyboard logic
- [`docs/APP_STYLE.md`](APP_STYLE.md) — Design tokens, layout, component CSS
- [`prototype/index.html`](../prototype/index.html) — UI design sandbox học sinh
