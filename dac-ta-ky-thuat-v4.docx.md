**ĐẶC TẢ KỸ THUẬT**

**WEBSITE ĐỐ VUI HÓA HỌC REAL-TIME — DẠNG KAHOOT**

*Phiên bản 4.0  ·  Thiết kế cho AI Multi-Agent Implementation  ·  Mobile-first*

# **1\. Yêu cầu & Tech Stack (tóm tắt)**

📌  Stack quyết định: PHP (admin dashboard \+ MySQL) \+ Node.js hoặc Go (WebSocket server) \+ Redis (gameplay state). Xem v3.0 để biết so sánh chi tiết.

# **2\. Kiến trúc tổng thể**

  Browser/Mobile (Học sinh)          Browser (Giáo viên)  
        │  WebSocket                       │  HTTP \+ WebSocket  
        │                                  │  
        ▼                                  ▼  
  ┌─────────────────────────────────────────────────────────┐  
  │              Node.js / Go — WebSocket Server           │  
  │   Port 8080  ·  Multi-worker  ·  Redis Pub/Sub         │  
  └─────────────────────────┬───────────────────────────────┘  
                            │  
          ┌─────────────────┴───────────────────┐  
          ▼                                     ▼  
  ┌───────────────────┐             ┌───────────────────────┐  
  │  Redis (RAM)      │             │  PHP Admin Server     │  
  │  room:\*  (state)  │             │  Nginx \+ PHP-FPM      │  
  │  leaderboard:\*    │◄────────────│  Port 80/443          │  
  │  TTL: 2 giờ       │  init room  └──────────┬────────────┘  
  └───────────────────┘                        │  
                                               ▼  
                                    ┌──────────────────────┐  
                                    │  MySQL / MariaDB     │  
                                    │  users, questions,   │  
                                    │  game\_results        │  
                                    └──────────────────────┘

# **3\. Luồng hoạt động của ứng dụng (User Flow)**

| Vai trò | Màn hình | Hành động | Mô tả chi tiết |
| :---- | :---- | :---- | :---- |
| **👨‍🏫 Giáo viên** | Dashboard | Tạo game / Chọn bộ câu hỏi | Chọn câu hỏi từ MySQL, cấu hình: thời gian/câu, số câu, cho phép spectator |
| **👨‍🏫 Giáo viên** | Lobby Screen | Nhận Game PIN (6 số) | Server tạo room trên Redis, sinh PIN ngẫu nhiên, hiển thị QR code \+ PIN lớn trên màn chiếu |
| **👨‍🏫 Giáo viên** | Lobby Screen | Chờ học sinh join, Start khi đủ | Danh sách học sinh join realtime. Nút Start Game kích hoạt khi ≥2 người. GV có thể kick người. |
| **👩‍🎓 Học sinh** | Join Screen | Nhập PIN \+ Tên hiển thị | URL: kahoot.school/join — Nhập PIN 6 số, tên nickname (≤20 ký tự). WebSocket handshake \+ NTP sync ngay tại bước này. |
| **👩‍🎓 Học sinh** | Waiting Room | Chờ GV bắt đầu | Hiển thị avatar ngẫu nhiên \+ tên. Có animation. Server push event START → chuyển màn hình tự động. |
| **👩‍🎓 Học sinh** | Question Screen | Đọc câu hỏi \+ Nhập công thức | Hiển thị: Câu hỏi text \+ hình ảnh (nếu có), bộ đếm ngược tròn, số thứ tự câu, bàn phím hóa học ảo bên dưới. |
| **👩‍🎓 Học sinh** | Submit State | Bấm Submit → Màn chờ | Sau submit: bàn phím ẩn, hiển thị spinner \+ 'Đã nộp\! Chờ kết quả...' \+ thứ hạng tạm thời nếu đã có đủ người nộp. |
| **👩‍🎓 Học sinh** | Result Screen | Xem kết quả câu vừa rồi | ✅/❌ Đúng/Sai. Đáp án đúng hiển thị. Điểm nhận được (+850). Thứ hạng tức thời. Animation confetti nếu đúng. |
| **👩‍🎓 Học sinh** | Leaderboard | Top 5 sau mỗi câu | Hiển thị 5 giây giữa các câu. Tên \+ điểm \+ delta (+200). Vị trí thay đổi có animation trượt. Câu tiếp theo tự động bắt đầu. |
| **👩‍🎓 Học sinh** | Final Screen | Bảng xếp hạng cuối | Podium top 3 (vàng/bạc/đồng). Điểm tổng. Nút 'Chơi lại' và 'Về trang chủ'. GV có thể export kết quả CSV. |

## **3.1 Màn hình học sinh — Mô tả chi tiết Question Screen**

📱  Mobile-first: toàn bộ UI thiết kế cho màn hình 375px trở lên. Bàn phím chiếm 40% chiều cao màn hình.

**Bố cục từ trên xuống (flex column, full height):** *cố định để học sinh không bị scroll.*

* **Header (8%):** Số câu (3/10), progress bar, điểm hiện tại của bản thân.

* **Timer (12%):** Vòng tròn countdown SVG, màu đổi từ xanh → vàng → đỏ khi còn ≤5 giây. Rung nhẹ khi còn 3 giây.

* **Câu hỏi (25%):** Text lớn, căn giữa, scroll được nếu dài. Font size tự co theo độ dài câu hỏi.

* **Input display (15%):** Công thức đang nhập, hiển thị đẹp với subscript/hệ số. Cursor nhấp nháy. Nút xóa ký tự cuối.

* **Bàn phím hóa học (40%):** Xem mục 5 để biết thiết kế chi tiết.

## **3.2 Hệ thống tính điểm**

Theo công thức Kahoot chuẩn, có điều chỉnh:

// Điểm tối đa mỗi câu: 1000  
// Điểm thực tế \= 1000 × (time\_remaining / time\_limit) × accuracy\_bonus  
//  
// time\_remaining: giây còn lại khi submit (dùng Hybrid Timestamp)  
// accuracy\_bonus: 1.0 (đúng hoàn toàn) | 0 (sai)  
//  
// Bonus streak: đúng liên tiếp 3 câu → \+50 mỗi câu tiếp theo  
// Ví dụ: submit sau 3s, câu 30s → 1000 × (27/30) \= 900 điểm

# **4\. Admin Dashboard — PHP là lựa chọn đúng**

✅  PHP hoàn toàn phù hợp cho admin dashboard. Không cần real-time ở đây — CRUD câu hỏi, xem báo cáo, quản lý tài khoản đều là HTTP request thông thường. Laravel hoặc PHP thuần đều được.

| Module | Stack | Mô tả chức năng |
| :---- | :---- | :---- |
| **Quản lý câu hỏi** | PHP \+ MySQL | CRUD câu hỏi: text, hình ảnh, đáp án đúng (chuẩn hoá), thời gian. Import từ CSV/Excel. |
| **Quản lý bộ đề** | PHP \+ MySQL | Tạo/sửa/xoá bộ câu hỏi. Gán tag môn học, lớp. Clone bộ đề. |
| **Xem lịch sử game** | PHP \+ MySQL | Danh sách game đã chơi, kết quả từng học sinh, export CSV báo cáo lớp. |
| **Auth giáo viên** | PHP Session | Login/logout, role: admin/teacher. bcrypt password. CSRF token. |
| **Tạo game mới** | PHP → Redis | PHP tạo room trên Redis qua phpredis. Sinh PIN, set TTL 2 giờ, redirect GV đến Lobby Screen. |
| **Lobby / Host Screen** | Node.js/Go WS | Sau khi tạo game, GV dùng WebSocket để host. PHP chỉ khởi tạo, phần real-time do Node/Go xử lý. |

## **4.1 Kiến trúc tổng thể — 2 server, 1 database layer**

┌─────────────────────────────────────────────────────────┐  
│  Browser / Mobile Client                                │  
└────────────┬────────────────────┬───────────────────────┘  
             │ HTTP               │ WebSocket  
             ▼                   ▼  
  ┌──────────────────┐  ┌────────────────────────┐  
  │  PHP Admin/API   │  │  Node.js / Go          │  
  │  (Nginx \+ PHP)   │  │  WebSocket Server      │  
  │  Port 80/443     │  │  Port 8080             │  
  └────────┬─────────┘  └──────────┬─────────────┘  
           │                       │  
           ▼                       ▼  
  ┌──────────────────┐  ┌────────────────────────┐  
  │  MySQL/MariaDB   │  │  Redis (In-memory)     │  
  │  Câu hỏi, users  │  │  Room state, scores    │  
  └──────────────────┘  └────────────────────────┘


# **6\. Phân rã nhiệm vụ cho AI Agents (Cursor)**

🤖  File này được thiết kế để đưa trực tiếp cho Cursor. Mỗi Agent có thể implement song song vì phụ thuộc đã được ghi rõ. Bắt đầu Phase 0 → chạy Phase 1 & 2 song song → Phase 3 & 4\.

| \# | Task / Agent | File / Module | Thời gian | Phụ thuộc | Acceptance Criteria |
| ----- | :---- | :---- | :---- | :---- | :---- |
| **—** | **PHASE 0 — Setup hạ tầng** |  | 30 phút | — |  |
| **0.1** | Agent: DevOps — VPS \+ Docker Compose | *docker-compose.yml* | 30 phút | — | Redis \+ MySQL \+ Node/Go container chạy được. Health check pass. |
| **—** | **PHASE 1 — Database & Auth (PHP)** |  | 3–4 giờ | 0.1 |  |
| **1.1** | Agent: DB — MySQL schema migration | *migrations/001\_init.sql* | 1 giờ | 0.1 | Tables: users, question\_sets, questions, game\_sessions, game\_results tồn tại đúng schema. |
| **1.2** | Agent: Auth — PHP login/session/CSRF | *auth/login.php, middleware/* | 2 giờ | 1.1 | Login/logout hoạt động. bcrypt verify. CSRF token validate. Session expire 8h. |
| **1.3** | Agent: CRUD — Quản lý câu hỏi & bộ đề | *admin/questions/, admin/sets/* | 3 giờ | 1.1, 1.2 | Tạo/sửa/xoá câu hỏi. Lưu đáp án đúng dạng normalized. Import CSV. Validation phía server. |
| **—** | **PHASE 2 — WebSocket Server (Node/Go)** |  | 4–6 giờ | 0.1 |  |
| **2.1** | Agent: WS-Core — Room manager \+ Redis state | *ws/room.js, ws/redis.js* | 2 giờ | 0.1 | Tạo/join/leave room. State persist Redis. Reconnect khôi phục đúng state. |
| **2.2** | Agent: NTP — Time sync \+ Hybrid Timestamp | *ws/ntp.js, client/ntp.js* | 1.5 giờ | 2.1 | 3 vòng ping-pong. Median offset. Hybrid timestamp reject lệch \>500ms. |
| **2.3** | Agent: Gameplay — Submit, ZSET, scoring | *ws/gameplay.js, ws/scoring.js* | 2 giờ | 2.1, 2.2 | Submit lưu ZSET \+ Hash. Idempotency (SETNX). Rate limit 1/giây. Leaderboard đúng thứ tự. |
| **2.4** | Agent: PubSub — Multi-worker broadcast | *ws/pubsub.js* | 1 giờ | 2.1 | Chạy 4 worker. Broadcast đến 50 client từ bất kỳ worker nào. Không drop message. |
| **—** | **PHASE 3 — Frontend (Mobile-first)** |  | 6–8 giờ | 2.1 |  |
| **3.1** | Agent: UI-Join — Join screen \+ NTP client | *public/join.html, js/join.js* | 1.5 giờ | 2.1, 2.2 | Nhập PIN \+ tên. WS connect. NTP sync. Chuyển waiting room. Responsive 375px+. |
| **3.2** | Agent: Keyboard — Bàn phím hóa học | *js/keyboard.js, css/keyboard.css* | 3 giờ | — | 3 tab hoạt động. Smart context (hệ số vs subscript). Backspace xóa 1 token. Preview realtime. |
| **3.3** | Agent: UI-Game — Question \+ Timer \+ Submit | *public/game.html, js/game.js* | 2 giờ | 3.2, 2.3 | Timer countdown SVG. Submit lock sau khi bấm. Result screen. Leaderboard animation. |
| **3.4** | Agent: UI-Host — Màn hình giáo viên | *public/host.html, js/host.js* | 1.5 giờ | 2.1, 2.3 | Hiển thị câu hỏi to. Số người đã submit. Bảng điểm realtime. Nút Next/End. |
| **—** | **PHASE 4 — Integration & Testing** |  | 2–3 giờ | All |  |
| **4.1** | Agent: Test — Load test 50 concurrent users | *test/load-test.js (k6)* | 1 giờ | All | 50 WS concurrent. p99 latency \<50ms. Không drop message. Leaderboard nhất quán. |
| **4.2** | Agent: Test — Reconnect & edge cases | *test/reconnect.test.js* | 1 giờ | 2.1 | Ngắt mạng giữa câu → reconnect → đúng state. Double-submit bị chặn. Clock skew \>500ms bị reject. |

## **6.1 Prompt mẫu cho từng Agent trong Cursor**

**Cách dùng:** Mở Cursor, tạo chat mới, paste đoạn dưới \+ đính kèm file đặc tả này.

\=== PROMPT CHO AGENT 3.2 — KEYBOARD \===  
Bạn là AI agent implement module bàn phím hóa học.  
Đọc file đặc tả (mục 5\) để hiểu yêu cầu.  
   
Output cần tạo:  
  \- js/keyboard.js   : KeyboardController class, config-driven  
  \- css/keyboard.css : Mobile-first, touch targets ≥44px  
  \- js/keyboard-config.js : KEYBOARD\_TABS array  
   
Constraints:  
  \- Vanilla JS, không dùng framework  
  \- Smart context: sau element → subscript, sau '+' → coefficient  
  \- Backspace xóa 1 token hoàn chỉnh  
  \- Emit event 'formula-change' với payload {tokens, serialized}  
  \- Không phụ thuộc module khác (standalone)

# **7\. Hướng phát triển tiếp theo**

| Giai đoạn | Thời điểm | Tính năng |
| :---- | :---- | :---- |
| **v1.0 MVP** | Hoàn thành Phase 4 | Game cơ bản hoạt động: GV tạo game, HS join bằng PIN, bàn phím hóa học, leaderboard realtime. |
| **v1.1** | \+1–2 tuần | Hình ảnh trong câu hỏi. Spectator mode (phụ huynh xem). Export kết quả CSV. QR code join. |
| **v1.2** | \+2–3 tuần | Chế độ luyện tập (không realtime). Thống kê sai nhiều nhất theo câu. Dashboard phân tích lớp. |
| **v2.0** | \+1–2 tháng | Mở rộng môn học: Toán (LaTeX input), Vật lý (đơn vị SI). Bàn phím mở rộng theo môn. Multi-language. |
| **v3.0** | \+3–6 tháng | AI tự sinh câu hỏi từ bài học. Adaptive difficulty. API public cho trường học tích hợp. |

*v4.0 — Đầy đủ luồng hoạt động, phân rã agent, bàn phím UX, roadmap. Sẵn sàng đưa cho Cursor.*