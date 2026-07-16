/**
 * Image Cropper — tải 1 ảnh lớn, khoanh nhiều vùng chữ nhật bằng canvas,
 * kéo di chuyển / chọn nhiều vùng để chỉnh cùng lúc, rồi cắt ở độ phân giải
 * gốc và upload PNG base64 lên api/image-crops.
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

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function init(options) {
    const saveUrl = options.saveUrl;

    const fileInput = document.getElementById('icFileInput');
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
    const resultFolderEl = document.getElementById('icResultFolder');
    const resultListEl = document.getElementById('icResultList');

    let img = null;
    let boxes = [];
    let boxSeq = 0;

    // mode: null | 'draw' (khoanh vùng mới) | 'move' (kéo vùng có sẵn) | 'resize' (kéo góc)
    let mode = null;
    let dragStart = null;
    let dragCurrent = null;
    let moveOrigins = null;
    let movedDuringDrag = false;
    let pendingClickBox = null;
    let pendingClickShift = false;
    let dragSnapshot = null;
    let resizeBox = null;
    let resizeCorner = null;
    let resizeAnchor = null;
    let resizeChanged = false;

    // Lịch sử cho Ctrl+Z / Ctrl+Y — mỗi phần tử là 1 bản chụp {boxes, boxSeq}.
    let history = [];
    let future = [];

    function toast(message, type) {
      if (window.AdminToast) window.AdminToast.show(message, type || 'success');
      else if (type === 'error') alert(message);
    }

    function pointFromEvent(evt) {
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / rect.width;
      const scaleY = canvas.height / rect.height;
      const x = (evt.clientX - rect.left) * scaleX;
      const y = (evt.clientY - rect.top) * scaleY;
      return {
        x: clamp(x, 0, canvas.width),
        y: clamp(y, 0, canvas.height),
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
      return clamp(canvas.width / HANDLE_RATIO, HANDLE_MIN, HANDLE_MAX);
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
      };
    }

    function restoreState(state) {
      boxes = state.boxes.map((b) => ({ ...b }));
      boxSeq = state.boxSeq;
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
    }

    function redo() {
      if (future.length === 0) return;
      history.push(snapshotState());
      restoreState(future.pop());
      drawScene();
      renderBoxList();
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
          x: clamp(box.x + DUPLICATE_OFFSET, 0, canvas.width - box.w),
          y: clamp(box.y + DUPLICATE_OFFSET, 0, canvas.height - box.h),
          label: box.label ? `${box.label}-copy` : `vung-${boxSeq}`,
          selected: true,
        };
      });

      clearSelection();
      boxes.push(...duplicates);
      drawScene();
      renderBoxList();
    }

    function drawScene() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);

      boxes.forEach((box, index) => {
        const color = box.selected ? '#f97316' : '#2D46D6';
        const fill = box.selected ? 'rgba(249, 115, 22, 0.18)' : 'rgba(45, 70, 214, 0.12)';

        ctx.strokeStyle = color;
        ctx.lineWidth = Math.max(2, canvas.width / 400) * (box.selected ? 1.4 : 1);
        ctx.strokeRect(box.x, box.y, box.w, box.h);
        ctx.fillStyle = fill;
        ctx.fillRect(box.x, box.y, box.w, box.h);

        const badge = String(index + 1);
        const fontSize = Math.max(14, Math.round(canvas.width / 60));
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
        ctx.lineWidth = Math.max(2, canvas.width / 400);
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

    function cropToDataUrl(box) {
      const off = document.createElement('canvas');
      off.width = box.w;
      off.height = box.h;
      const offCtx = off.getContext('2d');
      offCtx.drawImage(img, box.x, box.y, box.w, box.h, 0, 0, box.w, box.h);
      return off.toDataURL('image/png');
    }

    function applyDelta(box, dx, dy, dw, dh) {
      commitHistory(snapshotState());
      targetsFor(box).forEach((b) => {
        if (dx || dy) {
          b.x = clamp(b.x + dx, 0, canvas.width - b.w);
          b.y = clamp(b.y + dy, 0, canvas.height - b.h);
        }
        if (dw) {
          b.w = clamp(b.w + dw, MIN_BOX_SIZE, canvas.width - b.x);
        }
        if (dh) {
          b.h = clamp(b.h + dh, MIN_BOX_SIZE, canvas.height - b.y);
        }
      });
      drawScene();
      renderBoxList();
    }

    function renderBoxList() {
      boxCountEl.textContent = String(boxes.length);
      emptyHintEl.hidden = boxes.length > 0;
      saveBtn.disabled = boxes.length === 0;
      duplicateBtn.disabled = selectedBoxes().length === 0;

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

        const thumb = document.createElement('img');
        thumb.src = cropToDataUrl(box);
        thumb.alt = `Vùng ${index + 1}`;
        li.appendChild(thumb);

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

        const wLabel = document.createElement('label');
        wLabel.textContent = 'W';
        const wInput = document.createElement('input');
        wInput.type = 'number';
        wInput.min = String(MIN_BOX_SIZE);
        wInput.value = String(box.w);
        wInput.addEventListener('change', () => {
          const next = parseInt(wInput.value, 10);
          const delta = (Number.isFinite(next) ? next : box.w) - box.w;
          applyDelta(box, 0, 0, delta, 0);
        });
        wLabel.appendChild(wInput);
        dims.appendChild(wLabel);

        const hLabel = document.createElement('label');
        hLabel.textContent = 'H';
        const hInput = document.createElement('input');
        hInput.type = 'number';
        hInput.min = String(MIN_BOX_SIZE);
        hInput.value = String(box.h);
        hInput.addEventListener('change', () => {
          const next = parseInt(hInput.value, 10);
          const delta = (Number.isFinite(next) ? next : box.h) - box.h;
          applyDelta(box, 0, 0, 0, delta);
        });
        hLabel.appendChild(hInput);
        dims.appendChild(hLabel);

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

        const size = document.createElement('span');
        size.className = 'ic-box-size';
        size.textContent = `${box.w} × ${box.h} px`;
        fields.appendChild(size);

        li.appendChild(fields);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-secondary btn-sm ic-box-remove';
        removeBtn.textContent = 'Xóa';
        removeBtn.addEventListener('click', () => {
          commitHistory(snapshotState());
          boxes = boxes.filter((b) => b.id !== box.id);
          drawScene();
          renderBoxList();
        });
        li.appendChild(removeBtn);

        boxListEl.appendChild(li);
      });
    }

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
      resultsCard.hidden = true;
      resultListEl.innerHTML = '';
      renderBoxList();
    }

    function loadImageFile(file) {
      const reader = new FileReader();
      reader.onload = () => {
        const image = new Image();
        image.onload = () => {
          img = image;
          canvas.width = image.naturalWidth;
          canvas.height = image.naturalHeight;
          workspace.hidden = false;
          resetWorkspace();
          drawScene();
        };
        image.onerror = () => toast('Không đọc được ảnh này.', 'error');
        image.src = reader.result;
      };
      reader.onerror = () => toast('Không đọc được file ảnh.', 'error');
      reader.readAsDataURL(file);
    }

    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (file) loadImageFile(file);
    });

    changeImageBtn.addEventListener('click', () => {
      fileInput.value = '';
      fileInput.click();
    });

    canvas.addEventListener('mousedown', (evt) => {
      if (!img) return;
      const point = pointFromEvent(evt);

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
        pendingClickShift = evt.shiftKey;

        if (!hit.selected && !evt.shiftKey) clearSelection();
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

    canvas.addEventListener('mousemove', (evt) => {
      const point = pointFromEvent(evt);

      if (!mode) {
        updateHoverCursor(point);
        return;
      }

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
            b.x = clamp(origin.x + dx, 0, canvas.width - b.w);
            b.y = clamp(origin.y + dy, 0, canvas.height - b.h);
          });
          drawScene();
        }
        return;
      }

      if (mode === 'resize' && resizeBox && resizeAnchor) {
        const rect = normalizeRect(resizeAnchor, point);
        const x = clamp(rect.x, 0, canvas.width);
        const y = clamp(rect.y, 0, canvas.height);
        resizeBox.x = x;
        resizeBox.y = y;
        resizeBox.w = clamp(rect.w, MIN_BOX_SIZE, canvas.width - x);
        resizeBox.h = clamp(rect.h, MIN_BOX_SIZE, canvas.height - y);
        resizeChanged = true;
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
            x: rect.x,
            y: rect.y,
            w: rect.w,
            h: rect.h,
            label: `vung-${boxSeq}`,
            selected: false,
          });
          if (dragSnapshot) commitHistory(dragSnapshot);
        }
      } else if (mode === 'move' && pendingClickBox) {
        if (!movedDuringDrag) {
          if (pendingClickShift) {
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
      pendingClickShift = false;
      dragSnapshot = null;
      resizeBox = null;
      resizeCorner = null;
      resizeAnchor = null;
      resizeChanged = false;

      drawScene();
      renderBoxList();
    }

    canvas.addEventListener('mouseup', () => {
      if (mode) finishPointer();
    });
    canvas.addEventListener('mouseleave', () => {
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

      if (!isTyping && evt.key === 'Delete') {
        const targets = selectedBoxes();
        if (targets.length === 0) return;
        evt.preventDefault();
        commitHistory(snapshotState());
        const ids = new Set(targets.map((b) => b.id));
        boxes = boxes.filter((b) => !ids.has(b.id));
        drawScene();
        renderBoxList();
      }
    });

    saveBtn.addEventListener('click', async () => {
      if (boxes.length === 0) return;
      saveBtn.disabled = true;
      saveBtn.textContent = 'Đang lưu...';

      try {
        const images = boxes.map((box) => ({
          data: cropToDataUrl(box),
          label: box.label,
        }));

        const res = await fetch(saveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ images }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
          throw new Error(data.message || data.error || 'Không lưu được ảnh.');
        }

        const saved = data.data?.images || [];
        resultFolderEl.textContent = data.data?.folder || '';
        resultListEl.innerHTML = '';
        saved.forEach((item) => {
          const li = document.createElement('li');
          li.className = 'ic-result-item';

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
        resultsCard.hidden = saved.length === 0;

        toast(`Đã lưu ${saved.length} ảnh.`, 'success');
      } catch (err) {
        toast(err.message || 'Có lỗi khi lưu ảnh.', 'error');
      } finally {
        saveBtn.disabled = boxes.length === 0;
        saveBtn.textContent = 'Cắt & Lưu tất cả';
      }
    });
  }

  window.ImageCropper = { init };
})();
