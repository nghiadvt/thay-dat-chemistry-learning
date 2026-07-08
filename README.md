# Hóa Thầy Đạt — Chemistry Quiz Real-time

Website đố vui hóa học real-time dạng Kahoot. **Học sinh: mobile only.** Giáo viên: web admin Laravel.

## Tài liệu

| File | Nội dung |
|---|---|
| [`docs/SYSTEM_DESIGN.md`](docs/SYSTEM_DESIGN.md) | Kiến trúc, stack, phases |
| [`docs/APP_LOGIC.md`](docs/APP_LOGIC.md) | Luồng màn hình, scoring |
| [`docs/APP_STYLE.md`](docs/APP_STYLE.md) | Design tokens — học sinh mobile |
| [`local-deployment-plan.md`](local-deployment-plan.md) | Checklist triển khai |
| [`dac-ta-ky-thuat-v4.docx.md`](dac-ta-ky-thuat-v4.docx.md) | Đặc tả kỹ thuật gốc (archive) |

## UI surfaces

| Surface | Platform | URL local |
|---|---|---|
| **Admin (GV)** | Web desktop | `http://localhost:38480/admin` |
| **Học sinh** | Mobile | `http://localhost:38480/home` (trang chủ) · `/join` (chơi game) |

**Luồng GV:** Login → Admin → soạn game/quiz/bàn phím → **Phòng chơi** → tạo phòng → **Vào phòng** (điều khiển + link HS).

**Luồng HS:** Trang chủ `/home` → **Chơi game** → nhập PIN / quét QR → chơi. Deep-link QR: `/join/123456`. (Cùng port `38480` với admin; WS ở `38581`.)

**Trải nghiệm app (không thanh URL):** học sinh **Thêm vào Màn hình chính** trên điện thoại (PWA). Mở link thường trong trình duyệt vẫn hiện thanh địa chỉ — đó là giới hạn bảo mật của trình duyệt, không tắt được bằng code.

**Link/QR công khai** lấy từ `APP_URL` trong root `.env` (không phụ thuộc host trình duyệt admin). Test phone cùng Wi‑Fi: đặt `APP_URL` / `WS_PUBLIC_URL` = IP LAN rồi recreate `php-admin`. Production: `https://your-domain`.

Login seed: `teacher@hoadat.local` / `password123`

## Hạ tầng local (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec php-admin php artisan migrate --force   # first boot only
docker compose exec php-admin php artisan db:seed --force   # tài khoản demo + dữ liệu mẫu
```

| Service | URL / Port |
|---|---|
| PHP Admin | `http://localhost:38480` |
| WebSocket | `http://localhost:38581` |
| MySQL | `localhost:38306` |
| Redis | `localhost:38637` |

## Trạng thái dự án

- [x] Phase 0–2: Docker, Laravel API, WebSocket
- [x] Phase 3A–3D, 3B, 4 (xem `local-deployment-plan.md`)
- [x] Admin native: bàn phím + host phòng trong `/admin` (không iframe `teacher.html`)

Prototype `/app/` vẫn phục vụ **học sinh mobile**; GV dùng admin Laravel.
