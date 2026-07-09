# Keyboard Schema — Hóa Thầy Đạt

> Cấu trúc JSON lưu trong `keyboards.config` (MySQL JSON column).
> Editor tham chiếu: [`prototype/keyboard-editor.html`](../prototype/keyboard-editor.html) + [`prototype/js/keyboard-editor.js`](../prototype/js/keyboard-editor.js).
> Runtime gửi qua WebSocket: field `keyboard_config` trong event `new_question`.

**Cập nhật lần cuối:** 2026-07-09 (token phím ghép H₂O/CO₂; GV đua vịt hiện bục khi game_ended)

---

## 1. Lưu DB thế nào?

| Cột DB | Nguồn từ editor | Ghi chú |
|---|---|---|
| `keyboards.name` | `#kbeNameInput` / `data.name` | Tên hiển thị admin |
| `keyboards.subject` | Chưa có trong editor — set khi Save API | Mặc định `"chemistry"` |
| `keyboards.config` | Phần layout JSON (xem §2) | **Không** lưu `id` client hay `updatedAt` editor |

**Luồng Save (Phase 1.5):**

```
keyboard-editor → POST /api/keyboards
  body: { name, subject?, config: { schema_version, defaults, rows, smart_context } }
```

Export JSON từ editor (`exportJson()`) gần đúng `config` — API strip `id`/`updatedAt`/`name` trước khi ghi `config`.

---

## 2. Cấu trúc `keyboards.config`

```json
{
  "schema_version": 1,
  "defaults": {
    "keySize": "M",
    "fontSize": "M",
    "textColor": "#000000",
    "background": "#FFFFFF",
    "border": "#D0D0D0"
  },
  "rows": [
    {
      "id": "row-abc",
      "name": "Elements 1",
      "height": "M",
      "padding": 2,
      "spacing": 4,
      "background": "#F5F5F5",
      "border": "#E0E0E0",
      "alignment": "center",
      "hidden": false,
      "locked": false,
      "isSpaceRow": false,
      "keys": [
        {
          "id": "key-xyz",
          "text": "H",
          "value": "H",
          "width": 1,
          "type": "normal",
          "background": "#FFFFFF",
          "color": "#000000",
          "border": "#D0D0D0",
          "radius": 6,
          "fontSize": "M",
          "keySize": "M",
          "tooltip": "",
          "disabled": false
        }
      ]
    }
  ],
  "smart_context": {
    "after_element": "subscript",
    "after_plus": "coefficient"
  }
}
```

### `defaults`

| Field | Kiểu | Mô tả |
|---|---|---|
| `keySize` | `"S"` \| `"M"` \| `"L"` | Kích thước phím mặc định |
| `fontSize` | `"S"` \| `"M"` \| `"L"` | Cỡ chữ mặc định |
| `textColor` | hex `#RRGGBB` | Màu chữ |
| `background` | hex | Nền phím |
| `border` | hex | Viền phím |

### `rows[]`

| Field | Kiểu | Mô tả |
|---|---|---|
| `id` | string | ID nội bộ editor (giữ khi round-trip import/export) |
| `name` | string | Tên hàng (admin only) |
| `height` | `"S"` \| `"M"` \| `"L"` | Chiều cao hàng |
| `padding`, `spacing` | number (px) | |
| `background`, `border` | hex | Style hàng |
| `alignment` | `"flex-start"` \| `"center"` \| `"flex-end"` | |
| `hidden` | boolean | Ẩn hàng khi render HS |
| `locked` | boolean | Khóa chỉnh sửa |
| `isSpaceRow` | boolean | Hàng cuối (Space/Send) — **bắt buộc 1 hàng, ở cuối** |
| `keys` | Key[] | |

### `keys[]`

| Field | Kiểu | Mô tả |
|---|---|---|
| `id` | string | ID nội bộ |
| `text` | string | Nhãn hiển thị |
| `value` | string | Ký tự/token ghi vào output khi bấm |
| `width` | number 1–10 | Độ rộng theo **units** (max 10 units/hàng) |
| `type` | xem bảng dưới | |
| `background`, `color`, `border` | hex | Style từng phím |
| `radius` | number (px) | Bo góc |
| `fontSize`, `keySize` | `"S"` \| `"M"` \| `"L"` | |
| `tooltip` | string | |
| `disabled` | boolean | |

### `type` (key)

| Giá trị | Mô tả | Ghi chú |
|---|---|---|
| `normal` | Phím thường (nguyên tố, số, ký hiệu) | |
| `delete` | Xóa 1 ký tự / token | width cố định 2, bắt buộc có |
| `space` | Dấu cách | Hàng `isSpaceRow` |
| `send` | Xuống dòng / submit | Hàng `isSpaceRow` |
| `globe` | Placeholder đổi layout | Tùy chọn |
| `empty` | Ô trống (spacer) | width flex |

### `smart_context` (hành vi nhập — áp dụng Test editor + runtime HS)

Quy tắc token khi HS gõ (`docs/APP_LOGIC.md` §4.3). Server merge default nếu thiếu:

```json
{
  "after_element": "subscript",
  "after_plus": "coefficient"
}
```

**Test overlay** trong keyboard editor (`keyboard-editor.js`) dùng `smart_context` để tự phân loại số:
- Đầu công thức / sau `+` → hệ số (số to)
- Sau nguyên tố / sau `)` / nối chỉ số → subscript (số nhỏ, `<sub>`)
- **Phím ghép** (vd. `H₂O`, `CO₂`): `formulaAppendChemString` tách thành token element + subscript — khớp Test editor và runtime HS (`equation-ui.js`)
- **Hiển thị subscript:** CSS `top: 0.16em` (không dùng `vertical-align: sub` — tránh lệch quá sâu xuống)
- Backspace xóa **1 token** (vd. `Cl` = 1 token)
- Output hiển thị hóa học; `data-serialized` = plain text gửi server (vd. `2CO2`)

---

## 3. Validation (khớp `validateKeyboard()` trong editor)

Trước khi lưu API / publish:

| Rule | Mô tả |
|---|---|
| `MAX_UNITS = 10` | Tổng `width` mỗi hàng ≤ 10 |
| Hàng Space | Đúng 1 hàng `isSpaceRow`, **ở cuối** |
| Bắt buộc | Ít nhất 1 phím `delete`, `space`, `send` |
| Hàng visible | Không được trống (trừ spacer trong space row) |
| `normal` keys | `text` không rỗng |

---

## 4. WebSocket — `keyboard_config`

Khi emit `new_question`, server load `quizzes.keyboard_id` → copy `keyboards.config` (có thể kèm `name`):

```json
{
  "keyboard_config": {
    "schema_version": 1,
    "defaults": { "...": "..." },
    "rows": [ "..." ],
    "smart_context": { "after_element": "subscript", "after_plus": "coefficient" }
  }
}
```

Client học sinh render `rows[]` giống markup keys của `renderPhoneKb()` trong editor — **không** dùng model `tabs[]` cũ. Overlay Preview/Test admin gọi `renderPhoneKb(el, { editable: false })` (không selectKey / không row menu). Runtime HS (`keyboard-runtime.js` + `student.css`): bàn phím **full-width** neo đáy viewport; `HTDKeyboardRuntime.resolveKeyInputValue(key)` **khớp** `testKeyInputValue()` (fallback `text` khi `value` rỗng). Nhập công thức dùng `smart_context` qua `EquationUI.FormulaController` / `blankTokens` — hệ số (số to) vs chỉ số (`<sub>`) theo token model §4.3 `APP_LOGIC.md`.

---

## 5. Khác biệt so với spec cũ (đã deprecated)

| Spec cũ (plan 1.5 ban đầu) | Prototype / schema hiện tại |
|---|---|
| `tabs: [{ label, keys: ["H","O"] }]` | `rows: [{ name, keys: [{ text, value, width, type, ... }] }]` |
| Chỉ mảng string keys | Key object đầy đủ style + width units |
| Không có space row | Hàng `isSpaceRow` bắt buộc |

---

## 6. Tài liệu liên quan

- [`docs/DATA_MODEL.md`](DATA_MODEL.md) — bảng `keyboards`, FK `quizzes.keyboard_id`
- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — token model, smart context
- [`prototype/js/keyboard-editor.js`](../prototype/js/keyboard-editor.js) — `defaultKeyboard()`, `exportJson()`
