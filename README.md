# Hóa Thầy Đạt — Chemistry Quiz Real-time

Website đố vui hóa học real-time dạng Kahoot. Mobile-first.

## Tài liệu

| File | Nội dung |
|---|---|
| [`docs/SYSTEM_DESIGN.md`](docs/SYSTEM_DESIGN.md) | Kiến trúc hệ thống, stack, phases |
| [`docs/APP_LOGIC.md`](docs/APP_LOGIC.md) | Luồng màn hình, scoring, keyboard logic |
| [`docs/APP_STYLE.md`](docs/APP_STYLE.md) | Design tokens, layout, components |
| [`dac-ta-ky-thuat-v4.docx.md`](dac-ta-ky-thuat-v4.docx.md) | Đặc tả kỹ thuật gốc (archive) |

## Prototype UI (giai đoạn hiện tại)

Prototype tách riêng học sinh và giáo viên, đồng bộ dữ liệu qua `localStorage`.

```bash
cd prototype && python3 -m http.server 8888
```

| URL | Mô tả |
|---|---|
| `http://localhost:8888/index.html` | App học sinh (mobile thực tế) |
| `http://localhost:8888/teacher.html` | Bảng điều khiển giáo viên (website desktop) — tạo phòng, QR, PIN, danh sách HS |

**Cấu trúc file:**

```
prototype/
├── index.html          # Học sinh
├── teacher.html        # Giáo viên
├── css/
│   ├── shared.css
│   ├── student.css
│   └── teacher.css
└── js/
    ├── shared.js       # localStorage, QR, fake data
    ├── student.js
    └── teacher.js
```

**Luồng demo:** Mở `teacher.html` → Tạo phòng → Mở `index.html` trên điện thoại → Nhập PIN / quét QR → Chờ → GV bấm Bắt đầu.

## Trạng thái dự án

- [x] Docs: SYSTEM_DESIGN, APP_LOGIC, APP_STYLE
- [x] Prototype UI học sinh (`prototype/index.html`)
- [x] Prototype UI giáo viên (`prototype/teacher.html`)
- [ ] Bàn phím hóa học logic (token model) — sau khi chốt UI
- [ ] Backend: PHP + WebSocket + Redis + MySQL

http://localhost:8888/prototype/
http://localhost:8888/prototype/teacher.html