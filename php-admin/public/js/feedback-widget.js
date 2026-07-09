/**
 * Admin feedback widget — floating góp ý.
 */
(function () {
  const root = document.getElementById('feedbackWidget');
  if (!root) return;

  const submitUrl = root.dataset.submitUrl;
  const posKey = `htd_feedback_widget_pos_${root.dataset.userId || '0'}`;
  const DRAG_THRESHOLD = 8;

  const fab = document.getElementById('feedbackWidgetFab');
  const panel = document.getElementById('feedbackWidgetPanel');
  const closeBtn = document.getElementById('feedbackWidgetClose');
  const form = document.getElementById('feedbackWidgetForm');
  const imagesInput = document.getElementById('feedbackImages');
  const previewHost = document.getElementById('feedbackImagePreview');
  const uploadZone = document.querySelector('.feedback-upload-zone');

  const submitBtn = document.getElementById('feedbackWidgetSubmit');

  let selectedFiles = [];
  let dragState = null;
  let suppressClick = false;
  /** Vị trí icon khi panel đóng — khôi phục sau khi đóng form. */
  let fabAnchor = null;

  function clampPosition(x, y, width, height) {
    const pad = 8;
    const w = width ?? root.offsetWidth;
    const h = height ?? root.offsetHeight;
    return {
      x: Math.min(Math.max(pad, x), window.innerWidth - w - pad),
      y: Math.min(Math.max(pad, y), window.innerHeight - h - pad),
    };
  }

  function setWidgetPosition(x, y, width, height) {
    const pos = clampPosition(x, y, width, height);
    root.style.left = `${pos.x}px`;
    root.style.top = `${pos.y}px`;
    root.style.right = 'auto';
    root.style.bottom = 'auto';
    root.classList.add('is-positioned');
    return pos;
  }

  /** Căn tạm khi panel mở — không ghi đè vị trí icon đã lưu. */
  function ensureWidgetInViewport() {
    const rect = root.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return null;
    return setWidgetPosition(rect.left, rect.top, rect.width, rect.height);
  }

  function persistFabAnchor(pos) {
    fabAnchor = { x: pos.x, y: pos.y };
    savePosition(pos.x, pos.y);
  }

  function captureFabAnchor() {
    if (!root.classList.contains('is-positioned')) {
      fabAnchor = null;
      return;
    }
    const rect = root.getBoundingClientRect();
    fabAnchor = { x: rect.left, y: rect.top };
  }

  function savePosition(x, y) {
    try {
      localStorage.setItem(posKey, JSON.stringify({ x, y }));
    } catch (_) { /* ignore */ }
  }

  function loadPosition() {
    try {
      const raw = localStorage.getItem(posKey);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (typeof parsed.x !== 'number' || typeof parsed.y !== 'number') return null;
      return clampPosition(parsed.x, parsed.y);
    } catch (_) {
      return null;
    }
  }

  function anchorWidgetForDrag() {
    const rect = root.getBoundingClientRect();
    setWidgetPosition(rect.left, rect.top);
    return { x: rect.left, y: rect.top };
  }

  function onFabPointerDown(e) {
    if (e.button !== 0) return;
    const anchor = anchorWidgetForDrag();
    dragState = {
      pointerId: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      origX: anchor.x,
      origY: anchor.y,
      moved: false,
    };
    fab.setPointerCapture(e.pointerId);
    e.preventDefault();
  }

  function onFabPointerMove(e) {
    if (!dragState || e.pointerId !== dragState.pointerId) return;
    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;
    if (!dragState.moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) return;
    dragState.moved = true;
    root.classList.add('is-dragging');
    setWidgetPosition(dragState.origX + dx, dragState.origY + dy);
  }

  function onFabPointerUp(e) {
    if (!dragState || e.pointerId !== dragState.pointerId) return;
    if (fab.hasPointerCapture(e.pointerId)) {
      fab.releasePointerCapture(e.pointerId);
    }
    if (dragState.moved) {
      suppressClick = true;
      const x = parseFloat(root.style.left) || 0;
      const y = parseFloat(root.style.top) || 0;
      const pos = setWidgetPosition(x, y);
      persistFabAnchor(pos);
    }
    root.classList.remove('is-dragging');
    dragState = null;
  }

  function initWidgetPosition() {
    const saved = loadPosition();
    if (saved) {
      const pos = setWidgetPosition(saved.x, saved.y);
      fabAnchor = pos;
    }
  }

  function addFiles(incoming) {
    if (incoming.length === 0) return;
    const before = selectedFiles.length;
    selectedFiles = [...selectedFiles, ...incoming].slice(0, 3);
    renderPreviews();
    if (before >= 3 && selectedFiles.length >= 3) {
      window.AdminToast?.show('Đã đủ 3 ảnh đính kèm.', 'warning');
    }
  }

  function filesFromClipboard(dataTransfer) {
    if (!dataTransfer) return [];
    const fromItems = Array.from(dataTransfer.items || [])
      .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
      .map((item) => item.getAsFile())
      .filter(Boolean);
    if (fromItems.length > 0) return fromItems;
    return Array.from(dataTransfer.files || []).filter((f) => f.type.startsWith('image/'));
  }

  function normalizePastedFile(file, index) {
    if (file.name && file.name !== 'image.png' && file.name !== 'blob') return file;
    const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
    return new File([file], `screenshot-${Date.now()}-${index}.${ext}`, { type: file.type });
  }

  function handlePaste(e) {
    if (panel.hidden) return;
    const imageFiles = filesFromClipboard(e.clipboardData);
    if (imageFiles.length === 0) return;
    e.preventDefault();
    addFiles(imageFiles.map(normalizePastedFile));
    uploadZone?.classList.add('is-pasted');
    setTimeout(() => uploadZone?.classList.remove('is-pasted'), 600);
  }

  root.hidden = false;
  initWidgetPosition();

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function openPanel() {
    captureFabAnchor();
    panel.hidden = false;
    root.classList.add('is-panel-open');
    fab.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      if (root.classList.contains('is-positioned')) {
        ensureWidgetInViewport();
      }
      document.getElementById('feedbackBody')?.focus();
    });
  }

  function closePanel() {
    panel.hidden = true;
    root.classList.remove('is-panel-open');
    fab.setAttribute('aria-expanded', 'false');
    requestAnimationFrame(() => {
      if (fabAnchor) {
        setWidgetPosition(fabAnchor.x, fabAnchor.y);
      }
    });
  }

  function renderPreviews() {
    if (!previewHost) return;
    previewHost.innerHTML = '';
    selectedFiles.forEach((file, index) => {
      const wrap = document.createElement('div');
      wrap.className = 'feedback-widget-preview';
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.setAttribute('aria-label', 'Xóa ảnh');
      remove.textContent = '×';
      remove.addEventListener('click', () => {
        selectedFiles.splice(index, 1);
        renderPreviews();
      });
      wrap.appendChild(img);
      wrap.appendChild(remove);
      previewHost.appendChild(wrap);
    });
  }

  fab?.addEventListener('pointerdown', onFabPointerDown);
  fab?.addEventListener('pointermove', onFabPointerMove);
  fab?.addEventListener('pointerup', onFabPointerUp);
  fab?.addEventListener('pointercancel', onFabPointerUp);

  fab?.addEventListener('click', () => {
    if (suppressClick) {
      suppressClick = false;
      return;
    }
    if (panel.hidden) openPanel();
    else closePanel();
  });

  window.addEventListener('resize', () => {
    if (!root.classList.contains('is-positioned')) return;
    if (panel.hidden) {
      const pos = ensureWidgetInViewport() || fabAnchor;
      if (pos) persistFabAnchor(pos);
    } else {
      ensureWidgetInViewport();
    }
  });

  closeBtn?.addEventListener('click', closePanel);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && panel && !panel.hidden) closePanel();
  });

  imagesInput?.addEventListener('change', () => {
    addFiles(Array.from(imagesInput.files || []));
    imagesInput.value = '';
  });

  if (uploadZone) {
    ['dragenter', 'dragover'].forEach((evt) => {
      uploadZone.addEventListener(evt, (e) => {
        e.preventDefault();
        uploadZone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach((evt) => {
      uploadZone.addEventListener(evt, (e) => {
        e.preventDefault();
        uploadZone.classList.remove('is-dragover');
      });
    });
    uploadZone.addEventListener('drop', (e) => {
      const files = filesFromClipboard(e.dataTransfer);
      addFiles(files);
    });
  }

  panel?.addEventListener('paste', handlePaste);

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!submitUrl) return;

    const body = document.getElementById('feedbackBody')?.value?.trim() || '';
    const priority = form.querySelector('input[name="priority"]:checked')?.value || 'medium';

    if (body.length < 3) {
      window.AdminToast?.show('Vui lòng nhập nội dung góp ý.', 'error');
      return;
    }

    const fd = new FormData();
    fd.append('body', body);
    fd.append('priority', priority);
    fd.append('page_url', window.location.pathname + window.location.search);
    fd.append('page_title', document.title || '');
    selectedFiles.forEach((file) => fd.append('images[]', file));

    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang gửi…';

    try {
      const res = await fetch(submitUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
          Accept: 'application/json',
        },
        body: fd,
        credentials: 'same-origin',
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        let msg = json.error || json.message;
        if (!msg && json.errors) {
          const first = Object.values(json.errors).flat()[0];
          if (first) msg = first;
        }
        window.AdminToast?.show(msg || 'Không gửi được góp ý. Vui lòng thử lại.', 'error');
        return;
      }

      window.AdminToast?.show(
        'Cảm ơn bạn đã góp ý! Ý kiến của bạn giúp chúng tôi không ngừng cải thiện ứng dụng.',
        'success'
      );

      form.reset();
      selectedFiles = [];
      renderPreviews();
      closePanel();
    } catch (_) {
      window.AdminToast?.show('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Gửi góp ý';
    }
  });
})();
