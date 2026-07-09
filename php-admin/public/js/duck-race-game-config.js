/**
 * Admin — cấu hình đua vịt: thước vạch, canvas resize vịt, preview di chuyển.
 */
const DuckRaceGameConfig = (function () {
  const MIN_GAP = 2;
  const DUCK_SIZE_MIN = 32;
  const DUCK_SIZE_MAX = 128;
  const DUCK_SIZE_DEFAULT = 64;
  const HANDLE_RADIUS = 7;

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function round1(n) {
    return Math.round(n * 10) / 10;
  }

  function readNumber(id, fallback) {
    const el = document.getElementById(id);
    const n = Number(el?.value);
    return Number.isFinite(n) ? n : fallback;
  }

  function getDuckSpritePx() {
    return clamp(readNumber('duck_sprite_px', DUCK_SIZE_DEFAULT), DUCK_SIZE_MIN, DUCK_SIZE_MAX);
  }

  function setDuckSpritePx(px) {
    const size = clamp(Math.round(px), DUCK_SIZE_MIN, DUCK_SIZE_MAX);
    const input = document.getElementById('duck_sprite_px');
    const range = document.getElementById('duck_sprite_px_range');
    const label = document.getElementById('duck_sprite_px_label');
    if (input) input.value = String(size);
    if (range) range.value = String(size);
    if (label) label.textContent = `${size} px`;
    document.dispatchEvent(new CustomEvent('duckrace:duck-size-changed', { detail: { size } }));
    return size;
  }

  function applyDuckSizeToEl(el) {
    if (!el) return;
    const px = getDuckSpritePx();
    el.style.setProperty('--duck-sprite-px', `${px}px`);
  }

  function getBounds() {
    return {
      start: readNumber('track_start_pct', 20),
      end: readNumber('track_end_pct', 90),
    };
  }

  function getLaneBounds() {
    return {
      top: readNumber('lane_top_pct', 50),
      bottom: readNumber('lane_bottom_pct', 92),
    };
  }

  function setLaneBounds(top, bottom) {
    const topEl = document.getElementById('lane_top_pct');
    const bottomEl = document.getElementById('lane_bottom_pct');
    if (topEl) topEl.value = String(round1(top));
    if (bottomEl) bottomEl.value = String(round1(bottom));
  }

  function renderRaceFrame(surfaceEl) {
    if (!surfaceEl) return;
    const { start, end } = getBounds();
    const { top, bottom } = getLaneBounds();
    surfaceEl.style.setProperty('--frame-left', `${start}%`);
    surfaceEl.style.setProperty('--frame-right', `${end}%`);
    surfaceEl.style.setProperty('--frame-top', `${top}%`);
    surfaceEl.style.setProperty('--frame-bottom', `${bottom}%`);
  }

  function emitFrameChanged() {
    document.dispatchEvent(new CustomEvent('duckrace:frame-changed'));
    document.dispatchEvent(new CustomEvent('duckrace:bounds-changed'));
    document.dispatchEvent(new CustomEvent('duckrace:lane-bounds-changed'));
  }

  function setBounds(start, end) {
    const startEl = document.getElementById('track_start_pct');
    const endEl = document.getElementById('track_end_pct');
    if (startEl) startEl.value = String(round1(start));
    if (endEl) endEl.value = String(round1(end));
  }

  function pctFromPointer(surface, clientX) {
    const rect = surface.getBoundingClientRect();
    if (!rect.width) return 0;
    return round1(clamp(((clientX - rect.left) / rect.width) * 100, 0, 100));
  }

  function scoreToLeftPct(score, cfg) {
    const forward = Math.max(0, score);
    const progress = Math.min(1, forward / cfg.targetScore);
    return cfg.start + progress * (cfg.end - cfg.start);
  }

  function readPreviewConfig() {
    const bounds = getBounds();
    return {
      correctDelta: readNumber('correct_delta', 3),
      wrongDelta: readNumber('wrong_delta', -5),
      targetScore: readNumber('target_score', 30),
      start: bounds.start,
      end: bounds.end,
      duckSize: getDuckSpritePx(),
    };
  }

  function pctFromPointerY(surface, clientY) {
    const rect = surface.getBoundingClientRect();
    if (!rect.height) return 0;
    return round1(clamp(((clientY - rect.top) / rect.height) * 100, 0, 100));
  }

  function initFrameEditor(editorEl) {
    const surface = editorEl.querySelector('.drc-track-surface');
    if (!surface) return;

    const edges = [...editorEl.querySelectorAll('[data-edge]')];
    let dragging = null;
    const MIN_H_GAP = MIN_GAP;
    const MIN_V_GAP = 5;

    function renderFrame() {
      renderRaceFrame(surface);
      emitFrameChanged();
    }

    function normalizeBounds() {
      let { start, end } = getBounds();
      let { top, bottom } = getLaneBounds();
      start = clamp(start, 0, 100 - MIN_H_GAP);
      end = clamp(end, MIN_H_GAP, 100);
      top = clamp(top, 0, 100 - MIN_V_GAP);
      bottom = clamp(bottom, MIN_V_GAP, 100);
      if (end - start < MIN_H_GAP) {
        if (dragging === 'start') start = end - MIN_H_GAP;
        else end = start + MIN_H_GAP;
      }
      if (bottom - top < MIN_V_GAP) {
        if (dragging === 'lane-top') top = bottom - MIN_V_GAP;
        else bottom = top + MIN_V_GAP;
      }
      setBounds(start, end);
      setLaneBounds(top, bottom);
      renderFrame();
    }

    function startDrag(which, e) {
      dragging = which;
      edges.find((el) => el.dataset.edge === which)?.classList.add('is-dragging');
      e.preventDefault();
    }

    function onMove(e) {
      if (!dragging) return;
      if (dragging === 'start' || dragging === 'end') {
        const pct = pctFromPointer(surface, e.clientX);
        let { start, end } = getBounds();
        if (dragging === 'start') {
          setBounds(clamp(pct, 0, end - MIN_H_GAP), end);
        } else {
          setBounds(start, clamp(pct, start + MIN_H_GAP, 100));
        }
      } else {
        const pct = pctFromPointerY(surface, e.clientY);
        let { top, bottom } = getLaneBounds();
        if (dragging === 'lane-top') {
          setLaneBounds(clamp(pct, 0, bottom - MIN_V_GAP), bottom);
        } else {
          setLaneBounds(top, clamp(pct, top + MIN_V_GAP, 100));
        }
      }
      renderFrame();
    }

    function stopDrag() {
      if (!dragging) return;
      edges.forEach((el) => el.classList.remove('is-dragging'));
      dragging = null;
    }

    edges.forEach((el) => {
      el.addEventListener('mousedown', (e) => startDrag(el.dataset.edge, e));
    });
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', stopDrag);

    ['track_start_pct', 'track_end_pct', 'lane_top_pct', 'lane_bottom_pct'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', normalizeBounds);
      document.getElementById(id)?.addEventListener('input', normalizeBounds);
    });

    renderFrame();
  }

  function scoreToLeftPctInFrame(score, cfg) {
    const forward = Math.max(0, score);
    const progress = Math.min(1, forward / cfg.targetScore);
    return progress * 100;
  }

  /** Canvas studio — kéo handle góc để resize vịt (không cần thư viện ngoài). */
  function initDuckSizeStudio(studioEl, duckSrc) {
    const canvas = studioEl.querySelector('.drc-duck-size__canvas');
    if (!canvas || !duckSrc) return;

    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.crossOrigin = 'anonymous';

    const groundY = canvas.height - 28;
    const centerX = canvas.width / 2;
    let size = getDuckSpritePx();
    let dragging = false;
    let layout = { x: 0, y: 0, w: 0, h: 0, handleX: 0, handleY: 0 };

    function computeLayout(px) {
      const w = px;
      const h = px;
      const x = centerX - w / 2;
      const y = groundY - h;
      return { x, y, w, h, handleX: x + w, handleY: y + h };
    }

    function drawGrid() {
      ctx.fillStyle = '#e0f2fe';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.strokeStyle = 'rgba(45, 70, 214, 0.08)';
      ctx.lineWidth = 1;
      for (let x = 0; x < canvas.width; x += 16) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, canvas.height);
        ctx.stroke();
      }
      for (let y = 0; y < canvas.height; y += 16) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(canvas.width, y);
        ctx.stroke();
      }
      ctx.strokeStyle = '#7ec8e8';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(0, groundY);
      ctx.lineTo(canvas.width, groundY);
      ctx.stroke();
    }

    function draw() {
      layout = computeLayout(size);
      drawGrid();
      if (!img.complete || !img.naturalWidth) return;

      ctx.drawImage(img, layout.x, layout.y, layout.w, layout.h);

      ctx.strokeStyle = '#2d46d6';
      ctx.lineWidth = 2;
      ctx.setLineDash([5, 4]);
      ctx.strokeRect(layout.x, layout.y, layout.w, layout.h);
      ctx.setLineDash([]);

      ctx.fillStyle = '#2d46d6';
      ctx.beginPath();
      ctx.arc(layout.handleX, layout.handleY, HANDLE_RADIUS, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();

      ctx.fillStyle = '#475569';
      ctx.font = '11px system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(`${size} × ${size} px`, centerX, 16);
    }

    function canvasPoint(e) {
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / rect.width;
      const scaleY = canvas.height / rect.height;
      return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
      };
    }

    function hitHandle(pt) {
      const dx = pt.x - layout.handleX;
      const dy = pt.y - layout.handleY;
      return Math.sqrt(dx * dx + dy * dy) <= HANDLE_RADIUS + 4;
    }

    function sizeFromHandle(pt) {
      const newSize = Math.max(pt.x - layout.x, groundY - pt.y);
      return setDuckSpritePx(newSize);
    }

    canvas.addEventListener('mousedown', (e) => {
      const pt = canvasPoint(e);
      if (!hitHandle(pt)) return;
      dragging = true;
      canvas.classList.add('is-resizing');
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      size = sizeFromHandle(canvasPoint(e));
      draw();
    });

    document.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging = false;
      canvas.classList.remove('is-resizing');
    });

    function onExternalSizeChange(e) {
      size = e.detail?.size ?? getDuckSpritePx();
      draw();
    }

    document.addEventListener('duckrace:duck-size-changed', onExternalSizeChange);

    document.getElementById('duck_sprite_px')?.addEventListener('input', () => {
      size = setDuckSpritePx(readNumber('duck_sprite_px', DUCK_SIZE_DEFAULT));
      draw();
    });
    document.getElementById('duck_sprite_px_range')?.addEventListener('input', (e) => {
      size = setDuckSpritePx(Number(e.target.value));
      draw();
    });

    img.onload = () => draw();
    img.src = duckSrc;
    size = getDuckSpritePx();
    draw();
  }

  function initPreviewResizeHandle(duckEl) {
    const handle = duckEl?.querySelector('.drc-preview-duck__resize');
    if (!handle || !duckEl) return;

    let dragging = false;
    let startX = 0;
    let startSize = 0;

    handle.addEventListener('mousedown', (e) => {
      dragging = true;
      startX = e.clientX;
      startSize = getDuckSpritePx();
      handle.classList.add('is-dragging');
      e.preventDefault();
      e.stopPropagation();
    });

    document.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      const delta = e.clientX - startX;
      setDuckSpritePx(startSize + delta * 0.6);
    });

    document.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging = false;
      handle.classList.remove('is-dragging');
    });
  }

  function getDuckSwimMs() {
    return clamp(readNumber('duck_swim_ms', 1150), 400, 3000);
  }

  function applySwimTransition(el) {
    if (!el) return;
    const ms = getDuckSwimMs();
    el.style.transition = `left ${ms}ms ease-in-out`;
  }

  function setPreviewDuckPosition(duck, leftPct) {
    if (!duck) return;
    applySwimTransition(duck);
    const next = `${leftPct}%`;
    const prev = duck.dataset.duckLeft;
    duck.style.left = next;
    if (prev != null && prev !== next) {
      const ms = getDuckSwimMs();
      duck.classList.add('is-swimming');
      clearTimeout(duck._swimTimer);
      duck._swimTimer = setTimeout(() => duck.classList.remove('is-swimming'), ms + 80);
    }
    duck.dataset.duckLeft = next;
  }

  function initPreview(previewEl) {
    const duck = previewEl.querySelector('.drc-preview-duck');
    const finishOverlay = previewEl.querySelector('.drc-preview-finish');
    const scoreEl = previewEl.querySelector('.drc-preview__score');
    const surface = previewEl.querySelector('.drc-preview-surface');
    let score = 0;
    let finished = false;

    initPreviewResizeHandle(duck);

    function render() {
      const cfg = readPreviewConfig();
      renderRaceFrame(surface);

      const left = finished ? 100 : scoreToLeftPctInFrame(score, cfg);
      setPreviewDuckPosition(duck, left);
      applyDuckSizeToEl(duck);

      if (scoreEl) {
        scoreEl.textContent = `Điểm: ${score} / ${cfg.targetScore}`;
        scoreEl.classList.toggle('is-negative', score < 0);
        scoreEl.classList.toggle('is-finished', finished);
      }

      duck?.classList.toggle('is-finished', finished);
      finishOverlay?.classList.toggle('visible', finished);
    }

    function applyDelta(delta) {
      if (finished) return;
      score += delta;
      const cfg = readPreviewConfig();
      if (score >= cfg.targetScore) {
        score = cfg.targetScore;
        finished = true;
      }
      render();
    }

    previewEl.querySelector('[data-preview="correct"]')?.addEventListener('click', () => {
      applyDelta(readPreviewConfig().correctDelta);
    });
    previewEl.querySelector('[data-preview="wrong"]')?.addEventListener('click', () => {
      applyDelta(readPreviewConfig().wrongDelta);
    });
    previewEl.querySelector('[data-preview="reset"]')?.addEventListener('click', () => {
      score = 0;
      finished = false;
      render();
    });

    ['correct_delta', 'wrong_delta', 'target_score', 'track_start_pct', 'track_end_pct', 'lane_top_pct', 'lane_bottom_pct', 'duck_sprite_px', 'duck_swim_ms'].forEach((id) => {
      document.getElementById(id)?.addEventListener('input', render);
      document.getElementById(id)?.addEventListener('change', render);
    });
    document.getElementById('duck_sprite_px_range')?.addEventListener('input', render);
    document.getElementById('duck_swim_ms_range')?.addEventListener('input', () => {
      const range = document.getElementById('duck_swim_ms_range');
      const input = document.getElementById('duck_swim_ms');
      const label = document.getElementById('duck_swim_ms_label');
      if (range && input) input.value = range.value;
      if (label && range) label.textContent = `${(Number(range.value) / 1000).toFixed(2)} giây / bước`;
      render();
    });
    document.getElementById('duck_swim_ms')?.addEventListener('input', () => {
      const input = document.getElementById('duck_swim_ms');
      const range = document.getElementById('duck_swim_ms_range');
      const label = document.getElementById('duck_swim_ms_label');
      if (input && range) range.value = input.value;
      if (label && input) label.textContent = `${(Number(input.value) / 1000).toFixed(2)} giây / bước`;
      render();
    });
    document.addEventListener('duckrace:frame-changed', render);
    document.addEventListener('duckrace:bounds-changed', render);
    document.addEventListener('duckrace:lane-bounds-changed', render);
    document.addEventListener('duckrace:duck-size-changed', render);

    render();
  }

  function init(root) {
    if (!root) return;
    const frameEditor = root.querySelector('.drc-frame-editor');
    const studio = root.querySelector('.drc-duck-size');
    const preview = root.querySelector('.drc-preview');
    const duckSrc = root.dataset.duckSrc || '';
    if (frameEditor) initFrameEditor(frameEditor);
    if (studio) initDuckSizeStudio(studio, duckSrc);
    if (preview) initPreview(preview);
  }

  return { init, getDuckSpritePx, setDuckSpritePx };
})();
