# Question Template Schema — Hóa Thầy Đạt

> Cấu trúc JSON `template` + `correct_answer` cho câu hỏi `answer_type: structured`.
> Runtime HS: `equation-ui.js` · Admin builder: `question-template-builder.js`

**Cập nhật lần cuối:** 2026-07-09 (Admin builder: nhập nhanh, đổi loại ô, kéo thả)

---

## 1. Tổng quan

| Field | Kiểu | Ghi chú |
|---|---|---|
| `answer_type` | `structured` | Câu có ô tương tác (hệ số / điền công thức) |
| `input_mode` | string | Preset hành vi UI + validate Admin |
| `template` | JSON array | Thứ tự render phương trình |
| `correct_answer` | JSON object | Đáp án theo id ô |
| `content` | HTML | Đề bài (CKEditor) — tách khỏi template |

### `input_mode` hợp lệ

| Giá trị | Mô tả | Ô cho phép |
|---|---|---|
| `balance` | Cân bằng hệ số | Chỉ `coef` |
| `blank` | Điền chất/công thức thiếu | Chỉ `blank` |
| `blank_balance` | Cả hệ số và điền thiếu | `coef` + `blank` |
| `product` | Điền một sản phẩm | Đúng 1 `blank`, không `coef` |

---

## 2. Template parts (`template[]`)

Mỗi phần tử là object có `t` (type):

| `t` | Field | Mô tả |
|---|---|---|
| `txt` | `text` string | Ký hiệu cố định: ` + `, ` → `, ` = `… |
| `chem` | `text` string | Công thức cố định (plain ASCII): `H2O`, `Fe2O3` |
| `coef` | `id` string | Ô hệ số HS điền số (unique, vd. `c0`) |
| `blank` | `id` string | Ô công thức HS điền qua bàn phím quiz (unique, vd. `b0`) |

### Ví dụ — cân bằng hệ số

```json
[
  { "t": "coef", "id": "c0" }, { "t": "chem", "text": "H2" },
  { "t": "txt", "text": " + " },
  { "t": "coef", "id": "c1" }, { "t": "chem", "text": "O2" },
  { "t": "txt", "text": " → " },
  { "t": "coef", "id": "c2" }, { "t": "chem", "text": "H2O" }
]
```

### Ví dụ — điền chỗ thiếu

```json
[
  { "t": "chem", "text": "Fe" },
  { "t": "txt", "text": " + " },
  { "t": "blank", "id": "b0" },
  { "t": "txt", "text": " → " },
  { "t": "chem", "text": "Fe2O3" }
]
```

---

## 3. Đáp án đúng (`correct_answer`)

```json
{
  "coef": { "c0": "2", "c1": "1", "c2": "2" },
  "blank": { "b0": "O2" }
}
```

- `coef`: giá trị **chuỗi số** nguyên (`"1"`–`"999"`)
- `blank`: công thức plain ASCII; chấm qua `normalizeFormula()` (không phân biệt hoa/thường, bỏ khoảng trắng)

Mọi `id` trong `template` (loại `coef` / `blank`) **bắt buộc** có trong `correct_answer`.

---

## 4. WebSocket / chấm điểm

**`new_question`** (HS): gửi `template`, `input_mode`, `keyboard_config` — **không** gửi `correct_answer`.

**`submit_answer`** (HS):

```json
{
  "question_id": 42,
  "answer": {
    "coef": { "c0": "2", "c1": "1", "c2": "2" },
    "blank": { "b0": "H2" }
  },
  "hybrid_timestamp": 1710000000000
}
```

Chấm: so khớp từng key trong `correct_answer` (`ws-server/ws/scoring.js`).

---

## 5. Admin UI (builder)

- **Nhập nhanh:** `H2 + O2 → H2O` hoặc `H2+O2->H2O` + đáp án `2,1,2` (`->` / `=>` tự đổi `→`).
- **Đổi loại ô:** dropdown trên từng dòng hoặc click chip preview.
- **Kéo thả:** handle `⋮⋮` để sắp xếp lại thứ tự parts.
- **Đáp án inline:** nhập trực tiếp trên dòng hệ số/ô điền.

---

## 6. Mở rộng template mới (sau này)

1. Thêm `t` mới vào schema doc này
2. `equation-ui.js` → `renderPart()` + controller HS
3. `question-template-builder.js` → nút thêm + validate Admin
4. `QuestionValidator.php` → rule validate
5. `scoring.js` → logic chấm (nếu cần)

**Không** nhét ô tương tác vào HTML `content` (CKEditor).

---

## Liên kết

- [`docs/APP_LOGIC.md`](APP_LOGIC.md) — luồng HS, `input_mode`
- [`docs/DATA_MODEL.md`](DATA_MODEL.md) — cột DB `questions`
- [`docs/API_CONTRACTS.md`](API_CONTRACTS.md) — payload WS
