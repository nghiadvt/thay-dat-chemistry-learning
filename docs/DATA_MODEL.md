# Data Model — Hóa Thầy Đạt

> Schema chốt trước Phase 1.1. Migrations Laravel implement theo file này.
> Chi tiết loại câu hỏi/đáp án: [`APP_LOGIC.md`](APP_LOGIC.md) §3.1.

**Cập nhật lần cuối:** 2026-07-08 (QR phòng lưu PNG `qr_path`; chơi lại phòng ended)

---

## 1. ERD

```mermaid
erDiagram
  USERS ||--o{ GAMES : creates
  USERS ||--o{ GAME_SESSIONS : hosts
  GAMES ||--o{ QUIZZES : contains
  KEYBOARDS ||--o{ QUIZZES : "used by"
  QUIZZES ||--o{ QUESTIONS : contains
  QUIZZES }o--o{ TAGS : "tagged with"
  GAMES ||--o{ GAME_SESSIONS : "played as"
  GAME_SESSIONS ||--o{ GAME_RESULTS : "final scores"
  GAME_SESSIONS ||--o{ SESSION_ANSWERS : "per question"
  QUESTIONS ||--o{ SESSION_ANSWERS : answered
```

---

## 2. Quy ước chung

| Quy tắc | Giá trị |
|---|---|
| `users` | Mở rộng bảng Laravel mặc định — **không** tạo bảng mới |
| Login giáo viên | Email + password (Laravel Auth) |
| Nội dung câu hỏi | **1 cột** `content` (LONGTEXT HTML) — text + ảnh + video qua rich text editor |
| HTML sanitize | Bắt buộc server-side trước khi lưu (HTMLPurifier hoặc tương đương) |
| Xóa `games` có quiz | **RESTRICT** — chặn xóa, bắt xóa/chuyển quiz trước |
| Xóa `keyboards` đang dùng | **RESTRICT** |
| 1 `game_session` (MVP) | Chơi **1 quiz** đã chọn (`quiz_id`); `game_id` giữ để nhóm/báo cáo. Session cũ không có `quiz_id` → ws chơi toàn bộ quiz trong game |

---

## 3. Bảng `users` (Laravel + mở rộng)

Bảng đã có từ Laravel default migration. **Chỉ thêm cột:**

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `role` | `ENUM('admin','teacher') NOT NULL DEFAULT 'teacher'` | Phân quyền admin dashboard |

Giữ nguyên: `id`, `name`, `email` UNIQUE, `password`, `remember_token`, `email_verified_at`, `timestamps`.

---

## 4. Bảng `keyboards`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `name` | VARCHAR(255) | VD: "Bàn phím hóa vô cơ" |
| `subject` | VARCHAR(64) NULL | VD: "chemistry" — chuẩn bị v2.0 đa môn |
| `config` | JSON | Layout bàn phím — cấu trúc `rows[]` + `defaults` + `smart_context` — xem [`KEYBOARD_SCHEMA.md`](KEYBOARD_SCHEMA.md). `smart_context` quy định hành vi nhập số (hệ số vs chỉ số dưới) khi HS gõ và trong **Test** overlay của editor |
| `preview_path` | VARCHAR(255) NULL | Đường dẫn tương đối file PNG preview (disk `public`) — VD: `keyboards/06-07-2026-ban-phim-hoa-vo-co.png` |
| `created_at`, `updated_at` | TIMESTAMP | |

**Preview ảnh:** Admin editor chụp DOM bàn phím (`html2canvas`) khi **Save** hoặc lần đầu mở editor (nếu chưa có ảnh) → `POST /api/keyboards/{id}/preview` → lưu `storage/app/public/keyboards/{dd-mm-YYYY}-{slug-ten-ban-phim}.png` (trùng tên trong ngày → thêm `-{id}`). Truy cập qua `/storage/...` (cần `php artisan storage:link`). Model expose `preview_url` (accessor, không lưu DB). Xóa bàn phím → xóa file preview tương ứng.

### Lưu từ Keyboard Editor

| Editor (`keyboard-editor.js`) | DB |
|---|---|
| `data.name` | `keyboards.name` |
| `(chưa có UI)` | `keyboards.subject` — mặc định `chemistry` |
| `{ defaults, rows, smart_context }` | `keyboards.config` |
| Chụp `#kbePhoneKb` (html2canvas) | `keyboards.preview_path` — PNG qua API upload |
| `data.id`, `data.updatedAt` | **Không lưu** — dùng `keyboards.id`, `updated_at` |

**Test overlay (editor):** nút **Test** mô phỏng nhập công thức HS — dùng `config.smart_context` để tự phân loại phím số (`0`–`9`): hệ số (số to) ở đầu công thức / sau `+`; chỉ số dưới (hiển thị `<sub>`) sau nguyên tố / sau `)`. Backspace xóa theo **token** (vd. `Cl` = 1 token). Plain text serialize (vd. `2CO2`) lưu tạm `data-serialized` trên `#kbeTestOutput` — **không** ghi DB; format này khớp `answer` formula gửi WS (xem [`API_CONTRACTS.md`](API_CONTRACTS.md) §9.1).

Prototype hiện lưu localStorage key `htd_chemical_keyboard` (full object). Production: tách `name` → cột, phần còn lại → `config` JSON.

---

## 5. Bảng `games`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `name` | VARCHAR(255) | |
| `description` | TEXT NULL | |
| `created_by` | BIGINT FK → `users.id` NULL | GV tạo game |
| `created_at`, `updated_at` | TIMESTAMP | |

---

## 6. Bảng `quizzes`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `game_id` | BIGINT FK → `games.id` ON DELETE RESTRICT | |
| `keyboard_id` | BIGINT FK → `keyboards.id` ON DELETE RESTRICT | |
| `name` | VARCHAR(255) | |
| `subject` | VARCHAR(64) NULL | |
| `grade` | VARCHAR(32) NULL | VD: "10", "11" |
| `sort_order` | SMALLINT NOT NULL DEFAULT 0 | Thứ tự trong game khi chơi |
| `is_active` | BOOLEAN NOT NULL DEFAULT true | Ẩn quiz không dùng |
| `show_explanation` | BOOLEAN NOT NULL DEFAULT false | Bật → gửi `explanation` trong `question_result` sau khi HS trả lời |
| `shuffle_options` | BOOLEAN NOT NULL DEFAULT false | Bật → xáo trộn thứ tự `options` trắc nghiệm riêng từng HS |
| `created_at`, `updated_at` | TIMESTAMP | |

**Chủ đề (tag):** quan hệ N–N qua `quiz_tag` — xem §6.1.

### 6.1 Bảng `tags` & `quiz_tag`

| Bảng | Cột | Ghi chú |
|---|---|---|
| `tags` | `id`, `name` UNIQUE, `slug` UNIQUE, `timestamps` | Chủ đề: "Hóa vô cơ", "Lớp 10", … |
| `quiz_tag` | `quiz_id` FK, `tag_id` FK | PK composite — 1 quiz nhiều tag |

Admin nhập tag dạng chuỗi phân cách dấu phẩy; lọc quiz theo tag trên `/admin/quizzes`.

---

## 7. Bảng `questions`

Nội dung câu hỏi gộp trong `content`. Loại tương tác học sinh qua `answer_type`.

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `quiz_id` | BIGINT FK → `quizzes.id` ON DELETE CASCADE | |
| `content` | LONGTEXT NOT NULL | HTML: đề text + `<img>` + `<video>` (sanitize trước lưu) |
| `explanation` | LONGTEXT NULL | HTML giải thích đáp án (tuỳ chọn, sanitize trước lưu) |
| `answer_type` | ENUM('mc','essay') NOT NULL | |
| `options` | JSON NULL | `mc`: `["đáp án A", "B", "C", "D"]` — tối thiểu 2, tối đa 6 |
| `correct_index` | TINYINT UNSIGNED NULL | `mc`: index 0-based |
| `correct_answer_normalized` | TEXT NULL | `essay`: đáp án mẫu (so khớp văn bản) |
| `input_mode` | VARCHAR(32) NULL | *(dự phòng — chưa dùng)* |
| `template` | JSON NULL | *(dự phòng — chưa dùng)* |
| `correct_answer` | JSON NULL | *(dự phòng — chưa dùng)* |
| `time_limit_seconds` | INT NOT NULL DEFAULT 30 | |
| `points` | SMALLINT UNSIGNED NOT NULL DEFAULT 1 | Điểm cơ bản khi trả lời đúng (1–100) |
| `sort_order` | SMALLINT NOT NULL DEFAULT 0 | Thứ tự trong quiz |
| `is_active` | BOOLEAN NOT NULL DEFAULT true | Ẩn câu hỏi khi tắt (không đưa vào phòng chơi) |
| `created_at`, `updated_at` | TIMESTAMP | |

### Validation theo `answer_type`

| `answer_type` | Field bắt buộc |
|---|---|
| `mc` | `options` (≥2), `correct_index` |
| `essay` | `correct_answer_normalized` (đáp án mẫu) |

### Ví dụ `content` (HTML)

```html
<p>Quan sát ống nghiệm như hình. Dung dịch có màu gì?</p>
<img src="/storage/questions/q42.png" alt="ống nghiệm">
```

```html
<p>Quan sát video thí nghiệm:</p>
<video src="/storage/questions/demo.mp4" poster="/storage/questions/demo-poster.jpg" controls></video>
```

---

## 8. Bảng `game_sessions`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `pin` | CHAR(6) NOT NULL UNIQUE | 6 chữ số |
| `qr_path` | VARCHAR(255) NULL | Ảnh QR join (`/join/{pin}`) — PNG trên disk `public`, VD: `sessions/123456.png`. Tạo khi **Tạo phòng**; backfill khi mở trang host nếu thiếu. Model expose `qr_url` (accessor) |
| `name` | VARCHAR(255) NULL | Tên phòng do GV đặt (hiển thị danh sách) |
| `host_id` | BIGINT FK → `users.id` | GV host |
| `game_id` | BIGINT FK → `games.id` | Game chứa quiz (denormalized) |
| `quiz_id` | BIGINT FK → `quizzes.id` NULL | Quiz được chơi trong phòng; NULL = legacy (chơi cả game) |
| `status` | ENUM('waiting','playing','ended') NOT NULL DEFAULT 'waiting' | |
| `is_active` | BOOLEAN NOT NULL DEFAULT true | Tắt → xóa Redis room, HS không join được |
| `started_at` | TIMESTAMP NULL | Khi GV bấm Start |
| `ended_at` | TIMESTAMP NULL | Khi game kết thúc |

**Chơi lại:** Admin `POST .../reset` hoặc API `POST /api/game-sessions/{id}/reset` — đặt lại `status=waiting`, xóa state Redis (players, leaderboard, plan…), **giữ** bản ghi `game_results` / `session_answers` của lần chơi trước.
| `created_at`, `updated_at` | TIMESTAMP | |

---

## 9. Bảng `game_results`

Tổng kết cuối session (1 dòng / học sinh / session).

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `session_id` | BIGINT FK → `game_sessions.id` ON DELETE CASCADE | |
| `student_name` | VARCHAR(20) NOT NULL | Không login — nickname |
| `player_token` | CHAR(36) NULL | UUID reconnect (Phase 2) |
| `score` | INT NOT NULL DEFAULT 0 | Tổng điểm |
| `rank` | INT NOT NULL | Hạng cuối |
| `created_at`, `updated_at` | TIMESTAMP | |

---

## 10. Bảng `session_answers`

Chi tiết từng câu — phục vụ scoring, CSV, thống kê sai (v1.2).

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT PK | |
| `session_id` | BIGINT FK → `game_sessions.id` ON DELETE CASCADE | |
| `question_id` | BIGINT FK → `questions.id` | |
| `student_name` | VARCHAR(20) NOT NULL | |
| `answer_submitted` | JSON NULL | Đáp án HS gửi (index / string / structured). Câu `formula`: chuỗi plain text đã serialize từ token bàn phím (vd. `"2CO2"`, không Unicode subscript) |
| `is_correct` | BOOLEAN NOT NULL | |
| `score_earned` | INT NOT NULL DEFAULT 0 | Điểm câu này |
| `answered_at` | TIMESTAMP NOT NULL | |

**Unique:** `(session_id, question_id, student_name)` — chống double-submit ở DB.

---

## 11. Mở rộng tương lai (chưa implement)

| Version | Thay đổi schema |
|---|---|
| v1.2 Luyện tập | Bảng `practice_attempts` riêng |
| v2.0 LaTeX/đa môn | `answer_type` mới hoặc keyboard `subject` khác |
| v3.0 AI | `questions.source`, `questions.ai_metadata` JSON |
| Chọn subset quiz/session | Bảng `game_session_quizzes` |

---

## 12. Tài liệu liên quan

- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — `answer_type`, payload WebSocket
- [`docs/API_CONTRACTS.md`](API_CONTRACTS.md) — endpoints & events (task 2.1)
- [`local-deployment-plan.md`](../local-deployment-plan.md) — checklist Phase 1
