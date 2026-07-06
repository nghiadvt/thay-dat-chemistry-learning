# Hóa Thầy Đạt — Chemistry Quiz Real-time

Website đố vui hóa học real-time dạng Kahoot. **Học sinh: mobile only.** Admin + Giáo viên: web desktop.

## Tài liệu

| File | Nội dung |
|---|---|
| [`docs/SYSTEM_DESIGN.md`](docs/SYSTEM_DESIGN.md) | Kiến trúc, stack, phases |
| [`docs/APP_LOGIC.md`](docs/APP_LOGIC.md) | Luồng màn hình, scoring, Phase 3 order |
| [`docs/APP_STYLE.md`](docs/APP_STYLE.md) | Design tokens — **chỉ học sinh mobile** |
| [`local-deployment-plan.md`](local-deployment-plan.md) | Checklist triển khai (3C admin → 3D → 3B → Phase 4) |
| [`dac-ta-ky-thuat-v4.docx.md`](dac-ta-ky-thuat-v4.docx.md) | Đặc tả kỹ thuật gốc (archive) |

## UI surfaces

| Surface | Platform | URL local |
|---|---|---|
| **Admin** | Web desktop | `http://localhost:38480/dashboard` (Phase **3C** — CRUD đầy đủ) |
| **Teacher host** | Web desktop | `http://localhost:38480/app/teacher.html` |
| **Student** | Mobile only | `http://localhost:38480/app/index.html` |

**Thứ tự Phase 3:** 3A (integration, done) → **3C Admin** → 3D Teacher+Student → 3B Polish → **Phase 4** test.

## Prototype / integration

**Demo localStorage (cũ):** `cd prototype && python3 -m http.server 8888` — thêm `?demo=1` nếu mở qua backend mount.

**Tích hợp backend (Phase 3A):** prototype được mount tại Laravel:

| URL | Mô tả |
|---|---|
| `http://localhost:38480/app/index.html` | Học sinh — **mobile only** |
| `http://localhost:38480/app/teacher.html` | Giáo viên host — **web desktop** |
| `http://localhost:38480/login` | Đăng nhập GV (`teacher@hoadat.local` / `password123`) |

**Luồng chơi thật:** Login GV → `teacher.html` → Tạo phòng → HS mở `index.html` → nhập PIN → GV Start → HS trả lời → GV **Câu tiếp theo** → Final.

**Integration layer:** `prototype/js/config.js`, `api.js`, `socket.js`, `game-adapter.js`, `backend-bridge.js` — chi tiết mapping trong [`docs/APP_LOGIC.md`](docs/APP_LOGIC.md) §8.

## Hạ tầng local (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec php-admin php artisan migrate --force   # first boot only
```

| Service | URL / Port host | Cách kiểm tra |
|---|---|---|
| PHP Admin (Laravel) | `http://localhost:38480` | Mở trình duyệt → trang chủ Laravel |
| WebSocket (Socket.io) | `http://localhost:38581` | `curl http://localhost:38581/health` → `{"ok":true}`; client JS dùng `io('http://localhost:38581')` — **không** gõ `ws://` trên thanh địa chỉ |
| MySQL | `localhost:38306` | DBeaver / `mysql -h 127.0.0.1 -P 38306` |
| Redis | `localhost:38637` | `redis-cli -p 38637 ping` |

**Lưu ý Socket.io:** Client JS dùng `io('http://localhost:38581')`. Kiểm tra nhanh:

```bash
curl http://localhost:38581/health
```

## Cursor AI (rules + hooks)

- `.cursor/rules/` — auto-load ngữ cảnh + bảng định tuyến `docs/`
- `.cursor/hooks.json` — nhắc cập nhật living docs khi sửa code (postToolUse + stop)

## Trạng thái dự án

- [x] Docs: SYSTEM_DESIGN, APP_LOGIC, APP_STYLE, DATA_MODEL, API_CONTRACTS
- [x] Prototype UI học sinh + giáo viên (`prototype/`)
- [x] Phase 0–2: Docker, Laravel API, WebSocket gameplay
- [x] Phase 3A: Integration layer (plumbing)
- [ ] **Phase 3C: Admin UI web đầy đủ** ← tiếp theo
- [ ] Phase 3D: Teacher (web) + Student (mobile) với data admin
- [ ] Phase 3B: UI polish
- [ ] Phase 4: Test tổng thể + load test
