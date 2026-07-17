/**
 * Image Cropper — tải 1 ảnh lớn (lưu ngay lên server), khoanh nhiều vùng chữ
 * nhật bằng canvas (kéo chuột hoặc nhập W/H trực tiếp), kéo di chuyển / chọn
 * nhiều vùng để chỉnh cùng lúc (được phép kéo ra ngoài rìa ảnh — phần ngoài
 * ảnh không được cắt), xoay/lật từng vùng hoặc cả ảnh gốc, phóng to/thu nhỏ
 * khung nhìn (canvas tự tăng độ phân giải theo zoom để không vỡ nét), kẻ
 * ruler/guide để đo, rồi cắt ở độ phân giải gốc — chọn định dạng/DPI lúc lưu
 * — để có thể quay lại chỉnh sửa sau này.
 */
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const MIN_BOX_SIZE = 8;
  const NUDGE_STEP = 10;
  const NUDGE_STEP_FINE = 2;
  const MOVE_THRESHOLD = 2;
  const DUPLICATE_OFFSET = 16;
  const MAX_HISTORY = 50;
  const HANDLE_MIN = 10;
  const HANDLE_MAX = 28;
  const HANDLE_RATIO = 120;
  const CORNER_CURSORS = { nw: 'nwse-resize', se: 'nwse-resize', ne: 'nesw-resize', sw: 'nesw-resize' };
  const ZOOM_MIN = 0.25;
  const ZOOM_MAX = 3;
  const ZOOM_STEP = 0.25;
  const RENDER_SCALE_MAX = 4;
  const RENDER_SCALE_MAX_DIM = 8000;
  const RULER_SIZE = 24;
  const RULER_MIN_TICK_PX = 60;
  const RULER_TICK_CANDIDATES = [5, 10, 25, 50, 100, 250, 500, 1000, 2000, 5000];
  const DPI_PRESETS = { custom: 96, logo: 300, banner: 72, hero: 150 };

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function normalizeDeg(value) {
    let deg = Number(value);
    if (!Number.isFinite(deg)) deg = 0;
    deg %= 360;
    if (deg < 0) deg += 360;
    return Math.round(deg * 100) / 100;
  }

  // ── PNG pHYs chunk (DPI) injection — thao tác byte thuần, không phụ thuộc DOM ──
  function crc32(bytes) {
    let crc = 0xFFFFFFFF;
    for (let i = 0; i < bytes.length; i += 1) {
      crc ^= bytes[i];
      for (let j = 0; j < 8; j += 1) {
        crc = (crc >>> 1) ^ (0xEDB88320 & -(crc & 1));
      }
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  function buildPngChunk(typeStr, data) {
    const typeBytes = new TextEncoder().encode(typeStr);
    const chunk = new Uint8Array(4 + 4 + data.length + 4);
    const view = new DataView(chunk.buffer);
    view.setUint32(0, data.length);
    chunk.set(typeBytes, 4);
    chunk.set(data, 8);
    const crc = crc32(chunk.subarray(4, 8 + data.length));
    view.setUint32(8 + data.length, crc);
    return chunk;
  }

  function injectPngDpi(dataUrl, dpi) {
    const commaIdx = dataUrl.indexOf(',');
    const base64 = dataUrl.slice(commaIdx + 1);
    const binaryStr = atob(base64);
    const bytes = new Uint8Array(binaryStr.length);
    for (let i = 0; i < binaryStr.length; i += 1) bytes[i] = binaryStr.charCodeAt(i);

    const pxPerMeter = Math.round((dpi || 96) / 0.0254);
    const physData = new Uint8Array(9);
    const physView = new DataView(physData.buffer);
    physView.setUint32(0, pxPerMeter);
    physView.setUint32(4, pxPerMeter);
    physData[8] = 1; // unit specifier: 1 = meter
    const physChunk = buildPngChunk('pHYs', physData);

    // signature(8) + IHDR[length(4)+type(4)+data(13)+crc(4)] = 33 — pHYs chèn ngay sau IHDR.
    const ihdrEnd = 8 + 4 + 4 + 13 + 4;
    const newBytes = new Uint8Array(bytes.length + physChunk.length);
    newBytes.set(bytes.subarray(0, ihdrEnd), 0);
    newBytes.set(physChunk, ihdrEnd);
    newBytes.set(bytes.subarray(ihdrEnd), ihdrEnd + physChunk.length);

    let binary = '';
    for (let i = 0; i < newBytes.length; i += 1) binary += String.fromCharCode(newBytes[i]);
    return 'data:image/png;base64,' + btoa(binary);
  }

  function wrapAsSvg(pngDataUrl, width, height) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><image width="${width}" height="${height}" href="${pngDataUrl}"/></svg>`;
    return 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
  }

  function init(options) {
    const uploadUrl = options.uploadUrl;
    const regionsUrlTemplate = options.regionsUrlTemplate;
    const regionDeleteUrlTemplate = options.regionDeleteUrlTemplate;
    const sourceBoot = options.sourceBoot || null;

    const fileInput = document.getElementById('icFileInput');
    const uploadHint = document.getElementById('icUploadHint');
    const workspace = document.getElementById('icWorkspace');
    const canvas = document.getElementById('icCanvas');
    const ctx = canvas.getContext('2d');
    const boxCountEl = document.getElementById('icBoxCount');
    const boxListEl = document.getElementById('icBoxList');
    const emptyHintEl = document.getElementById('icEmptyHint');
    const saveBtn = document.getElementById('icSaveBtn');
    const undoBtn = document.getElementById('icUndoBox');
    const clearBtn = document.getElementById('icClearBoxes');
    const changeImageBtn = document.getElementById('icChangeImage');
    const duplicateBtn = document.getElementById('icDuplicateBox');
    const resultsCard = document.getElementById('icResultsCard');
    const resultListEl = document.getElementById('icResultList');

    const modal = document.getElementById('icRegionModal');
    const modalTitle = document.getElementById('icRegionModalTitle');
    const modalImg = document.getElementById('icRegionModalImg');
    const modalRotate90 = document.getElementById('icModalRotate90');
    const modalRotateInput = document.getElementById('icModalRotateInput');
    const modalRotateRange = document.getElementById('icModalRotateRange');
    const modalFlip = document.getElementById('icModalFlip');
    const modalReset = document.getElementById('icModalReset');

    const zoomOutBtn = document.getElementById('icZoomOut');
    const zoomInBtn = document.getElementById('icZoomIn');
    const zoomResetBtn = document.getElementById('icZoomReset');
    const imageRotateBtn = document.getElementById('icImageRotate90');
    const imageFlipBtn = document.getElementById('icImageFlip');
    const guideColorInput = document.getElementById('icGuideColor');
    const guideThicknessInput = document.getElementById('icGuideThickness');
    const clearGuidesBtn = document.getElementById('icClearGuides');

    const canvasWrapEl = document.getElementById('icCanvasWrap');
    const canvasStageEl = document.getElementById('icCanvasStage');
    const guideLayerEl = document.getElementById('icGuideLayer');
    const rulerHEl = document.getElementById('icRulerH');
    const rulerVEl = document.getElementById('icRulerV');
    const rulerHCanvas = document.getElementById('icRulerHCanvas');
    const rulerVCanvas = document.getElementById('icRulerVCanvas');

    const createByDimsBtn = document.getElementById('icCreateByDims');
    const createDimsPanel = document.getElementById('icCreateDimsPanel');
    const dimsXInput = document.getElementById('icDimsX');
    const dimsYInput = document.getElementById('icDimsY');
    const dimsWInput = document.getElementById('icDimsW');
    const dimsHInput = document.getElementById('icDimsH');
    const dimsDoneBtn = document.getElementById('icDimsDone');
    const dimsCancelBtn = document.getElementById('icDimsCancel');

    const exportModal = document.getElementById('icExportModal');
    const exportFormatSelect = document.getElementById('icExportFormat');
    const exportQualityGroup = document.getElementById('icExportQualityGroup');
    const exportQualityInput = document.getElementById('icExportQuality');
    const exportQualityValueEl = document.getElementById('icExportQualityValue');
    const exportPresetSelect = document.getElementById('icExportPreset');
    const exportDpiInput = document.getElementById('icExportDpi');
    const exportCancelBtn = document.getElementById('icExportCancel');
    const exportConfirmBtn = document.getElementById('icExportConfirm');

    let img = null;
    // Kích thước ảnh gốc (px thật) — hệ tọa độ chuẩn cho MỌI phép tính box/
    // guide/ruler. Tách khỏi canvas.width/height thật (canvas.width/height là
    // độ phân giải bitmap thật, phụ thuộc zoom*devicePixelRatio, xem applyZoom).
    let imgW = 0;
    let imgH = 0;
    let boxes = [];
    let boxSeq = 0;
    let sourceId = sourceBoot ? sourceBoot.id : null;
    let modalBox = null;
    const thumbRefs = new Map();
    const positionRefs = new Map();

    let zoomLevel = 1;
    let guides = { h: [], v: [] };
    let guideSeq = 0;
    let guideColor = guideColorInput ? guideColorInput.value : '#f97316';
    let guideThickness = guideThicknessInput ? Number(guideThicknessInput.value) : 1;

    let clipboard = [];
    let pasteCount = 0;

    let dimsPanelBox = null;
    let dimsPanelSnapshot = null;

    // mode: null | 'draw' (khoanh vùng mới) | 'move' (kéo vùng có sẵn) | 'resize' (kéo góc)
    let mode = null;
    let dragStart = null;
    let dragCurrent = null;
    let moveOrigins = null;
    let movedDuringDrag = false;
    let pendingClickBox = null;
    let pendingClickMulti = false;
    let dragSnapshot = null;
    let resizeBox = null;
    let resizeCorner = null;
    let resizeAnchor = null;
    let resizeChanged = false;

    // Lịch sử cho Ctrl+Z / Ctrl+Y — mỗi phần tử là 1 bản chụp {boxes, boxSeq, img, imgW, imgH}.
    let history = [];
    let future = [];

    function toast(message, type) {
      if (window.AdminToast) window.AdminToast.show(message, type || 'success');
      else if (type === 'error') alert(message);
    }

    // Không giới hạn về khung ảnh nữa — vùng khoanh được phép kéo ra ngoài,
    // lúc cắt canvas tự bỏ qua phần nằm ngoài ảnh gốc. Trả về tọa độ theo px
    // ảnh gốc (imgW/imgH) — KHÔNG phải theo bitmap thật của canvas (đã tách
    // rời để hỗ trợ zoom nét, xem renderScale()/applyZoom()).
    function pointFromEvent(evt) {
      const rect = canvas.getBoundingClientRect();
      const scaleX = imgW / rect.width;
      const scaleY = imgH / rect.height;
      return {
        x: (evt.clientX - rect.left) * scaleX,
        y: (evt.clientY - rect.top) * scaleY,
      };
    }

    function normalizeRect(a, b) {
      return {
        x: Math.round(Math.min(a.x, b.x)),
        y: Math.round(Math.min(a.y, b.y)),
        w: Math.round(Math.abs(b.x - a.x)),
        h: Math.round(Math.abs(b.y - a.y)),
      };
    }

    function hitTestBox(point) {
      for (let i = boxes.length - 1; i >= 0; i -= 1) {
        const b = boxes[i];
        if (point.x >= b.x && point.x <= b.x + b.w && point.y >= b.y && point.y <= b.y + b.h) {
          return b;
        }
      }
      return null;
    }

    function handleSize() {
      return clamp(imgW / HANDLE_RATIO, HANDLE_MIN, HANDLE_MAX);
    }

    function boxCorners(box) {
      return {
        nw: { x: box.x, y: box.y },
        ne: { x: box.x + box.w, y: box.y },
        sw: { x: box.x, y: box.y + box.h },
        se: { x: box.x + box.w, y: box.y + box.h },
      };
    }

    // Điểm đối diện với góc đang kéo — giữ cố định trong lúc resize.
    function oppositeCorner(box, corner) {
      const corners = boxCorners(box);
      const opposite = { nw: 'se', ne: 'sw', sw: 'ne', se: 'nw' }[corner];
      return corners[opposite];
    }

    // Chỉ khung đang được chọn mới hiện ô kéo góc.
    function hitTestHandle(point) {
      const half = handleSize();
      const selected = selectedBoxes();
      for (let i = selected.length - 1; i >= 0; i -= 1) {
        const box = selected[i];
        const corners = boxCorners(box);
        const entries = Object.entries(corners);
        for (let j = 0; j < entries.length; j += 1) {
          const [corner, point2] = entries[j];
          if (Math.abs(point.x - point2.x) <= half && Math.abs(point.y - point2.y) <= half) {
            return { box, corner };
          }
        }
      }
      return null;
    }

    function updateHoverCursor(point) {
      const handle = hitTestHandle(point);
      if (handle) {
        canvas.style.cursor = CORNER_CURSORS[handle.corner];
        return;
      }
      canvas.style.cursor = hitTestBox(point) ? 'move' : 'crosshair';
    }

    function clearSelection() {
      boxes.forEach((b) => { b.selected = false; });
    }

    function selectedBoxes() {
      return boxes.filter((b) => b.selected);
    }

    // Danh sách vùng bị tác động bởi 1 nút điều khiển: nếu vùng đó đang được
    // chọn (có thể cùng nhiều vùng khác) thì áp dụng cho cả nhóm đang chọn,
    // ngược lại chỉ áp dụng riêng vùng đó.
    function targetsFor(box) {
      return box.selected ? selectedBoxes() : [box];
    }

    function snapshotState() {
      return {
        boxes: boxes.map((b) => ({ ...b })),
        boxSeq,
        img,
        imgW,
        imgH,
      };
    }

    function restoreState(state) {
      boxes = state.boxes.map((b) => ({ ...b }));
      boxSeq = state.boxSeq;
      if (state.img && state.img !== img) {
        img = state.img;
        imgW = state.imgW;
        imgH = state.imgH;
        applyZoom();
      }
    }

    function commitHistory(snapshot) {
      history.push(snapshot);
      if (history.length > MAX_HISTORY) history.shift();
      future = [];
    }

    function undo() {
      if (history.length === 0) return;
      future.push(snapshotState());
      restoreState(history.pop());
      drawScene();
      renderBoxList();
      syncModalAfterHistoryChange();
    }

    function redo() {
      if (future.length === 0) return;
      history.push(snapshotState());
      restoreState(future.pop());
      drawScene();
      renderBoxList();
      syncModalAfterHistoryChange();
    }

    function syncModalAfterHistoryChange() {
      if (!modalBox) return;
      const stillExists = boxes.find((b) => b.id === modalBox.id);
      if (stillExists) {
        modalBox = stillExists;
        renderModal();
      } else {
        closeModal();
      }
    }

    function duplicateSelected() {
      const targets = selectedBoxes();
      if (targets.length === 0) return;

      commitHistory(snapshotState());

      const duplicates = targets.map((box) => {
        boxSeq += 1;
        return {
          ...box,
          id: boxSeq,
          regionId: null,
          x: box.x + DUPLICATE_OFFSET,
          y: box.y + DUPLICATE_OFFSET,
          label: box.label ? `${box.label}-copy` : `vung-${boxSeq}`,
          selected: true,
        };
      });

      clearSelection();
      boxes.push(...duplicates);
      drawScene();
      renderBoxList();
    }

    // Nhân bản 1 vùng tại đúng vị trí cũ (không lệch) — dùng khi muốn cắt 2
    // ảnh khác nhau (xoay/lật/tên khác nhau) từ cùng 1 vùng trên ảnh gốc.
    function duplicateInPlace(box) {
      commitHistory(snapshotState());
      boxSeq += 1;
      const copy = {
        ...box,
        id: boxSeq,
        regionId: null,
        label: box.label ? `${box.label}-2` : `vung-${boxSeq}`,
        selected: false,
      };
      boxes.push(copy);
      drawScene();
      renderBoxList();
    }

    // Tỉ lệ bitmap thật / px ảnh gốc — tăng theo devicePixelRatio*zoom để nét
    // khi phóng to, có trần để không cấp phát bitmap khổng lồ.
    function renderScale() {
      if (!imgW || !imgH) return 1;
      const dpr = window.devicePixelRatio || 1;
      const raw = dpr * zoomLevel;
      const capByDim = Math.min(RENDER_SCALE_MAX_DIM / imgW, RENDER_SCALE_MAX_DIM / imgH);
      return Math.max(0.05, Math.min(raw, RENDER_SCALE_MAX, capByDim));
    }

    function drawScene() {
      if (!img || !imgW || !imgH) return;
      const scale = renderScale();
      ctx.setTransform(scale, 0, 0, scale, 0, 0);
      ctx.clearRect(0, 0, imgW, imgH);
      ctx.drawImage(img, 0, 0, imgW, imgH);

      boxes.forEach((box, index) => {
        const color = box.selected ? '#f97316' : '#2D46D6';
        const fill = box.selected ? 'rgba(249, 115, 22, 0.18)' : 'rgba(45, 70, 214, 0.12)';

        ctx.strokeStyle = color;
        ctx.lineWidth = Math.max(2, imgW / 400) * (box.selected ? 1.4 : 1);
        ctx.strokeRect(box.x, box.y, box.w, box.h);
        ctx.fillStyle = fill;
        ctx.fillRect(box.x, box.y, box.w, box.h);

        const badge = String(index + 1);
        const fontSize = Math.max(14, Math.round(imgW / 60));
        ctx.font = `bold ${fontSize}px system-ui, sans-serif`;
        const padding = 6;
        const textWidth = ctx.measureText(badge).width;
        ctx.fillStyle = color;
        ctx.fillRect(box.x, box.y, textWidth + padding * 2, fontSize + padding * 2);
        ctx.fillStyle = '#fff';
        ctx.textBaseline = 'top';
        ctx.fillText(badge, box.x + padding, box.y + padding);
      });

      if (mode === 'draw' && dragStart && dragCurrent) {
        const rect = normalizeRect(dragStart, dragCurrent);
        ctx.strokeStyle = '#dc2626';
        ctx.lineWidth = Math.max(2, imgW / 400);
        ctx.setLineDash([6, 4]);
        ctx.strokeRect(rect.x, rect.y, rect.w, rect.h);
        ctx.setLineDash([]);
      }

      const size = handleSize();
      selectedBoxes().forEach((box) => {
        Object.values(boxCorners(box)).forEach((corner) => {
          ctx.fillStyle = '#fff';
          ctx.fillRect(corner.x - size / 2, corner.y - size / 2, size, size);
          ctx.strokeStyle = '#f97316';
          ctx.lineWidth = Math.max(1.5, size / 8);
          ctx.strokeRect(corner.x - size / 2, corner.y - size / 2, size, size);
        });
      });
    }

    // Vẽ vùng đã khoanh ra 1 canvas riêng, áp dụng lật ngang rồi xoay theo
    // góc đã đặt. Canvas kết quả giãn theo khung bao của hình chữ nhật sau
    // khi xoay để không bị cắt góc, phần thừa để trong suốt. `drawImage` tự
    // giới hạn vùng nguồn theo biên ảnh gốc — phần box nằm ngoài ảnh sẽ chỉ
    // để trống (trong suốt), không lỗi, không cần xử lý riêng. Luôn đọc từ
    // `img` ở độ phân giải gốc — không bị ảnh hưởng bởi zoom khung nhìn.
    function renderRegionCanvas(box) {
      const rad = (box.rotation || 0) * Math.PI / 180;
      const cos = Math.abs(Math.cos(rad));
      const sin = Math.abs(Math.sin(rad));
      const outW = Math.max(1, Math.round(box.w * cos + box.h * sin));
      const outH = Math.max(1, Math.round(box.w * sin + box.h * cos));

      const off = document.createElement('canvas');
      off.width = outW;
      off.height = outH;
      const offCtx = off.getContext('2d');
      offCtx.translate(outW / 2, outH / 2);
      offCtx.rotate(rad);
      if (box.flipped) offCtx.scale(-1, 1);
      offCtx.drawImage(img, box.x, box.y, box.w, box.h, -box.w / 2, -box.h / 2, box.w, box.h);
      return off;
    }

    function cropToDataUrl(box) {
      return renderRegionCanvas(box).toDataURL('image/png');
    }

    // Ảnh gốc + khung đã khoanh vẽ đè lên — dùng làm thumbnail ở trang quản
    // lý, luôn PNG (ảnh nội bộ, không phải file xuất cho người dùng), luôn ở
    // style "chưa chọn" (không tô cam / không ô vuông góc) để nhất quán.
    function renderPreviewCanvas() {
      const off = document.createElement('canvas');
      off.width = imgW;
      off.height = imgH;
      const offCtx = off.getContext('2d');
      offCtx.drawImage(img, 0, 0, imgW, imgH);

      boxes.forEach((box, index) => {
        const color = '#2D46D6';
        offCtx.strokeStyle = color;
        offCtx.lineWidth = Math.max(2, imgW / 400);
        offCtx.strokeRect(box.x, box.y, box.w, box.h);
        offCtx.fillStyle = 'rgba(45, 70, 214, 0.12)';
        offCtx.fillRect(box.x, box.y, box.w, box.h);

        const badge = String(index + 1);
        const fontSize = Math.max(14, Math.round(imgW / 60));
        offCtx.font = `bold ${fontSize}px system-ui, sans-serif`;
        const padding = 6;
        const textWidth = offCtx.measureText(badge).width;
        offCtx.fillStyle = color;
        offCtx.fillRect(box.x, box.y, textWidth + padding * 2, fontSize + padding * 2);
        offCtx.fillStyle = '#fff';
        offCtx.textBaseline = 'top';
        offCtx.fillText(badge, box.x + padding, box.y + padding);
      });

      return off.toDataURL('image/png');
    }

    function applyDelta(box, dx, dy, dw, dh) {
      commitHistory(snapshotState());
      targetsFor(box).forEach((b) => {
        if (dx || dy) {
          b.x += dx;
          b.y += dy;
        }
        if (dw) {
          b.w = Math.max(MIN_BOX_SIZE, b.w + dw);
        }
        if (dh) {
          b.h = Math.max(MIN_BOX_SIZE, b.h + dh);
        }
      });
      drawScene();
      renderBoxList();
    }

    // Cập nhật ảnh thumbnail (danh sách + modal nếu đang mở) mà không cần
    // dựng lại toàn bộ danh sách — dùng cho các thao tác xoay/lật liên tục.
    function refreshBoxVisuals(box) {
      const ref = thumbRefs.get(box.id);
      if (ref) ref.img.src = cropToDataUrl(box);
      if (modalBox && modalBox.id === box.id) renderModal();
    }

    function setBoxRotation(box, deg, snapshot) {
      commitHistory(snapshot || snapshotState());
      box.rotation = normalizeDeg(deg);
      refreshBoxVisuals(box);
    }

    function stepBoxRotation90(box) {
      setBoxRotation(box, (box.rotation || 0) + 90);
    }

    function toggleBoxFlip(box) {
      commitHistory(snapshotState());
      box.flipped = !box.flipped;
      refreshBoxVisuals(box);
    }

    function resetBoxTransform(box) {
      if (!box.rotation && !box.flipped) return;
      commitHistory(snapshotState());
      box.rotation = 0;
      box.flipped = false;
      refreshBoxVisuals(box);
    }

    // Gắn nút xoay 90°, ô nhập góc, thanh trượt góc, nút lật ngang và nút
    // reset cho 1 vùng — dùng chung cho cả hàng trong danh sách lẫn modal.
    function bindRotateFlipControls(box, els) {
      let dragSnapshot = null;
      let rafPending = false;

      function liveUpdate(deg) {
        box.rotation = normalizeDeg(deg);
        if (els.numberInput) els.numberInput.value = String(Math.round(box.rotation));
        if (els.rangeInput) els.rangeInput.value = String(Math.round(box.rotation));
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(() => {
          refreshBoxVisuals(box);
          rafPending = false;
        });
      }

      function syncNumberInput() {
        if (els.numberInput) els.numberInput.value = String(Math.round(box.rotation || 0));
        if (els.rangeInput) els.rangeInput.value = String(Math.round(box.rotation || 0));
      }

      if (els.step90Btn) {
        els.step90Btn.addEventListener('click', () => {
          stepBoxRotation90(box);
          syncNumberInput();
        });
      }

      if (els.numberInput) {
        els.numberInput.value = String(Math.round(box.rotation || 0));
        els.numberInput.addEventListener('change', () => {
          setBoxRotation(box, els.numberInput.value);
        });
      }

      if (els.rangeInput) {
        els.rangeInput.value = String(Math.round(box.rotation || 0));
        els.rangeInput.addEventListener('pointerdown', () => {
          dragSnapshot = snapshotState();
        });
        els.rangeInput.addEventListener('input', () => liveUpdate(els.rangeInput.value));
        els.rangeInput.addEventListener('change', () => {
          if (dragSnapshot) {
            const snap = dragSnapshot;
            dragSnapshot = null;
            setBoxRotation(box, els.rangeInput.value, snap);
          }
        });
      }

      if (els.flipBtn) {
        els.flipBtn.addEventListener('click', () => {
          toggleBoxFlip(box);
          els.flipBtn.classList.toggle('is-active', !!box.flipped);
        });
      }

      if (els.resetBtn) {
        els.resetBtn.addEventListener('click', () => {
          resetBoxTransform(box);
          syncNumberInput();
          if (els.flipBtn) els.flipBtn.classList.remove('is-active');
        });
      }
    }

    function openModal(box, index) {
      modalBox = box;
      modalTitle.textContent = `#${index + 1} — ${box.label || 'Vùng khoanh'}`;
      modal.hidden = false;
      document.body.classList.add('ic-region-modal-open');
      renderModal();
    }

    function renderModal() {
      if (!modalBox) return;
      modalImg.src = cropToDataUrl(modalBox);
      modalImg.alt = modalBox.label || 'Vùng khoanh';
      modalRotateInput.value = String(Math.round(modalBox.rotation || 0));
      modalRotateRange.value = String(Math.round(modalBox.rotation || 0));
    }

    function closeModal() {
      modalBox = null;
      modal.hidden = true;
      modalImg.removeAttribute('src');
      document.body.classList.remove('ic-region-modal-open');
    }

    modal.querySelector('.ic-region-modal-close').addEventListener('click', closeModal);
    modal.querySelector('.ic-region-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', (evt) => {
      if (evt.key === 'Escape' && !modal.hidden) closeModal();
      if (evt.key === 'Escape' && exportModal && !exportModal.hidden) closeExportModal();
    });

    // Điều khiển xoay/lật trong modal thao tác trên `modalBox` (vùng đang mở)
    // — gắn 1 lần duy nhất, không cần rebind mỗi lần mở modal.
    let modalDragSnapshot = null;
    let modalRafPending = false;

    function modalLiveRotate(deg) {
      if (!modalBox) return;
      modalBox.rotation = normalizeDeg(deg);
      modalRotateInput.value = String(Math.round(modalBox.rotation));
      modalRotateRange.value = String(Math.round(modalBox.rotation));
      if (modalRafPending) return;
      modalRafPending = true;
      requestAnimationFrame(() => {
        refreshBoxVisuals(modalBox);
        modalRafPending = false;
      });
    }

    modalRotate90.addEventListener('click', () => { if (modalBox) stepBoxRotation90(modalBox); });
    modalRotateInput.addEventListener('change', () => { if (modalBox) setBoxRotation(modalBox, modalRotateInput.value); });
    modalRotateRange.addEventListener('pointerdown', () => { modalDragSnapshot = snapshotState(); });
    modalRotateRange.addEventListener('input', () => modalLiveRotate(modalRotateRange.value));
    modalRotateRange.addEventListener('change', () => {
      if (modalBox && modalDragSnapshot) {
        const snap = modalDragSnapshot;
        modalDragSnapshot = null;
        setBoxRotation(modalBox, modalRotateRange.value, snap);
      }
    });
    modalFlip.addEventListener('click', () => { if (modalBox) toggleBoxFlip(modalBox); });
    modalReset.addEventListener('click', () => { if (modalBox) resetBoxTransform(modalBox); });

    function renderBoxList() {
      boxCountEl.textContent = String(boxes.length);
      emptyHintEl.hidden = boxes.length > 0;
      saveBtn.disabled = boxes.length === 0;
      duplicateBtn.disabled = selectedBoxes().length === 0;

      thumbRefs.clear();
      positionRefs.clear();
      boxListEl.innerHTML = '';
      boxes.forEach((box, index) => {
        const li = document.createElement('li');
        li.className = 'ic-box-item' + (box.selected ? ' is-selected' : '');

        const selectWrap = document.createElement('label');
        selectWrap.className = 'ic-box-select';
        selectWrap.title = 'Chọn để thao tác cùng nhiều vùng khác';
        const selectCb = document.createElement('input');
        selectCb.type = 'checkbox';
        selectCb.checked = !!box.selected;
        selectCb.addEventListener('change', () => {
          box.selected = selectCb.checked;
          drawScene();
          renderBoxList();
        });
        selectWrap.appendChild(selectCb);
        li.appendChild(selectWrap);

        const badge = document.createElement('span');
        badge.className = 'ic-box-badge';
        badge.textContent = String(index + 1);
        li.appendChild(badge);

        const thumbBtn = document.createElement('button');
        thumbBtn.type = 'button';
        thumbBtn.className = 'ic-box-thumb';
        thumbBtn.title = 'Xem lớn / xoay / lật';
        const thumb = document.createElement('img');
        thumb.src = cropToDataUrl(box);
        thumb.alt = `Vùng ${index + 1}`;
        thumbBtn.appendChild(thumb);
        thumbBtn.addEventListener('click', () => openModal(box, index));
        li.appendChild(thumbBtn);
        thumbRefs.set(box.id, { img: thumb });

        const fields = document.createElement('div');
        fields.className = 'ic-box-fields';

        const label = document.createElement('input');
        label.type = 'text';
        label.value = box.label;
        label.placeholder = `vung-${index + 1}`;
        label.addEventListener('input', () => { box.label = label.value; });
        fields.appendChild(label);

        const controls = document.createElement('div');
        controls.className = 'ic-box-controls';

        const dims = document.createElement('div');
        dims.className = 'ic-box-dims';

        function makeDimField(labelText, currentValue, onChange) {
          const wrap = document.createElement('label');
          wrap.textContent = labelText;
          const input = document.createElement('input');
          input.type = 'number';
          input.value = String(Math.round(currentValue));
          input.addEventListener('change', () => {
            const next = parseInt(input.value, 10);
            if (Number.isFinite(next)) onChange(next);
          });
          wrap.appendChild(input);
          dims.appendChild(wrap);
          return input;
        }

        const xInput = makeDimField('X', box.x, (next) => applyDelta(box, next - box.x, 0, 0, 0));
        const yInput = makeDimField('Y', box.y, (next) => applyDelta(box, 0, next - box.y, 0, 0));
        xInput.title = 'Vị trí trái (px)';
        yInput.title = 'Vị trí trên (px)';
        positionRefs.set(box.id, { xInput, yInput });

        const wInput = makeDimField('W', box.w, (next) => applyDelta(box, 0, 0, next - box.w, 0));
        wInput.min = String(MIN_BOX_SIZE);
        const hInput = makeDimField('H', box.h, (next) => applyDelta(box, 0, 0, 0, next - box.h));
        hInput.min = String(MIN_BOX_SIZE);

        controls.appendChild(dims);

        const nudge = document.createElement('div');
        nudge.className = 'ic-box-nudge';
        [
          ['up', '↑', 0, -1],
          ['down', '↓', 0, 1],
          ['left', '←', -1, 0],
          ['right', '→', 1, 0],
        ].forEach(([dir, arrow, dx, dy]) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'ic-nudge';
          btn.dataset.dir = dir;
          btn.textContent = arrow;
          btn.title = 'Giữ Shift để di chuyển tinh chỉnh (2px)';
          btn.addEventListener('click', (evt) => {
            const step = evt.shiftKey ? NUDGE_STEP_FINE : NUDGE_STEP;
            applyDelta(box, dx * step, dy * step, 0, 0);
          });
          nudge.appendChild(btn);
        });
        controls.appendChild(nudge);

        fields.appendChild(controls);

        const rotateFlip = document.createElement('div');
        rotateFlip.className = 'ic-box-rotate';

        const step90Btn = document.createElement('button');
        step90Btn.type = 'button';
        step90Btn.className = 'ic-icon-btn';
        step90Btn.title = 'Xoay 90°';
        step90Btn.innerHTML = '<svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M10 3a7 7 0 1 0 6.32 4H14.9A5.5 5.5 0 1 1 10 4.5v2.6l4-3.1-4-3.1V3Z"/></svg>';
        rotateFlip.appendChild(step90Btn);

        const rotateInput = document.createElement('input');
        rotateInput.type = 'number';
        rotateInput.className = 'ic-box-rotate-input';
        rotateInput.min = '0';
        rotateInput.max = '359';
        rotateInput.title = 'Góc xoay (độ)';
        rotateFlip.appendChild(rotateInput);

        const flipBtn = document.createElement('button');
        flipBtn.type = 'button';
        flipBtn.className = 'ic-icon-btn' + (box.flipped ? ' is-active' : '');
        flipBtn.title = 'Lật ngang (đối chiếu gương)';
        flipBtn.innerHTML = '<svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round" d="M10 2v16M4 6l3 2.5-3 2.5M16 6l-3 2.5 3 2.5"/></svg>';
        rotateFlip.appendChild(flipBtn);

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'ic-icon-btn';
        resetBtn.title = 'Về góc/chiều ban đầu';
        resetBtn.innerHTML = '<svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M4 4v5h5M4.5 9A5.5 5.5 0 1 1 6 13" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        rotateFlip.appendChild(resetBtn);

        fields.appendChild(rotateFlip);

        bindRotateFlipControls(box, {
          step90Btn,
          numberInput: rotateInput,
          rangeInput: null,
          flipBtn,
          resetBtn,
        });

        const size = document.createElement('span');
        size.className = 'ic-box-size';
        size.textContent = `${box.w} × ${box.h} px${box.rotation ? ` · ${Math.round(box.rotation)}°` : ''}${box.flipped ? ' · lật' : ''}`;
        fields.appendChild(size);

        li.appendChild(fields);

        const itemActions = document.createElement('div');
        itemActions.className = 'ic-box-item-actions';

        const duplicateItemBtn = document.createElement('button');
        duplicateItemBtn.type = 'button';
        duplicateItemBtn.className = 'btn btn-secondary btn-sm ic-box-duplicate';
        duplicateItemBtn.title = 'Sao chép tại chỗ (giữ nguyên vị trí, để cắt 2 ảnh khác nhau từ cùng 1 vùng)';
        duplicateItemBtn.textContent = 'Sao chép';
        duplicateItemBtn.addEventListener('click', () => duplicateInPlace(box));
        itemActions.appendChild(duplicateItemBtn);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-secondary btn-sm ic-box-remove';
        removeBtn.textContent = 'Xóa';
        removeBtn.addEventListener('click', () => {
          commitHistory(snapshotState());
          boxes = boxes.filter((b) => b.id !== box.id);
          if (modalBox && modalBox.id === box.id) closeModal();
          drawScene();
          renderBoxList();
        });
        itemActions.appendChild(removeBtn);

        li.appendChild(itemActions);

        boxListEl.appendChild(li);
      });
    }

    // Danh sách "Ảnh đã lưu" — độc lập với "Danh sách vùng đã khoanh": xóa 1
    // ảnh ở đây chỉ xóa bản đã lưu trên server; có thể tick thêm để xóa luôn
    // box tương ứng khỏi "Danh sách vùng đã khoanh" (mặc định giữ lại box,
    // chỉ mất liên kết regionId, coi như chưa lưu).
    function renderResultsList(items) {
      resultListEl.innerHTML = '';
      items.forEach((item) => {
        const li = document.createElement('li');
        li.className = 'ic-result-item';
        li.dataset.regionId = String(item.id);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'ic-result-remove';
        removeBtn.title = 'Xóa ảnh đã cắt này';
        removeBtn.innerHTML = '<svg viewBox="0 0 20 20" width="13" height="13" aria-hidden="true"><path fill="currentColor" d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
        removeBtn.addEventListener('click', () => deleteSavedRegion(item.id, li));
        li.appendChild(removeBtn);

        const thumb = document.createElement('img');
        thumb.src = item.url;
        thumb.alt = item.label || '';
        li.appendChild(thumb);

        const link = document.createElement('a');
        link.href = item.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = item.url;
        li.appendChild(link);

        resultListEl.appendChild(li);
      });
      resultsCard.hidden = items.length === 0;
    }

    async function deleteSavedRegion(regionId, liEl) {
      if (!sourceId || !regionId) return;

      let confirmed = false;
      let alsoRemoveBox = false;

      if (window.AdminConfirm) {
        const result = await AdminConfirm.show({
          title: 'Xóa ảnh đã cắt?',
          message: 'Ảnh đã lưu sẽ bị xóa khỏi server.',
          confirmText: 'Xóa',
          cancelText: 'Hủy',
          danger: true,
          checkbox: { label: 'Xóa luôn vùng khoanh này khỏi "Danh sách vùng đã khoanh"' },
        });
        confirmed = result.confirmed;
        alsoRemoveBox = result.checked;
      } else {
        confirmed = window.confirm('Xóa ảnh đã cắt này?');
      }
      if (!confirmed) return;

      try {
        const url = regionDeleteUrlTemplate.replace('__ID__', sourceId).replace('__REGION__', regionId);
        const res = await fetch(url, {
          method: 'DELETE',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error(data.message || data.error || 'Không xóa được ảnh.');
        }

        liEl.remove();
        resultsCard.hidden = resultListEl.children.length === 0;

        if (alsoRemoveBox) {
          boxes = boxes.filter((b) => b.regionId !== regionId);
          if (modalBox && modalBox.regionId === regionId) closeModal();
          drawScene();
          renderBoxList();
        } else {
          const box = boxes.find((b) => b.regionId === regionId);
          if (box) box.regionId = null;
        }

        toast('Đã xóa ảnh đã cắt.', 'success');
      } catch (err) {
        toast(err.message || 'Có lỗi khi xóa ảnh.', 'error');
      }
    }

    // ── Zoom khung nhìn — canvas.width/height (bitmap thật) tăng theo
    // renderScale() để không vỡ nét, canvas.style.width/height giữ kích
    // thước hiển thị theo zoomLevel. Đổi canvas.width/height luôn xóa nội
    // dung cũ (theo spec) nên luôn phải drawScene() lại sau đó. ──
    function applyZoom() {
      if (!img || !imgW || !imgH) return;
      zoomLevel = clamp(zoomLevel, ZOOM_MIN, ZOOM_MAX);
      const scale = renderScale();
      canvas.width = Math.max(1, Math.round(imgW * scale));
      canvas.height = Math.max(1, Math.round(imgH * scale));
      canvas.style.width = Math.round(imgW * zoomLevel) + 'px';
      canvas.style.height = Math.round(imgH * zoomLevel) + 'px';
      if (zoomResetBtn) zoomResetBtn.textContent = Math.round(zoomLevel * 100) + '%';
      drawScene();
      drawRulers();
      renderGuides();
    }

    function setZoom(level) {
      zoomLevel = level;
      applyZoom();
    }

    if (zoomInBtn) zoomInBtn.addEventListener('click', () => setZoom(zoomLevel + ZOOM_STEP));
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', () => setZoom(zoomLevel - ZOOM_STEP));
    if (zoomResetBtn) zoomResetBtn.addEventListener('click', () => setZoom(1));

    // ── Xoay / lật cả ảnh gốc — biến đổi luôn tọa độ mọi vùng đã khoanh để
    // chúng vẫn khớp đúng nội dung hình ảnh sau khi xoay/lật. ──
    function rotateImageWhole90() {
      if (!img) return;
      commitHistory(snapshotState());

      const oldW = imgW;
      const oldH = imgH;
      const off = document.createElement('canvas');
      off.width = oldH;
      off.height = oldW;
      const offCtx = off.getContext('2d');
      offCtx.translate(off.width, 0);
      offCtx.rotate(Math.PI / 2);
      offCtx.drawImage(img, 0, 0, oldW, oldH);

      img = off;
      imgW = oldH;
      imgH = oldW;

      boxes.forEach((b) => {
        const { x, y, w, h } = b;
        b.x = oldH - y - h;
        b.y = x;
        b.w = h;
        b.h = w;
      });

      // Guide gắn theo hệ tọa độ ảnh cũ — không còn ý nghĩa sau khi xoay,
      // xóa để tránh hiển thị sai vị trí.
      guides = { h: [], v: [] };

      applyZoom();
      renderBoxList();
      if (modalBox) renderModal();
    }

    function flipImageWhole() {
      if (!img) return;
      commitHistory(snapshotState());

      const w = imgW;
      const h = imgH;
      const off = document.createElement('canvas');
      off.width = w;
      off.height = h;
      const offCtx = off.getContext('2d');
      offCtx.translate(w, 0);
      offCtx.scale(-1, 1);
      offCtx.drawImage(img, 0, 0, w, h);

      img = off;

      boxes.forEach((b) => { b.x = w - b.x - b.w; });

      // Guide gắn theo hệ tọa độ ảnh cũ — không còn ý nghĩa sau khi lật,
      // xóa để tránh hiển thị sai vị trí.
      guides = { h: [], v: [] };
      renderGuides();

      drawScene();
      renderBoxList();
      if (modalBox) renderModal();
    }

    if (imageRotateBtn) imageRotateBtn.addEventListener('click', rotateImageWhole90);
    if (imageFlipBtn) imageFlipBtn.addEventListener('click', flipImageWhole);

    // ── Ruler: vẽ vạch chia theo px ảnh gốc, tự co giãn theo zoom, tự vẽ
    // lại phần đang hiển thị khi cuộn (không cần đồng bộ scroll bằng CSS). ──
    function niceTickInterval() {
      for (let i = 0; i < RULER_TICK_CANDIDATES.length; i += 1) {
        if (RULER_TICK_CANDIDATES[i] * zoomLevel >= RULER_MIN_TICK_PX) return RULER_TICK_CANDIDATES[i];
      }
      return RULER_TICK_CANDIDATES[RULER_TICK_CANDIDATES.length - 1];
    }

    function drawRulers() {
      if (!img || !rulerHCanvas || !rulerVCanvas) return;
      const viewW = Math.max(1, canvasWrapEl.clientWidth);
      const viewH = Math.max(1, canvasWrapEl.clientHeight);
      const scrollL = canvasWrapEl.scrollLeft;
      const scrollT = canvasWrapEl.scrollTop;
      const interval = niceTickInterval();

      rulerHCanvas.width = viewW;
      rulerHCanvas.height = RULER_SIZE;
      const hCtx = rulerHCanvas.getContext('2d');
      hCtx.clearRect(0, 0, viewW, RULER_SIZE);
      hCtx.fillStyle = '#f3f4f6';
      hCtx.fillRect(0, 0, viewW, RULER_SIZE);
      hCtx.strokeStyle = '#9ca3af';
      hCtx.fillStyle = '#4b5563';
      hCtx.font = '9px system-ui, sans-serif';
      hCtx.textBaseline = 'top';
      const startV = Math.max(0, Math.floor(scrollL / zoomLevel / interval) * interval);
      const endV = Math.min(imgW, Math.ceil((scrollL + viewW) / zoomLevel / interval) * interval);
      for (let v = startV; v <= endV; v += interval) {
        const screenX = v * zoomLevel - scrollL;
        hCtx.beginPath();
        hCtx.moveTo(screenX + 0.5, RULER_SIZE - 8);
        hCtx.lineTo(screenX + 0.5, RULER_SIZE);
        hCtx.stroke();
        hCtx.fillText(String(v), screenX + 2, 2);
      }

      rulerVCanvas.width = RULER_SIZE;
      rulerVCanvas.height = viewH;
      const vCtx = rulerVCanvas.getContext('2d');
      vCtx.clearRect(0, 0, RULER_SIZE, viewH);
      vCtx.fillStyle = '#f3f4f6';
      vCtx.fillRect(0, 0, RULER_SIZE, viewH);
      vCtx.strokeStyle = '#9ca3af';
      vCtx.fillStyle = '#4b5563';
      vCtx.font = '9px system-ui, sans-serif';
      vCtx.textBaseline = 'top';
      const startVv = Math.max(0, Math.floor(scrollT / zoomLevel / interval) * interval);
      const endVv = Math.min(imgH, Math.ceil((scrollT + viewH) / zoomLevel / interval) * interval);
      for (let v = startVv; v <= endVv; v += interval) {
        const screenY = v * zoomLevel - scrollT;
        vCtx.beginPath();
        vCtx.moveTo(RULER_SIZE - 8, screenY + 0.5);
        vCtx.lineTo(RULER_SIZE, screenY + 0.5);
        vCtx.stroke();
        vCtx.fillText(String(v), 2, screenY + 2);
      }
    }

    if (canvasWrapEl) canvasWrapEl.addEventListener('scroll', drawRulers);
    window.addEventListener('resize', () => { if (img) drawRulers(); });

    // ── Guide: đường kẻ đo kéo từ ruler vào, không thuộc dữ liệu ảnh, chỉ
    // là overlay DOM để đo — không lưu server. ──
    function renderGuides() {
      if (!guideLayerEl) return;
      guideLayerEl.innerHTML = '';
      guides.h.forEach((g) => guideLayerEl.appendChild(makeGuideEl('h', g)));
      guides.v.forEach((g) => guideLayerEl.appendChild(makeGuideEl('v', g)));
    }

    function updateGuideElPosition(el, axis, pos) {
      const screenPos = Math.round(pos * zoomLevel);
      if (axis === 'h') el.style.top = screenPos + 'px';
      else el.style.left = screenPos + 'px';
      const label = el.querySelector('.ic-guide-label');
      if (label) label.textContent = `${axis === 'h' ? 'Top' : 'Left'}: ${Math.round(pos)}px`;
    }

    function makeGuideEl(axis, guideObj) {
      const el = document.createElement('div');
      el.className = `ic-guide ic-guide-${axis}`;
      el.style.borderColor = guideColor;
      if (axis === 'h') el.style.borderTopWidth = guideThickness + 'px';
      else el.style.borderLeftWidth = guideThickness + 'px';

      const label = document.createElement('span');
      label.className = 'ic-guide-label';
      el.appendChild(label);

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'ic-guide-delete';
      del.textContent = '×';
      del.title = 'Xóa đường kẻ';
      del.addEventListener('mousedown', (e) => e.stopPropagation());
      del.addEventListener('click', () => {
        guides[axis] = guides[axis].filter((g) => g.id !== guideObj.id);
        renderGuides();
      });
      el.appendChild(del);

      el.addEventListener('mousedown', (e) => {
        if (e.target === del) return;
        startGuideDrag(axis, guideObj, e);
      });

      updateGuideElPosition(el, axis, guideObj.pos);
      return el;
    }

    function startGuideDrag(axis, guideObj, evt) {
      evt.preventDefault();
      evt.stopPropagation();
      function onMove(e) {
        const point = pointFromEvent(e);
        guideObj.pos = Math.round(axis === 'h' ? point.y : point.x);
        renderGuides();
      }
      function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    }

    function startGuideCreation(axis, evt) {
      if (!img) return;
      evt.preventDefault();
      const point = pointFromEvent(evt);
      guideSeq += 1;
      const guideObj = { id: guideSeq, pos: Math.round(axis === 'h' ? point.y : point.x) };
      guides[axis].push(guideObj);
      renderGuides();
      startGuideDrag(axis, guideObj, evt);
    }

    if (rulerHEl) rulerHEl.addEventListener('mousedown', (evt) => startGuideCreation('h', evt));
    if (rulerVEl) rulerVEl.addEventListener('mousedown', (evt) => startGuideCreation('v', evt));

    if (guideColorInput) {
      guideColorInput.addEventListener('input', () => {
        guideColor = guideColorInput.value;
        renderGuides();
      });
    }
    if (guideThicknessInput) {
      guideThicknessInput.addEventListener('input', () => {
        guideThickness = Number(guideThicknessInput.value) || 1;
        renderGuides();
      });
    }
    if (clearGuidesBtn) {
      clearGuidesBtn.addEventListener('click', () => {
        guides = { h: [], v: [] };
        renderGuides();
      });
    }

    // ── "Tạo khung" bằng cách nhập W/H trực tiếp — hiển thị real-time,
    // không cần nút submit riêng để khung xuất hiện (nhập tới đâu vẽ tới đó). ──
    function openCreateDimsPanel() {
      if (!img) return;
      closeCreateDimsPanel(false);

      dimsPanelSnapshot = snapshotState();
      boxSeq += 1;
      dimsPanelBox = {
        id: boxSeq,
        regionId: null,
        x: 0,
        y: 0,
        w: 0,
        h: 0,
        rotation: 0,
        flipped: false,
        label: `vung-${boxSeq}`,
        selected: true,
      };
      clearSelection();
      boxes.push(dimsPanelBox);

      dimsXInput.value = '0';
      dimsYInput.value = '0';
      dimsWInput.value = '';
      dimsHInput.value = '';
      createDimsPanel.hidden = false;
      drawScene();
      renderBoxList();
      dimsWInput.focus();
    }

    function updateDimsPanelBoxFromInputs() {
      if (!dimsPanelBox) return;
      dimsPanelBox.x = parseInt(dimsXInput.value, 10) || 0;
      dimsPanelBox.y = parseInt(dimsYInput.value, 10) || 0;
      dimsPanelBox.w = Math.max(0, parseInt(dimsWInput.value, 10) || 0);
      dimsPanelBox.h = Math.max(0, parseInt(dimsHInput.value, 10) || 0);
      drawScene();
      renderBoxList();
    }

    function closeCreateDimsPanel(commit) {
      if (dimsPanelBox) {
        const tooSmall = dimsPanelBox.w < MIN_BOX_SIZE || dimsPanelBox.h < MIN_BOX_SIZE;
        if (!commit || tooSmall) {
          boxes = boxes.filter((b) => b.id !== dimsPanelBox.id);
        } else if (dimsPanelSnapshot) {
          commitHistory(dimsPanelSnapshot);
        }
      }
      dimsPanelBox = null;
      dimsPanelSnapshot = null;
      if (createDimsPanel) createDimsPanel.hidden = true;
      drawScene();
      renderBoxList();
    }

    if (createByDimsBtn) createByDimsBtn.addEventListener('click', openCreateDimsPanel);
    if (dimsDoneBtn) dimsDoneBtn.addEventListener('click', () => closeCreateDimsPanel(true));
    if (dimsCancelBtn) dimsCancelBtn.addEventListener('click', () => closeCreateDimsPanel(false));
    [dimsXInput, dimsYInput, dimsWInput, dimsHInput].forEach((el) => {
      if (el) el.addEventListener('input', updateDimsPanelBoxFromInputs);
    });

    function resetWorkspace() {
      boxes = [];
      boxSeq = 0;
      mode = null;
      dragStart = null;
      dragCurrent = null;
      moveOrigins = null;
      pendingClickBox = null;
      dragSnapshot = null;
      resizeBox = null;
      resizeCorner = null;
      resizeAnchor = null;
      resizeChanged = false;
      history = [];
      future = [];
      clipboard = [];
      pasteCount = 0;
      guides = { h: [], v: [] };
      guideSeq = 0;
      zoomLevel = 1;
      dimsPanelBox = null;
      dimsPanelSnapshot = null;
      if (createDimsPanel) createDimsPanel.hidden = true;
      resultsCard.hidden = true;
      resultListEl.innerHTML = '';
      renderBoxList();
      renderGuides();
    }

    function showImage(image) {
      img = image;
      imgW = image.naturalWidth || image.width;
      imgH = image.naturalHeight || image.height;
      workspace.hidden = false;
      resetWorkspace();
      applyZoom();
    }

    async function uploadSourceImage(file) {
      const formData = new FormData();
      formData.append('image', file);

      const res = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || data.error || 'Không tải được ảnh lên.');
      }

      return data.data;
    }

    function loadImageFile(file) {
      if (uploadHint) uploadHint.textContent = 'Đang tải ảnh lên...';
      if (fileInput) fileInput.disabled = true;

      uploadSourceImage(file)
        .then((source) => {
          sourceId = source.id;

          const reader = new FileReader();
          reader.onload = () => {
            const image = new Image();
            image.onload = () => showImage(image);
            image.onerror = () => toast('Không đọc được ảnh này.', 'error');
            image.src = reader.result;
          };
          reader.onerror = () => toast('Không đọc được file ảnh.', 'error');
          reader.readAsDataURL(file);
        })
        .catch((err) => {
          toast(err.message || 'Có lỗi khi tải ảnh lên.', 'error');
        })
        .finally(() => {
          if (uploadHint) uploadHint.textContent = 'Chấp nhận JPG, PNG, WebP...';
          if (fileInput) fileInput.disabled = false;
        });
    }

    if (fileInput) {
      fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (file) loadImageFile(file);
      });
    }

    if (changeImageBtn) {
      changeImageBtn.addEventListener('click', () => {
        fileInput.value = '';
        fileInput.click();
      });
    }

    canvas.addEventListener('mousedown', (evt) => {
      if (!img) return;
      const point = pointFromEvent(evt);
      const multiSelect = evt.shiftKey || evt.ctrlKey || evt.metaKey;

      const handle = hitTestHandle(point);
      if (handle) {
        dragSnapshot = snapshotState();
        mode = 'resize';
        resizeBox = handle.box;
        resizeCorner = handle.corner;
        resizeAnchor = oppositeCorner(handle.box, handle.corner);
        resizeChanged = false;
        return;
      }

      const hit = hitTestBox(point);

      if (hit) {
        dragSnapshot = snapshotState();
        mode = 'move';
        movedDuringDrag = false;
        pendingClickBox = hit;
        pendingClickMulti = multiSelect;

        if (!hit.selected && !multiSelect) clearSelection();
        hit.selected = true;

        dragStart = point;
        moveOrigins = selectedBoxes().map((b) => ({ id: b.id, x: b.x, y: b.y }));
        drawScene();
        renderBoxList();
        return;
      }

      dragSnapshot = snapshotState();
      clearSelection();
      mode = 'draw';
      dragStart = point;
      dragCurrent = point;
      drawScene();
      renderBoxList();
    });

    // mousemove/mouseup gắn ở document (không phải canvas) để việc kéo vùng
    // ra ngoài rìa ảnh vẫn tiếp tục hoạt động dù chuột đã rời khỏi canvas.
    document.addEventListener('mousemove', (evt) => {
      if (!mode) {
        if (img && canvasStageEl && canvasStageEl.contains(evt.target)) {
          updateHoverCursor(pointFromEvent(evt));
        }
        return;
      }

      const point = pointFromEvent(evt);

      if (mode === 'draw') {
        dragCurrent = point;
        drawScene();
        return;
      }

      if (mode === 'move' && moveOrigins) {
        const dx = point.x - dragStart.x;
        const dy = point.y - dragStart.y;
        if (Math.abs(dx) > MOVE_THRESHOLD || Math.abs(dy) > MOVE_THRESHOLD) {
          movedDuringDrag = true;
        }
        if (movedDuringDrag) {
          moveOrigins.forEach((origin) => {
            const b = boxes.find((bx) => bx.id === origin.id);
            if (!b) return;
            b.x = origin.x + dx;
            b.y = origin.y + dy;
            const ref = positionRefs.get(b.id);
            if (ref) {
              ref.xInput.value = String(Math.round(b.x));
              ref.yInput.value = String(Math.round(b.y));
            }
          });
          drawScene();
        }
        return;
      }

      if (mode === 'resize' && resizeBox && resizeAnchor) {
        const rect = normalizeRect(resizeAnchor, point);
        resizeBox.x = rect.x;
        resizeBox.y = rect.y;
        resizeBox.w = Math.max(MIN_BOX_SIZE, rect.w);
        resizeBox.h = Math.max(MIN_BOX_SIZE, rect.h);
        resizeChanged = true;
        const ref = positionRefs.get(resizeBox.id);
        if (ref) {
          ref.xInput.value = String(Math.round(resizeBox.x));
          ref.yInput.value = String(Math.round(resizeBox.y));
        }
        drawScene();
      }
    });

    function finishPointer() {
      if (mode === 'draw') {
        const rect = normalizeRect(dragStart, dragCurrent);
        if (rect.w >= MIN_BOX_SIZE && rect.h >= MIN_BOX_SIZE) {
          boxSeq += 1;
          boxes.push({
            id: boxSeq,
            regionId: null,
            x: rect.x,
            y: rect.y,
            w: rect.w,
            h: rect.h,
            rotation: 0,
            flipped: false,
            label: `vung-${boxSeq}`,
            selected: false,
          });
          if (dragSnapshot) commitHistory(dragSnapshot);
        }
      } else if (mode === 'move' && pendingClickBox) {
        if (!movedDuringDrag) {
          if (pendingClickMulti) {
            pendingClickBox.selected = !pendingClickBox.selected;
          } else {
            clearSelection();
            pendingClickBox.selected = true;
          }
        } else if (dragSnapshot) {
          commitHistory(dragSnapshot);
        }
      } else if (mode === 'resize') {
        if (resizeChanged && dragSnapshot) commitHistory(dragSnapshot);
      }

      mode = null;
      dragStart = null;
      dragCurrent = null;
      moveOrigins = null;
      movedDuringDrag = false;
      pendingClickBox = null;
      pendingClickMulti = false;
      dragSnapshot = null;
      resizeBox = null;
      resizeCorner = null;
      resizeAnchor = null;
      resizeChanged = false;

      drawScene();
      renderBoxList();
    }

    document.addEventListener('mouseup', () => {
      if (mode) finishPointer();
    });

    undoBtn.addEventListener('click', () => {
      if (boxes.length === 0) return;
      commitHistory(snapshotState());
      boxes.pop();
      drawScene();
      renderBoxList();
    });

    clearBtn.addEventListener('click', () => {
      if (boxes.length === 0) return;
      commitHistory(snapshotState());
      boxes = [];
      drawScene();
      renderBoxList();
    });

    duplicateBtn.addEventListener('click', () => {
      duplicateSelected();
    });

    document.addEventListener('keydown', (evt) => {
      const target = evt.target;
      const isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA');
      const ctrlOrCmd = evt.ctrlKey || evt.metaKey;

      if (ctrlOrCmd && !isTyping && (evt.key === 'z' || evt.key === 'Z')) {
        evt.preventDefault();
        undo();
        return;
      }

      if (ctrlOrCmd && !isTyping && (evt.key === 'y' || evt.key === 'Y')) {
        evt.preventDefault();
        redo();
        return;
      }

      if (ctrlOrCmd && !isTyping && (evt.key === 'c' || evt.key === 'C')) {
        const targets = selectedBoxes();
        if (targets.length === 0) return;
        clipboard = targets.map((b) => ({ ...b }));
        pasteCount = 0;
        toast(`Đã copy ${targets.length} vùng.`, 'success');
        return;
      }

      if (ctrlOrCmd && !isTyping && (evt.key === 'v' || evt.key === 'V')) {
        if (clipboard.length === 0) return;
        evt.preventDefault();
        commitHistory(snapshotState());
        pasteCount += 1;
        const offset = DUPLICATE_OFFSET * pasteCount;
        const pasted = clipboard.map((b) => {
          boxSeq += 1;
          return { ...b, id: boxSeq, regionId: null, x: b.x + offset, y: b.y + offset, selected: true };
        });
        clearSelection();
        boxes.push(...pasted);
        drawScene();
        renderBoxList();
        return;
      }

      if (!isTyping && evt.key === 'Delete') {
        const targets = selectedBoxes();
        if (targets.length === 0) return;
        evt.preventDefault();
        commitHistory(snapshotState());
        const ids = new Set(targets.map((b) => b.id));
        boxes = boxes.filter((b) => !ids.has(b.id));
        drawScene();
        renderBoxList();
        return;
      }

      const arrowDirs = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] };
      if (!isTyping && arrowDirs[evt.key]) {
        const targets = selectedBoxes();
        if (targets.length === 0) return;
        evt.preventDefault();
        const [dx, dy] = arrowDirs[evt.key];
        const step = evt.shiftKey ? NUDGE_STEP_FINE : NUDGE_STEP;
        applyDelta(targets[0], dx * step, dy * step, 0, 0);
      }
    });

    // ── Modal tùy chọn xuất ảnh (định dạng/chất lượng/DPI) — mở khi bấm
    // "Cắt & Lưu tất cả", chỉ thật sự lưu lên server khi bấm "Xác nhận". ──
    function updateExportFormatUI() {
      const lossy = ['jpeg', 'webp', 'avif'].includes(exportFormatSelect.value);
      exportQualityGroup.hidden = !lossy;
    }

    function openExportModal() {
      if (boxes.length === 0 || !sourceId) return;
      updateExportFormatUI();
      exportModal.hidden = false;
      document.body.classList.add('ic-export-modal-open');
    }

    function closeExportModal() {
      exportModal.hidden = true;
      document.body.classList.remove('ic-export-modal-open');
    }

    if (exportFormatSelect) exportFormatSelect.addEventListener('change', updateExportFormatUI);
    if (exportQualityInput) {
      exportQualityInput.addEventListener('input', () => {
        exportQualityValueEl.textContent = exportQualityInput.value;
      });
    }
    if (exportPresetSelect) {
      exportPresetSelect.addEventListener('change', () => {
        exportDpiInput.value = String(DPI_PRESETS[exportPresetSelect.value] ?? 96);
      });
    }
    if (exportModal) {
      exportModal.querySelector('.ic-export-modal-close').addEventListener('click', closeExportModal);
      exportModal.querySelector('.ic-export-modal-backdrop').addEventListener('click', closeExportModal);
    }
    if (exportCancelBtn) exportCancelBtn.addEventListener('click', closeExportModal);

    saveBtn.addEventListener('click', openExportModal);

    // Xuất 1 vùng theo định dạng đã chọn. AVIF không được mọi trình duyệt hỗ
    // trợ mã hóa qua canvas — theo spec, trình duyệt sẽ tự rơi về PNG, việc
    // này kiểm tra bằng chính tiền tố "data:image/..." trả về (không tin vào
    // lựa chọn của người dùng) để biết có cần báo cho họ hay không.
    function exportBoxDataUrl(box, exportOptions) {
      const off = renderRegionCanvas(box);

      if (exportOptions.format === 'svg') {
        const pngUrl = off.toDataURL('image/png');
        return { dataUrl: wrapAsSvg(pngUrl, off.width, off.height), fellBack: false };
      }

      const mimeMap = { png: 'image/png', jpeg: 'image/jpeg', webp: 'image/webp', avif: 'image/avif' };
      const mime = mimeMap[exportOptions.format] || 'image/png';
      let dataUrl = off.toDataURL(mime, exportOptions.quality);
      const fellBack = !dataUrl.startsWith(`data:${mime}`);

      if (dataUrl.startsWith('data:image/png') && exportOptions.dpi) {
        dataUrl = injectPngDpi(dataUrl, exportOptions.dpi);
      }

      return { dataUrl, fellBack };
    }

    if (exportConfirmBtn) {
      exportConfirmBtn.addEventListener('click', async () => {
        if (boxes.length === 0 || !sourceId) return;

        const exportOptions = {
          format: exportFormatSelect.value,
          quality: clamp(Number(exportQualityInput.value) / 100 || 0.92, 0.5, 1),
          dpi: Math.max(1, Number(exportDpiInput.value) || 96),
        };

        exportConfirmBtn.disabled = true;
        exportConfirmBtn.textContent = 'Đang lưu...';

        try {
          let anyFallback = false;
          const regions = boxes.map((box) => {
            const { dataUrl, fellBack } = exportBoxDataUrl(box, exportOptions);
            if (fellBack) anyFallback = true;
            return {
              id: box.regionId,
              label: box.label,
              x: Math.round(box.x),
              y: Math.round(box.y),
              w: Math.round(box.w),
              h: Math.round(box.h),
              rotation: box.rotation || 0,
              flipped: !!box.flipped,
              data: dataUrl,
            };
          });

          const regionsUrl = regionsUrlTemplate.replace('__ID__', sourceId);
          const res = await fetch(regionsUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ regions, preview: renderPreviewCanvas() }),
          });

          const data = await res.json().catch(() => ({}));

          if (!res.ok) {
            throw new Error(data.message || data.error || 'Không lưu được ảnh.');
          }

          const saved = data.data?.regions || [];
          saved.forEach((item, index) => {
            if (boxes[index]) boxes[index].regionId = item.id;
          });

          renderResultsList(saved);
          closeExportModal();

          if (anyFallback) {
            toast('Một số định dạng không được trình duyệt hỗ trợ xuất — đã tự động dùng PNG cho vùng đó.', 'error');
          } else {
            toast(`Đã lưu ${saved.length} ảnh.`, 'success');
          }
        } catch (err) {
          toast(err.message || 'Có lỗi khi lưu ảnh.', 'error');
        } finally {
          exportConfirmBtn.disabled = false;
          exportConfirmBtn.textContent = 'Xác nhận';
        }
      });
    }

    if (sourceBoot) {
      const image = new Image();
      image.onload = () => {
        showImage(image);
        boxes = (sourceBoot.regions || []).map((region) => {
          boxSeq += 1;
          return {
            id: boxSeq,
            regionId: region.id,
            x: region.x,
            y: region.y,
            w: region.w,
            h: region.h,
            rotation: Number(region.rotation) || 0,
            flipped: !!region.flipped,
            label: region.label || `vung-${boxSeq}`,
            selected: false,
          };
        });
        drawScene();
        renderBoxList();
        renderResultsList((sourceBoot.regions || []).filter((r) => r.url).map((r) => ({ id: r.id, label: r.label, url: r.url })));
      };
      image.onerror = () => toast('Không tải được ảnh gốc.', 'error');
      image.src = sourceBoot.image_url;
    }
  }

  window.ImageCropper = { init };
})();
