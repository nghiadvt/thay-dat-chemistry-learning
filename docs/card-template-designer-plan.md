# Trình thiết kế thẻ tài khoản (Card Template Designer) cho in phiếu học sinh

**Cập nhật lần cuối:** 2026-07-22 (editor: grip kéo textbox, handle góc resize, xem trước A4 có ảnh)

## Context

Trang `/admin/students/classes/{id}/print-cards` hiện chỉ cho chọn **3 mẫu tĩnh** (`modern/classic/minimal`) định nghĩa cứng trong code — không upload được ảnh nền, không tự thiết kế, không kéo thả dữ liệu. Xem `php-admin/app/Support/PrintCardTemplates.php`, `php-admin/app/Http/Controllers/Admin/StudentPrintCardController.php`, `php-admin/resources/views/admin/students/print-cards/sheet.blade.php`.

Cần: một **trình thiết kế thẻ WYSIWYG** cho phép giáo viên upload ảnh template (1 hoặc 2 mặt), kéo-thả các ô textbox (bind cột dữ liệu hoặc text tĩnh) lên ảnh, tùy chỉnh font/màu/viền/nền, xem trước với dữ liệu mẫu, lưu **template tái sử dụng được**, rồi tự động **xếp thẻ (auto-tile) lên khổ A4** và xuất **PDF đúng DPI in ấn**.

Quyết định đã chốt với người dùng:
- Ô textbox thống nhất: mỗi ô là **text tĩnh** *hoặc* **bind cột** (student/class/teacher). Có handle 6-chấm kiểu Notion + thanh công cụ nổi để giữ canvas gọn. Tùy chỉnh: font (size/family/weight/italic/underline), màu chữ, canh lề, màu nền, độ mờ nền, viền (dày/màu/bo góc), padding, kích thước ô.
- **Thêm cột `email`** vào bảng students (kèm luồng nhập liệu).
- Template **thuộc về giáo viên, dùng lại cho nhiều lớp**.
- **Khung thẻ (frame) dùng chung cho cả 2 mặt**: một khung width×height chỉnh được; giáo viên tự chỉnh ảnh cho khớp khung. Khung quyết định kích thước + tỉ lệ thẻ cho cả 2 mặt và cho việc xếp A4.
- **Nhiều lớp ảnh (image layers) mỗi mặt — thao tác kiểu canvas**: upload nhiều ảnh lên 1 mặt; ảnh upload sau **đè lên** ảnh trước (theo z-order). Mỗi ảnh **kéo/di chuyển/thu phóng/xoay/chỉnh độ mờ** độc lập trong khung để thấy lẫn nhau. Có **dải thumbnail** các ảnh đã upload (đánh số), **kéo để đổi thứ tự** z-order. Text elements luôn nằm trên cùng các lớp ảnh.
- **Font**: bundle sẵn nhiều file `.ttf` (phổ biến + cách điệu) đăng ký với dompdf để in khớp editor; đánh dấu cờ hỗ trợ tiếng Việt cho từng font.

## Ràng buộc kỹ thuật cốt lõi (đã xác minh)

- **dompdf KHÔNG chạy JS/canvas/html2canvas.** Editor client phải serialize toàn bộ vị trí + style thành JSON; server render lại thành HTML/CSS **định vị tuyệt đối theo mm** (đúng pattern hiện có trong `sheet.blade.php`). Tránh float/table/inline-block.
- **Ảnh cho dompdf**: nhúng **data URI base64** (dompdf đọc tốt), tránh rắc rối chroot/remote.
- **DPI in**: dompdf nhúng ảnh theo pixel gốc, kích thước vật lý đặt bằng mm → chất lượng in = độ phân giải ảnh nguồn. Khi lưu sẽ **bake (kết xuất)** ảnh mỗi mặt thành PNG khớp đúng khung ở **300 DPI** (`px = mm/25.4*300`), rồi dompdf chỉ vẽ full-frame. Editor cảnh báo nếu ảnh gốc dưới ngưỡng 300 DPI.
- **Không có build step**: JS thuần gắn `window`, nạp qua `@vasset()`; thư viện ngoài nạp qua CDN `<script>` per-page (đã có tiền lệ html2canvas 1.4.1, socket.io, CKEditor).
- **Mật khẩu**: lấy qua `StudentPasswordService::reveal()` như hiện tại.

## Tiền lệ trong codebase cần tái sử dụng

- Kéo/resize bằng pointer-event + map tọa độ `scaleX/scaleY`: `php-admin/public/js/image-cropper.js` (mode `move/resize`, `hitTestHandle`, `getBoundingClientRect`).
- Editor toàn màn hình + boot object: `php-admin/resources/views/admin/keyboards/editor.blade.php` (`@section('body-class','admin-body--editor-tool')`, `window.ADMIN_BOOT`), logic undo/redo + bake PNG + upload data-URL trong `php-admin/public/htd-admin/js/keyboard-editor.js` (`pushHistory/undo/redo`, `captureKeyboardPreview`, `toDataURL`).
- API client `window.HTDApi` (envelope `{success,data,error}`, cookie + `X-XSRF-TOKEN`, upload data-URL kiểu `uploadKeyboardPreview`): `php-admin/public/htd-admin/js/api.js`.
- Upload ảnh lên disk `public` + lưu `*_path`, xóa file cũ khi thay: `AccountController` (avatar), `KeyboardController::uploadPreview`.
- Toast `window.AdminToast.show`, confirm dialog, `@vasset()`.

---

## Thiết kế triển khai

### 1. Cơ sở dữ liệu

**Migration A — thêm email vào students** (`database/migrations/..._add_email_to_students_table.php`):
- `$table->string('email', 190)->nullable()->after('display_name');`
- Thêm `email` vào `$fillable` của `Student.php`.
- Validate + lưu trong `StudentController@store/@update` (`nullable|email|max:190`) và thêm input email vào form blade tạo/sửa học sinh.

**Migration B — bảng `card_templates`** (JSON layout theo tiền lệ `default_policy => 'array'`):

| cột | kiểu | ghi chú |
|---|---|---|
| id | id | |
| teacher_id | foreignId → users, cascade | chủ sở hữu |
| name | string 120 | tên template |
| sides | tinyInt (1\|2) | 1 mặt / 2 mặt |
| frame_width_mm, frame_height_mm | decimal(6,2) | khung dùng chung 2 mặt |
| front_baked_path, back_baked_path | string nullable (public) | PNG đã bake 300 DPI (composite mọi lớp ảnh) để in |
| layout | json (`=> 'array'`) | xem cấu trúc dưới |
| timestamps, softDeletes | | |

Model `App\Models\CardTemplate` (fillable + cast `layout => array`, `sides => int`, decimals; scope `visibleTo(User)`; accessor URL cho các path qua `Storage::disk('public')->url`).

**Cấu trúc `layout` JSON:**
```jsonc
{
  "front": {
    "imageLayers": [                     // thứ tự mảng = z-order (đầu = dưới cùng, cuối = trên cùng)
      {
        "id": "img_01", "path": "card-templates/<id>/front/img_01.png", // ảnh gốc trên disk public
        "x": 0.0, "y": 0.0, "w": 1.0, "h": 1.0,   // vị trí/kích thước theo khung [0..1]
        "rotation": 0, "opacity": 1,
        "naturalRatio": 1.586   // tỉ lệ w/h ảnh gốc — editor giữ aspect khi resize; optional
      }
      /* … nhiều ảnh, ảnh sau đè ảnh trước … */
    ],
    "elements": [ /* text elements — luôn nằm trên các lớp ảnh */ ]
  },
  "back": { "imageLayers": [], "elements": [], "frameCropY": 0.12 },
  "a4": { "marginMm": 8, "gapMm": 4, "cardWidthMm": 54 }         // cardWidthMm = bề rộng thẻ khi xếp A4
}
```
Ảnh gốc từng lớp lưu trên disk `public` (VD `card-templates/<id>/<side>/<layerId>.png`), path nằm trong JSON để re-edit; thêm/bớt lớp thì xóa file lớp bị gỡ.
Mỗi **element** (đơn vị = phân số [0..1] theo khung, độc lập độ phân giải):
```jsonc
{
  "id": "el_ab12",
  "binding": "static" | "student.display_name" | "student.username" | "student.password"
            | "student.student_code" | "student.email" | "class.name" | "class.grade" | "teacher.name",
  "text": "Nhãn tĩnh / fallback",
  "x": 0.12, "y": 0.30, "w": 0.6, "h": 0.12,     // theo khung
  "fontFamily": "be-vietnam-pro", "fontSizePt": 11, "fontWeight": 700,
  "italic": false, "underline": false, "color": "#111827", "align": "left",
  "lineHeight": 1.2, "paddingPx": 4,
  "bgColor": null, "bgOpacity": 1, "borderWidthPt": 0, "borderColor": "#000", "borderRadiusPx": 4
}
```
**Mô hình co giãn đồng nhất**: geometry lưu theo phân số khung; font lưu pt tại kích thước **khung tham chiếu** (`frame_*_mm`). Khi xếp A4 với `cardWidthMm` khác, render nhân **hệ số tỉ lệ đồng nhất** `k = cardWidthMm / frame_width_mm` cho mọi mm + pt → WYSIWYG khi phóng to/thu nhỏ thẻ.

### 2. Registry font (bundle .ttf + đăng ký dompdf)

- `App\Support\CardFonts`: mảng `{ key, label, family, files{regular,bold,italic}, supportsVietnamese }`. Bộ đề xuất: **Việt-friendly** Be Vietnam Pro, Inter, Roboto, Montserrat, Nunito; **serif** Merriweather, Playfair Display; **cách điệu** Lobster, Pacifico, Dancing Script (flag Latin-only nếu thiếu dấu tiếng Việt).
- File `.ttf` đặt tại `resources/fonts/card/`; publish `config/dompdf.php` với `chroot` gồm thư mục đó + `isFontSubsettingEnabled => true`, `dpi => 96`, `enable_remote => false`.
- Partial render (mục 4) sinh `@font-face` trỏ `src: url(file://<abs>)` cho từng font đang dùng → dompdf tự nạp. Editor nạp cùng font qua `@font-face` web để khớp WYSIWYG. Picker font đánh dấu font Latin-only.

### 3. Routes & Controller

`Admin\CardTemplateController` (teacher-owned CRUD + editor). Trong nhóm `admin`:
```php
Route::get   ('card-templates',                 'index')->name('card-templates.index');
Route::get   ('card-templates/create',          'create')->name('card-templates.create'); // editor mới
Route::post  ('card-templates',                 'store');                                   // JSON
Route::get   ('card-templates/{template}/edit', 'edit');                                    // editor sửa
Route::put   ('card-templates/{template}',      'update');                                  // lưu layout+ảnh
Route::delete('card-templates/{template}',      'destroy');
Route::post  ('card-templates/{template}/preview', 'preview'); // render 1 thẻ dữ liệu mẫu → HTML (modal iframe)
```
- `store/update` nhận `name, sides, frame_*_mm, layout(json)`, và **các lớp ảnh mới dạng data-URL** cho mỗi mặt (ảnh cũ giữ theo path đã có). Server lưu từng ảnh gốc lớp vào disk `public`, rồi **bake** PNG 300 DPI khớp khung (GD/Imagick: canvas `frame_mm/25.4*300`px; vẽ **lần lượt từng lớp ảnh theo z-order** với vị trí/kích thước/xoay/opacity, overflow khung bị cắt) → lưu `*_baked_path`. Xóa file lớp/bake cũ khi thay (tiền lệ avatar/keyboard).
- `assertOwned` giống controller hiện có.

**Mở rộng in theo lớp** trong `StudentPrintCardController.php`:
- `index`: bổ sung danh sách **custom templates của giáo viên** + nút “Thiết kế thẻ mới” (→ editor) bên cạnh 3 mẫu built-in.
- `preview`/`export`: nhận `source = builtin:<key>` **hoặc** `custom:<id>`. Nếu custom → render qua sheet A4 mới (mục 5) với auto-tile; 2 mặt → sinh **trang mặt trước rồi trang mặt sau cùng lưới** (mỗi mặt 1 PDF/khối), giữ pipeline zip + `deleteFileAfterSend(true)` hiện có.

### 4. Partial render thẻ dùng chung (đảm bảo khớp editor ↔ preview ↔ PDF)

`resources/views/admin/card-templates/_card.blade.php`: nhận `(elements, imageDataUri, frameWidthMm, frameHeightMm, scaleK, data)` → xuất 1 khối thẻ kích thước `(frameW*k)×(frameH*k)`mm: `<img>`/background full-frame + mỗi element `position:absolute` theo mm (x*fw, y*fh…), font `@font-face`, màu/viền/nền. **Dùng lại** bởi cả `preview` (1 thẻ, data mẫu) và `sheet` A4 (mục 5). Editor client dựng DOM cùng cấu trúc/CSS để WYSIWYG.

### 5. Sheet A4 mới cho template tùy chỉnh

`resources/views/admin/card-templates/sheet.blade.php` (dựa trên sheet.blade.php hiện có):
- Tính `cardWmm = a4.cardWidthMm`, `cardHmm = cardWmm / (frameW/frameH)`, `k = cardWmm/frameW`.
- Auto-tile: `cols = floor((210-2*margin+gap)/(cardWmm+gap))`, `rows = floor((297-2*margin+gap)/(cardHmm+gap))`, `perSheet = cols*rows`. Đặt từng thẻ `position:absolute` left/top mm (đúng pattern hiện tại).
- Chunk học sinh theo `perSheet`; mỗi thẻ `@include('_card')` với data học sinh + ảnh bake data-URI.

### 6. Front-end: Trình thiết kế (2 chế độ trong 1 editor)

Blade `resources/views/admin/card-templates/editor.blade.php` (copy khung `keyboards/editor.blade.php`): `window.ADMIN_BOOT = { apiBase, template, fonts, bindings, sampleData }`; nạp `@vasset('htd-admin/js/card-editor.js')` + `@vasset('htd-admin/css/card-editor.css')` + **interact.js qua CDN** (kéo/resize mượt, đồng nhất pattern CDN sẵn có). CSS/JS thuần theo chuẩn dự án.

**JS `card-editor.js`** (`window.CardEditor`, dùng lại pattern state+history của keyboard-editor):
- **Tab “Thiết kế thẻ” (canvas nhiều lớp)**:
  - **Ảnh nền full-width cố định**: upload 1 ảnh/mặt, luôn phủ full bề rộng artboard; chiều cao artboard = width ÷ tỉ lệ ảnh gốc. Ảnh **không kéo được**.
  - **Khung thẻ (viewport) di chuyển trên ảnh**: bề rộng = full artboard; kéo handle 6-chấm **lên/xuống** để chọn vùng in; kéo cạnh dưới hoặc slider **Chiều cao khung** để đổi `frame_height_mm`. Vùng ngoài khung **tối màu**, trong khung **sáng**.
  - Lưu vị trí khung theo `frameCropY` [0..1] trên mỗi mặt (`front`/`back`).
  - Chỉnh `frame_*_mm` (input nâng cao) → tỉ lệ khung in đổi theo. Cả 2 mặt chia sẻ **một khung mm**; chuyển tab mặt trước/mặt sau.
  - **Dải thumbnail lớp ảnh** (panel bên): mỗi ảnh 1 thumbnail **đánh số**, **kéo để đổi z-order**, toggle ẩn/hiện + opacity nhanh, nút xóa. Chọn thumbnail = chọn lớp trên canvas và ngược lại.
  - **Chèn dữ liệu bằng chip gợi ý** (thay cho khái niệm "binding"): một hàng nút `[Tên] [Tài khoản] [Mật khẩu] [Mã HS] [Email] [Lớp] [Khối]` + nút `[Text tự nhập]`. Bấm → ô hiện ngay trên thẻ với **dữ liệu mẫu**, luôn nằm trên các lớp ảnh; **kéo bằng grip 6-chấm** bên trái ô (interact.js `allowFrom`), resize bằng góc.
  - **Grip 6-chấm + nút ⋯**: grip trái chỉ để **di chuyển**; nút **⋯** bên phải mở **popover** cạnh ô gồm: đổi nguồn dữ liệu (các chip trên), ô nhập text, font-family (list từ registry, gắn cờ Việt), cỡ chữ, B/I/U, màu chữ, canh lề, màu nền + độ mờ, viền (dày/màu/bo góc), padding, nút xóa. Canvas chính giữ gọn — mọi control ẩn trong popover.
  - Undo/redo, Del để xóa ô, snap lưới nhẹ.
- **Tab “Sắp lên A4”**:
  - Vẽ khổ A4 theo tỉ lệ + 1 thẻ; input **bề rộng thẻ (mm)** + margin + gap; kéo slider phóng to/thu nhỏ thẻ (chỉ đổi `cardWidthMm`, tỉ lệ giữ nguyên).
  - Nút **“Tự động xếp”**: tính `cols×rows` vừa A4, nhân bản thẻ lấp đầy đúng vị trí chuẩn (kích thước thẻ không đổi), hiển thị “được N thẻ/trang”.
- **Nút “Xem trước”**: lưu nháp (PUT) → mở **modal iframe** gọi route `preview` (render server-side qua `_card` với **dữ liệu mẫu**) ⇒ đúng như PDF sẽ in.
- **Nút “Lưu”**: bake ảnh mỗi mặt client (canvas `toDataURL`) + gửi layout → `store/update`.

### 6b. Nguyên tắc ngôn ngữ & UX (giữ nguyên sức mạnh, làm mềm bề mặt)

Đối tượng là giáo viên trẻ đã quen Canva/Notion → **giữ toàn bộ năng lực editor** (nhiều lớp ảnh, xoay, opacity, kéo tự do). Chỉ điều chỉnh ở lớp nhãn/wording để tránh ngôn ngữ chuyên ngành:
- **Không dùng từ "binding"** → dùng **chip dữ liệu** trực quan (mục 6). Không hiện tên cột kỹ thuật (`student.username`) cho người dùng.
- **Giấu đơn vị `mm`/`pt`**: thao tác chính là kéo-thả trực quan; cỡ chữ hiển thị bằng thanh trượt/mức "Nhỏ · Vừa · Lớn". Số `mm` (khung thẻ, bề rộng thẻ khi in) gom vào panel **"Thông số in / Nâng cao"**, mặc định thu gọn.
- **z-order**: giữ nguyên (thumbnail kéo đổi thứ tự + nút "Đưa lên trên / xuống dưới") — đúng chuẩn Canva, không cần đổi.
- **Cảnh báo DPI bằng ngôn ngữ người dùng**: thay "ảnh < 300 DPI" bằng ví dụ "Ảnh có thể hơi mờ khi in — nên dùng ảnh nét hơn", kèm gợi ý kích thước tối thiểu.
- **Snap + đường gióng nhẹ** (căn giữa/canh mép/khoảng cách đều) khi kéo ô & lớp ảnh — giúp bố cục gọn mà không cần người dùng chỉnh số.

### 7. Tích hợp điều hướng

- Thêm mục **“Mẫu thẻ của tôi”** vào sidebar admin (`layouts/admin.blade.php`) trong nhóm học sinh/công cụ.
- Trang print-cards của lớp: hiển thị custom templates + nút thiết kế mới; chọn template custom rồi **Tạo & tải ZIP** như luồng cũ.

---

## Files chính sẽ tạo/sửa

- **Tạo**: migration email; migration `card_templates`; `app/Models/CardTemplate.php`; `app/Support/CardFonts.php`; `app/Http/Controllers/Admin/CardTemplateController.php`; `config/dompdf.php`; `resources/fonts/card/*.ttf`; views `admin/card-templates/{index,editor,_card,sheet}.blade.php`; `public/htd-admin/js/card-editor.js`; `public/htd-admin/css/card-editor.css`.
- **Sửa**: `routes/web.php`; `Student.php` + form/validate email trong `StudentController`; `StudentPrintCardController.php` (nhận template custom) + `print-cards/index.blade.php`; `layouts/admin.blade.php`; `api.js` (thêm hàm gọi card-template API).

## Xác minh (end-to-end)

1. `php artisan migrate` — kiểm tra cột `email` + bảng `card_templates`.
2. Đăng nhập admin → “Mẫu thẻ của tôi” → tạo mới: chỉnh khung; **upload nhiều ảnh 1 mặt** → kiểm tra ảnh sau đè ảnh trước, kéo/resize/xoay/opacity từng lớp, kéo thumbnail đổi z-order, xóa 1 lớp; thêm vài ô (bind username/password + 1 text tĩnh); chỉnh font (thử 1 font Việt + 1 cách điệu), màu, viền, nền; kéo-thả & resize; kiểm tra handle 6-chấm + popover.
3. **Xem trước** → modal hiển thị thẻ với dữ liệu mẫu; đối chiếu vị trí/kiểu chữ khớp editor.
4. Tab “Sắp lên A4” → chỉnh bề rộng thẻ + “Tự động xếp” → xác nhận số thẻ/trang hợp lý; **Lưu**.
5. Mở lại template → xác nhận layout + ảnh khôi phục đúng để sửa tiếp.
6. Test 2 mặt: bật `sides=2`, upload ảnh mặt sau, chỉnh cho khớp **cùng khung**; thêm ô mặt sau.
7. Vào lớp có học sinh → print-cards → chọn template custom → **Tạo & tải ZIP** → mở PDF: kiểm tra ảnh nền nét (300 DPI), chữ tiếng Việt đúng font/đúng vị trí, thẻ xếp đúng lưới; bản 2 mặt có trang mặt trước + mặt sau.
8. Kiểm thử biên: lớp 0 học sinh, ảnh phân giải thấp (hiện cảnh báo DPI), font cách điệu Latin-only với tên có dấu (fallback), quyền sở hữu (giáo viên khác không mở được template — 403).
9. Chạy `php artisan test` cho controller mới (ownership, validate, store/update, export).
