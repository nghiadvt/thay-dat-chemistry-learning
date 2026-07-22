/**
 * Card Template WYSIWYG Editor
 */
window.CardEditor = (function () {
  const boot = window.ADMIN_BOOT || {};
  const tpl = boot.template || {};
  const SIZE_STEPS = [9, 11, 14, 18, 24];
  const SIZE_LABELS = ['Rất nhỏ', 'Nhỏ', 'Vừa', 'Lớn', 'Rất lớn'];
  const SNAP = 0.02;

  let uid = 0;
  const genId = (p) => `${p}_${++uid}_${Date.now().toString(36)}`;
  const clone = (o) => JSON.parse(JSON.stringify(o));

  const state = {
    id: tpl.id || null,
    name: tpl.name || 'Mẫu thẻ mới',
    sides: tpl.sides || 1,
    frame_width_mm: tpl.frame_width_mm || 85.6,
    frame_height_mm: tpl.frame_height_mm || 53.98,
    layout: clone(tpl.layout || defaultLayout()),
    currentSide: 'front',
    selected: null, // { kind: 'layer'|'element', id }
    undoStack: [],
    redoStack: [],
    pendingUploads: { front: {}, back: {} },
    layerSrcCache: {},
    fonts: tpl.fonts || [],
    bindings: tpl.bindings || [],
    sampleData: tpl.sampleData || {},
    storageBaseUrl: tpl.storageBaseUrl || '/storage',
    canvasZoom: 120,
  };

  function defaultLayout() {
    return {
      front: { imageLayers: [], elements: [], frameCropY: 0 },
      back: { imageLayers: [], elements: [], frameCropY: 0 },
      a4: { marginMm: 8, gapMm: 4, cardWidthMm: 54 },
    };
  }

  function sideLayout(side) {
    if (!state.layout[side]) state.layout[side] = { imageLayers: [], elements: [], frameCropY: 0 };
    if (state.layout[side].frameCropY == null) state.layout[side].frameCropY = 0;
    return state.layout[side];
  }

  function backgroundLayer(side = state.currentSide) {
    const layers = sideLayout(side).imageLayers || [];
    return layers.find((l) => !l.hidden) || layers[0] || null;
  }

  function cardFrameEl() {
    return document.getElementById('cdeCardFrame');
  }

  function pushHistory() {
    state.undoStack.push(clone({
      layout: state.layout,
      frame_width_mm: state.frame_width_mm,
      frame_height_mm: state.frame_height_mm,
      sides: state.sides,
    }));
    if (state.undoStack.length > 80) state.undoStack.shift();
    state.redoStack = [];
    updateHistoryBtns();
  }

  function commit(fn) {
    pushHistory();
    fn();
    renderAll();
  }

  function undo() {
    if (!state.undoStack.length) return;
    state.redoStack.push(clone({
      layout: state.layout,
      frame_width_mm: state.frame_width_mm,
      frame_height_mm: state.frame_height_mm,
      sides: state.sides,
    }));
    const prev = state.undoStack.pop();
    Object.assign(state, prev);
    updateHistoryBtns();
    renderAll();
  }

  function redo() {
    if (!state.redoStack.length) return;
    state.undoStack.push(clone({
      layout: state.layout,
      frame_width_mm: state.frame_width_mm,
      frame_height_mm: state.frame_height_mm,
      sides: state.sides,
    }));
    const next = state.redoStack.pop();
    Object.assign(state, next);
    updateHistoryBtns();
    renderAll();
  }

  function updateHistoryBtns() {
    const u = document.getElementById('cdeUndo');
    const r = document.getElementById('cdeRedo');
    if (u) u.disabled = !state.undoStack.length;
    if (r) r.disabled = !state.redoStack.length;
  }

  function layerSrc(layer) {
    if (state.pendingUploads[state.currentSide][layer.id]) {
      return state.pendingUploads[state.currentSide][layer.id];
    }
    if (state.layerSrcCache[layer.id]) return state.layerSrcCache[layer.id];
    if (layer.path) {
      return `${state.storageBaseUrl}/${layer.path}`.replace(/([^:]\/)\/+/g, '$1');
    }
    return '';
  }

  function resolveText(el) {
    const b = el.binding || 'static';
    if (b === 'static') return el.text || '';
    const [entity, field] = b.split('.');
    return state.sampleData[entity]?.[field] ?? el.text ?? '';
  }

  function fontFamilyCss(key) {
    const f = state.fonts.find((x) => x.key === key);
    return f ? `'${f.family}', sans-serif` : "'Be Vietnam Pro', sans-serif";
  }

  function ptFromLevel(level) {
    return SIZE_STEPS[Math.max(0, Math.min(4, (level || 3) - 1))] || 11;
  }

  function levelFromPt(pt) {
    let best = 2;
    let diff = Infinity;
    SIZE_STEPS.forEach((s, i) => {
      const d = Math.abs(s - pt);
      if (d < diff) { diff = d; best = i + 1; }
    });
    return best;
  }

  function snap(v) {
    return Math.round(v / SNAP) * SNAP;
  }

  function computeA4() {
    const a4 = state.layout.a4 || {};
    const marginMm = Math.max(0, Math.min(30, +(a4.marginMm ?? 8)));
    const gapMm = Math.max(0, Math.min(20, +(a4.gapMm ?? 4)));
    const cardWmm = Math.max(20, Math.min(100, +(a4.cardWidthMm ?? 54)));
    const fw = state.frame_width_mm;
    const fh = state.frame_height_mm;
    const cardHmm = cardWmm * (fh / fw);
    const cols = Math.max(1, Math.floor((210 - 2 * marginMm + gapMm) / (cardWmm + gapMm)));
    const rows = Math.max(1, Math.floor((297 - 2 * marginMm + gapMm) / (cardHmm + gapMm)));
    return { marginMm, gapMm, cardWmm, cardHmm, cols, rows, perSheet: cols * rows, scaleK: cardWmm / fw };
  }

  /* ── Render ── */
  function frameRatio() {
    return state.frame_height_mm / state.frame_width_mm;
  }

  function artboardWidthPx() {
    const wrap = document.getElementById('cdeFrameWrap');
    const stage = document.querySelector('.cde-stage');
    if (!wrap || !stage) return 640;
    const zoom = (state.canvasZoom || 100) / 100;
    const padX = 48;
    const inner = Math.max(280, wrap.clientWidth - padX);
    const stageMax = Math.max(280, stage.clientWidth - 260);
    return Math.round(Math.min(inner, stageMax) * zoom);
  }

  function artboardMetrics(side = state.currentSide) {
    const bg = backgroundLayer(side);
    const w = artboardWidthPx();
    const imgRatio = bg?.naturalRatio || frameRatio();
    const h = Math.max(Math.round(w / imgRatio), Math.round(w * frameRatio()));
    const frameHPx = Math.round(w * frameRatio());
    const maxTop = Math.max(0, h - frameHPx);
    const cropY = Math.max(0, Math.min(1, sideLayout(side).frameCropY ?? 0));
    const topPx = Math.round(cropY * maxTop);
    return { w, h, frameHPx, maxTop, cropY, topPx, hasImage: !!bg };
  }

  function syncFrameHeightUi() {
    const input = document.getElementById('cdeFrameH');
    if (input) input.value = String(Math.round(state.frame_height_mm * 10) / 10);
  }

  function layoutArtboard() {
    const artboard = document.getElementById('cdeArtboard');
    const imgEl = document.getElementById('cdeArtboardImg');
    const empty = document.getElementById('cdeArtboardEmpty');
    const frame = cardFrameEl();
    const zoomLabel = document.getElementById('cdeCanvasZoomLabel');
    if (!artboard || !frame) return;

    const m = artboardMetrics();
    artboard.style.width = `${m.w}px`;
    artboard.style.height = `${m.h}px`;

    const bg = backgroundLayer();
    if (bg && imgEl) {
      const src = layerSrc(bg);
      if (src && imgEl.src !== src) imgEl.src = src;
      imgEl.hidden = false;
      empty?.setAttribute('hidden', '');
      frame.classList.remove('is-empty');
    } else {
      if (imgEl) {
        imgEl.hidden = true;
        imgEl.removeAttribute('src');
      }
      empty?.removeAttribute('hidden');
      frame.classList.add('is-empty');
    }

    frame.style.width = `${m.w}px`;
    frame.style.height = `${m.frameHPx}px`;
    frame.style.top = `${m.topPx}px`;

    if (zoomLabel) zoomLabel.textContent = `${state.canvasZoom || 100}%`;
    syncFrameHeightUi();
  }

  function clampFrameCropY(side = state.currentSide) {
    const m = artboardMetrics(side);
    if (m.maxTop <= 0) {
      sideLayout(side).frameCropY = 0;
      return;
    }
    sideLayout(side).frameCropY = Math.max(0, Math.min(1, sideLayout(side).frameCropY ?? 0));
  }

  function maxFrameHeightMm(side = state.currentSide) {
    const bg = backgroundLayer(side);
    if (!bg?.naturalRatio) return 297;
    return state.frame_width_mm / bg.naturalRatio;
  }

  function setFrameHeightMm(hMm, { history = false } = {}) {
    const cap = maxFrameHeightMm();
    const next = Math.max(20, Math.min(cap, Math.min(297, hMm)));
    if (history) pushHistory();
    state.frame_height_mm = next;
    clampFrameCropY();
    layoutArtboard();
    renderElements();
    if (history) { /* already pushed */ } else { /* live drag */ }
  }

  function renderArtboard() {
    layoutArtboard();
    ensureBackgroundNaturalRatio();
    setupCardFrameInteract();
  }

  function renderElements() {
    const host = document.getElementById('cdeElements');
    if (!host) return;
    host.innerHTML = '';
    (sideLayout(state.currentSide).elements || []).forEach((el) => {
      const div = document.createElement('div');
      div.className = 'cde-el-node' + (isSelected('element', el.id) ? ' is-selected' : '');
      div.dataset.elId = el.id;
      div.style.left = `${el.x * 100}%`;
      div.style.top = `${el.y * 100}%`;
      div.style.width = `${el.w * 100}%`;
      div.style.height = `${el.h * 100}%`;
      div.style.fontFamily = fontFamilyCss(el.fontFamily);
      div.style.fontSize = `${el.fontSizePt || 11}pt`;
      div.style.fontWeight = el.fontWeight || 400;
      div.style.fontStyle = el.italic ? 'italic' : 'normal';
      div.style.textDecoration = el.underline ? 'underline' : 'none';
      div.style.color = el.color || '#111827';
      div.style.textAlign = el.align || 'left';
      div.style.lineHeight = el.lineHeight || 1.2;
      div.style.padding = `${el.paddingPx || 4}px`;
      if (el.bgColor) {
        const op = el.bgOpacity ?? 1;
        div.style.background = hexToRgba(el.bgColor, op);
      }
      const bw = el.borderWidthPt || 0;
      if (bw > 0) div.style.border = `${bw}pt solid ${el.borderColor || '#000'}`;
      div.style.borderRadius = `${el.borderRadiusPx || 0}px`;
      const grip = document.createElement('div');
      grip.className = 'cde-el-grip';
      grip.title = 'Giữ và kéo để di chuyển';
      grip.setAttribute('role', 'button');
      grip.setAttribute('aria-label', 'Kéo để di chuyển');
      const content = document.createElement('div');
      content.className = 'cde-el-content';
      content.textContent = resolveText(el);
      Object.assign(content.style, {
        fontFamily: div.style.fontFamily,
        fontSize: div.style.fontSize,
        fontWeight: div.style.fontWeight,
        fontStyle: div.style.fontStyle,
        textDecoration: div.style.textDecoration,
        color: div.style.color,
        textAlign: div.style.textAlign,
        lineHeight: div.style.lineHeight,
        padding: div.style.padding,
        background: div.style.background,
        border: div.style.border,
        borderRadius: div.style.borderRadius,
      });
      const settings = document.createElement('button');
      settings.type = 'button';
      settings.className = 'cde-el-settings';
      settings.title = 'Tùy chỉnh';
      settings.textContent = '⋯';
      settings.addEventListener('click', (e) => {
        e.stopPropagation();
        select('element', el.id);
        openPopover(div, el);
      });
      ['tl', 'tr', 'bl', 'br'].forEach((corner) => {
        const handle = document.createElement('div');
        handle.className = `cde-el-handle cde-el-handle--${corner}`;
        handle.title = 'Kéo góc để co giãn';
        div.appendChild(handle);
      });
      div.append(grip, content, settings);
      host.appendChild(div);
    });
    setupElementInteract();
  }

  function hexToRgba(hex, a) {
    const h = (hex || '').replace('#', '');
    if (h.length !== 6) return 'transparent';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return `rgba(${r},${g},${b},${a})`;
  }

  function renderLayerList() {
    const list = document.getElementById('cdeLayerList');
    if (!list) return;
    list.innerHTML = '';
    const bg = backgroundLayer();
    if (!bg) return;
    const li = document.createElement('li');
    li.className = 'cde-layer-item is-selected';
    li.dataset.layerId = bg.id;
    const num = document.createElement('span');
    num.className = 'cde-layer-num';
    num.textContent = '1';
    const thumb = document.createElement('img');
    thumb.className = 'cde-layer-thumb';
    thumb.src = layerSrc(bg);
    const actions = document.createElement('div');
    actions.className = 'cde-layer-actions';
    actions.innerHTML = `<button type="button" data-act="del" title="Xóa ảnh">✕</button>`;
    li.append(num, thumb, actions);
    actions.querySelector('[data-act="del"]')?.addEventListener('click', (e) => {
      e.stopPropagation();
      deleteLayer(bg.id);
    });
    list.appendChild(li);
  }

  function renderChips() {
    const host = document.getElementById('cdeChips');
    if (!host) return;
    host.innerHTML = '';
    state.bindings.forEach((b) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cde-chip';
      btn.textContent = b.label;
      btn.addEventListener('click', () => addElement(b.binding));
      host.appendChild(btn);
    });
  }

  function renderAll() {
    layoutArtboard();
    renderArtboard();
    renderElements();
    renderLayerList();
    renderA4Preview();
    closePopover();
  }

  function isSelected(kind, id) {
    return state.selected?.kind === kind && state.selected?.id === id;
  }

  function select(kind, id) {
    state.selected = { kind, id };
    renderElements();
    renderLayerList();
  }

  /* ── Interact.js ── */
  function setupCardFrameInteract() {
    if (!window.interact) return;
    const frame = cardFrameEl();
    if (!frame) return;
    interact('#cdeCardFrame').unset();

    interact('#cdeCardFrame')
      .draggable({
        allowFrom: '.cde-frame-drag-handle',
        listeners: {
          start() {
            frame.dataset.dragStartTop = String(parseFloat(frame.style.top || '0'));
          },
          move(e) {
            const m = artboardMetrics();
            if (!m.hasImage) return;
            const startTop = parseFloat(frame.dataset.dragStartTop || '0');
            const nextTop = Math.max(0, Math.min(m.maxTop, startTop + e.dy));
            frame.style.top = `${nextTop}px`;
            sideLayout(state.currentSide).frameCropY = m.maxTop > 0 ? nextTop / m.maxTop : 0;
          },
          end() { pushHistory(); layoutArtboard(); },
        },
      })
      .resizable({
        edges: { bottomLeft: '.cde-frame-handle--bl', bottomRight: '.cde-frame-handle--br' },
        listeners: {
          move(e) {
            const m = artboardMetrics();
            const curH = parseFloat(frame.style.height || '0');
            const newHPx = Math.max(Math.round(m.w * 0.12), curH + e.deltaRect.height);
            const cap = maxFrameHeightMm();
            state.frame_height_mm = Math.max(20, Math.min(cap, state.frame_width_mm * (newHPx / m.w)));
            clampFrameCropY();
            frame.style.width = `${m.w}px`;
            frame.style.height = `${Math.round(m.w * frameRatio())}px`;
            layoutArtboard();
          },
          end() { pushHistory(); syncFrameHeightUi(); },
        },
      });
  }

  function frameRect() {
    const frame = cardFrameEl();
    return frame ? frame.getBoundingClientRect() : { width: 1, height: 1, left: 0, top: 0 };
  }

  function setupElementInteract() {
    if (!window.interact) return;
    interact('.cde-el-node').unset();
    interact('.cde-el-grip').unset();

    interact('.cde-el-grip')
      .draggable({
        listeners: {
          move(e) {
            const node = e.target.closest('.cde-el-node');
            const el = findElement(node?.dataset.elId);
            if (!el || !node) return;
            const fr = frameRect();
            el.x = snap(Math.max(0, Math.min(1 - el.w, el.x + e.dx / fr.width)));
            el.y = snap(Math.max(0, Math.min(1 - el.h, el.y + e.dy / fr.height)));
            node.style.left = `${el.x * 100}%`;
            node.style.top = `${el.y * 100}%`;
          },
          end() { pushHistory(); },
        },
      });

    interact('.cde-el-node')
      .resizable({
        edges: {
          topLeft: '.cde-el-handle--tl',
          topRight: '.cde-el-handle--tr',
          bottomLeft: '.cde-el-handle--bl',
          bottomRight: '.cde-el-handle--br',
        },
        ignoreFrom: '.cde-el-grip, .cde-el-settings, .cde-el-content',
        listeners: {
          move(e) {
            const target = e.target.closest?.('.cde-el-node') || e.target;
            const el = findElement(target.dataset?.elId);
            if (!el) return;
            const fr = frameRect();
            el.w = snap(Math.max(0.04, el.w + e.deltaRect.width / fr.width));
            el.h = snap(Math.max(0.03, el.h + e.deltaRect.height / fr.height));
            el.x = snap(Math.max(0, Math.min(1 - el.w, el.x + e.deltaRect.left / fr.width)));
            el.y = snap(Math.max(0, Math.min(1 - el.h, el.y + e.deltaRect.top / fr.height)));
            Object.assign(target.style, {
              width: `${el.w * 100}%`,
              height: `${el.h * 100}%`,
              left: `${el.x * 100}%`,
              top: `${el.y * 100}%`,
            });
          },
          end() { pushHistory(); },
        },
      })
      .on('tap', (e) => {
        if (e.target.closest('.cde-el-grip, .cde-el-settings, .cde-el-handle')) return;
        select('element', e.currentTarget.dataset.elId);
      });
  }

  function findLayer(id) {
    return (sideLayout(state.currentSide).imageLayers || []).find((l) => l.id === id);
  }

  function findElement(id) {
    return (sideLayout(state.currentSide).elements || []).find((e) => e.id === id);
  }

  /* ── Actions ── */
  function addImageFiles(files) {
    if (!files.length) return;
    const file = files[0];
    pushHistory();
    const reader = new FileReader();
    reader.onload = () => {
      const id = genId('img');
      const side = state.currentSide;
      state.pendingUploads[side] = { [id]: reader.result };
      const layer = {
        id,
        path: null,
        x: 0,
        y: 0,
        w: 1,
        h: 1,
        rotation: 0,
        opacity: 1,
      };
      const img = new Image();
      img.onload = () => {
        layer.naturalRatio = img.naturalWidth / img.naturalHeight;
        sideLayout(side).imageLayers = [layer];
        sideLayout(side).frameCropY = 0;
        const cap = state.frame_width_mm / layer.naturalRatio;
        if (state.frame_height_mm > cap) state.frame_height_mm = cap;
        checkDpiWarning(file, reader.result, img.naturalWidth);
        renderAll();
      };
      img.onerror = () => {
        sideLayout(side).imageLayers = [layer];
        sideLayout(side).frameCropY = 0;
        renderAll();
      };
      img.src = reader.result;
    };
    reader.readAsDataURL(file);
  }

  function checkDpiWarning(file, dataUrl, naturalW) {
    const minPx = (state.frame_width_mm / 25.4) * 300;
    if (naturalW < minPx * 0.85) {
      const warn = document.getElementById('cdeDpiWarn');
      if (warn) {
        warn.hidden = false;
        warn.textContent = `Ảnh «${file.name}» có thể hơi mờ khi in — nên dùng ảnh nét hơn (tối thiểu ~${Math.round(minPx)}px chiều ngang).`;
      }
    }
  }

  function ensureBackgroundNaturalRatio() {
    const bg = backgroundLayer();
    if (!bg || bg.naturalRatio) return;
    const src = layerSrc(bg);
    if (!src) return;
    const img = new Image();
    img.onload = () => {
      if (!bg.naturalRatio) {
        bg.naturalRatio = img.naturalWidth / img.naturalHeight;
        layoutArtboard();
      }
    };
    img.src = src;
  }

  function addElement(binding) {
    commit(() => {
      sideLayout(state.currentSide).elements.push({
        id: genId('el'),
        binding,
        text: binding === 'static' ? 'Nhập text…' : '',
        x: 0.08, y: 0.42, w: 0.32, h: 0.07,
        fontFamily: 'be-vietnam-pro',
        fontSizePt: 14,
        fontWeight: 700,
        italic: false,
        underline: false,
        color: '#111827',
        align: 'center',
        lineHeight: 1.2,
        paddingPx: 4,
        bgColor: null,
        bgOpacity: 1,
        borderWidthPt: 0,
        borderColor: '#000000',
        borderRadiusPx: 0,
      });
    });
  }

  function deleteLayer(id) {
    commit(() => {
      sideLayout(state.currentSide).imageLayers = [];
      state.pendingUploads[state.currentSide] = {};
      sideLayout(state.currentSide).frameCropY = 0;
      state.selected = null;
    });
  }

  function deleteSelectedElement() {
    if (!state.selected || state.selected.kind !== 'element') return;
    commit(() => {
      const els = sideLayout(state.currentSide).elements;
      const i = els.findIndex((e) => e.id === state.selected.id);
      if (i >= 0) els.splice(i, 1);
      state.selected = null;
    });
  }

  /* ── Popover ── */
  let popoverEl = null;

  function openPopover(anchor, el) {
    popoverEl = el;
    const pop = document.getElementById('cdePopover');
    if (!pop) return;
    const rect = anchor.getBoundingClientRect();
    pop.removeAttribute('hidden');
    pop.style.left = `${Math.min(rect.right + 8, window.innerWidth - 280)}px`;
    pop.style.top = `${Math.max(8, rect.top)}px`;
    fillPopover(el);
  }

  function closePopover() {
    document.getElementById('cdePopover')?.setAttribute('hidden', '');
    popoverEl = null;
  }

  function fillPopover(el) {
    const chips = document.getElementById('cdePopoverChips');
    chips.innerHTML = '';
    state.bindings.forEach((b) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cde-chip';
      btn.textContent = b.label;
      btn.addEventListener('click', () => {
        el.binding = b.binding;
        if (b.binding === 'static' && !el.text) el.text = 'Nhập text…';
        renderElements();
      });
      chips.appendChild(btn);
    });
    document.getElementById('cdePopText').value = el.text || '';
    const fontSel = document.getElementById('cdePopFont');
    fontSel.innerHTML = state.fonts.map((f) =>
      `<option value="${f.key}">${f.label}${f.supportsVietnamese ? '' : ' (Latin)'}</option>`
    ).join('');
    fontSel.value = el.fontFamily || 'be-vietnam-pro';
    const lvl = levelFromPt(el.fontSizePt || 11);
    document.getElementById('cdePopSize').value = lvl;
    document.getElementById('cdePopSizeLabel').textContent = SIZE_LABELS[lvl - 1];
    document.getElementById('cdePopBold').classList.toggle('is-on', el.fontWeight >= 700);
    document.getElementById('cdePopItalic').classList.toggle('is-on', !!el.italic);
    document.getElementById('cdePopUnderline').classList.toggle('is-on', !!el.underline);
    document.getElementById('cdePopColor').value = el.color || '#111827';
    document.getElementById('cdePopAlign').value = el.align || 'left';
    document.getElementById('cdePopBg').value = el.bgColor || '#ffffff';
    document.getElementById('cdePopBgOp').value = Math.round((el.bgOpacity ?? 1) * 100);
    document.getElementById('cdePopBorder').value = el.borderWidthPt || 0;
    document.getElementById('cdePopRadius').value = el.borderRadiusPx || 0;
  }

  function bindPopover() {
    const apply = () => {
      if (!popoverEl) return;
      const el = popoverEl;
      el.text = document.getElementById('cdePopText').value;
      el.fontFamily = document.getElementById('cdePopFont').value;
      el.fontSizePt = ptFromLevel(+document.getElementById('cdePopSize').value);
      el.fontWeight = document.getElementById('cdePopBold').classList.contains('is-on') ? 700 : 400;
      el.italic = document.getElementById('cdePopItalic').classList.contains('is-on');
      el.underline = document.getElementById('cdePopUnderline').classList.contains('is-on');
      el.color = document.getElementById('cdePopColor').value;
      el.align = document.getElementById('cdePopAlign').value;
      const bg = document.getElementById('cdePopBg').value;
      el.bgColor = bg === '#ffffff' ? null : bg;
      el.bgOpacity = +document.getElementById('cdePopBgOp').value / 100;
      el.borderWidthPt = +document.getElementById('cdePopBorder').value;
      el.borderRadiusPx = +document.getElementById('cdePopRadius').value;
      renderElements();
    };
    ['cdePopText', 'cdePopFont', 'cdePopSize', 'cdePopColor', 'cdePopAlign', 'cdePopBg', 'cdePopBgOp', 'cdePopBorder', 'cdePopRadius'].forEach((id) => {
      document.getElementById(id)?.addEventListener('input', apply);
      document.getElementById(id)?.addEventListener('change', apply);
    });
    document.getElementById('cdePopSize')?.addEventListener('input', (e) => {
      document.getElementById('cdePopSizeLabel').textContent = SIZE_LABELS[+e.target.value - 1];
    });
    ['cdePopBold', 'cdePopItalic', 'cdePopUnderline'].forEach((id) => {
      document.getElementById(id)?.addEventListener('click', () => {
        document.getElementById(id).classList.toggle('is-on');
        apply();
      });
    });
    document.getElementById('cdePopDelete')?.addEventListener('click', () => {
      closePopover();
      deleteSelectedElement();
    });
  }

  /* ── A4 preview ── */
  let a4RenderToken = 0;

  function mountCardPreview(container, bgUrl, elements, slotW, refFrameW) {
    const inner = document.createElement('div');
    inner.className = 'cde-a4-card-slot__inner';
    if (bgUrl) inner.style.backgroundImage = `url(${bgUrl})`;
    const scale = slotW / refFrameW;
    elements.forEach((el) => {
      const d = document.createElement('div');
      d.className = 'cde-a4-card-slot__text';
      d.textContent = resolveText(el);
      d.style.left = `${el.x * 100}%`;
      d.style.top = `${el.y * 100}%`;
      d.style.width = `${el.w * 100}%`;
      d.style.height = `${el.h * 100}%`;
      d.style.fontFamily = fontFamilyCss(el.fontFamily);
      d.style.fontSize = `${(el.fontSizePt || 11) * scale}pt`;
      d.style.fontWeight = el.fontWeight || 400;
      d.style.fontStyle = el.italic ? 'italic' : 'normal';
      d.style.textDecoration = el.underline ? 'underline' : 'none';
      d.style.color = el.color || '#111827';
      d.style.textAlign = el.align || 'left';
      d.style.lineHeight = String(el.lineHeight || 1.2);
      d.style.padding = `${(el.paddingPx || 4) * scale}px`;
      if (el.bgColor) d.style.background = hexToRgba(el.bgColor, el.bgOpacity ?? 1);
      const bw = el.borderWidthPt || 0;
      if (bw > 0) d.style.border = `${bw * scale}pt solid ${el.borderColor || '#000'}`;
      d.style.borderRadius = `${(el.borderRadiusPx || 0) * scale}px`;
      inner.appendChild(d);
    });
    container.appendChild(inner);
  }

  function renderA4Preview() {
    const info = document.getElementById('cdeA4Info');
    const a4 = computeA4();
    if (info) info.textContent = `Được ${a4.perSheet} thẻ / trang A4`;
    const label = document.getElementById('cdeCardWidthLabel');
    if (label) label.textContent = `${Math.round(a4.cardWmm)} mm`;
  }

  async function renderA4Grid() {
    const host = document.getElementById('cdeA4Preview');
    if (!host) return;
    const token = ++a4RenderToken;
    host.innerHTML = '<p class="cde-a4-loading">Đang tạo xem trước A4…</p>';

    const a4 = computeA4();
    let bgUrl = '';
    try {
      bgUrl = await bakeSide('front');
    } catch (_) { /* empty preview */ }

    if (token !== a4RenderToken) return;

    const refFrameW = artboardMetrics('front').w || 640;
    const elements = sideLayout('front').elements || [];
    const scale = 2.5;
    const sheetW = 210 * scale;
    const sheetH = 297 * scale;
    host.innerHTML = '';
    const sheet = document.createElement('div');
    sheet.className = 'cde-a4-sheet';
    sheet.style.width = `${sheetW}px`;
    sheet.style.height = `${sheetH}px`;
    const cardW = a4.cardWmm * scale;
    const cardH = a4.cardHmm * scale;
    const gap = a4.gapMm * scale;
    const margin = a4.marginMm * scale;
    for (let r = 0; r < a4.rows; r++) {
      for (let c = 0; c < a4.cols; c++) {
        const slot = document.createElement('div');
        slot.className = 'cde-a4-card-slot';
        slot.style.left = `${margin + c * (cardW + gap)}px`;
        slot.style.top = `${margin + r * (cardH + gap)}px`;
        slot.style.width = `${cardW}px`;
        slot.style.height = `${cardH}px`;
        mountCardPreview(slot, bgUrl, elements, cardW, refFrameW);
        sheet.appendChild(slot);
      }
    }
    host.appendChild(sheet);
    renderA4Preview();
  }

  /* ── Bake & Save ── */
  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = src;
    });
  }

  async function bakeSide(side) {
    const pxW = Math.round((state.frame_width_mm / 25.4) * 300);
    const pxH = Math.round((state.frame_height_mm / 25.4) * 300);
    const canvas = document.createElement('canvas');
    canvas.width = pxW;
    canvas.height = pxH;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, pxW, pxH);

    const bg = backgroundLayer(side);
    if (!bg) return canvas.toDataURL('image/png');

    const src = state.pendingUploads[side][bg.id] || layerSrc(bg);
    if (!src) return canvas.toDataURL('image/png');

    try {
      const img = await loadImage(src);
      const cropW = img.naturalWidth;
      const cropH = Math.max(1, Math.round(cropW * frameRatio()));
      const maxSrcY = Math.max(0, img.naturalHeight - cropH);
      const cropY = Math.round((sideLayout(side).frameCropY ?? 0) * maxSrcY);
      ctx.drawImage(img, 0, cropY, cropW, cropH, 0, 0, pxW, pxH);
    } catch (_) { /* keep white */ }

    return canvas.toDataURL('image/png');
  }

  async function saveTemplate() {
    state.name = document.getElementById('cdeName')?.value || state.name;
    state.sides = +document.getElementById('cdeSides')?.value || 1;
    state.frame_width_mm = +document.getElementById('cdeFrameW')?.value || state.frame_width_mm;
    state.frame_height_mm = +document.getElementById('cdeFrameH')?.value || state.frame_height_mm;
    state.layout.a4 = state.layout.a4 || {};
    state.layout.a4.cardWidthMm = +document.getElementById('cdeCardWidth')?.value || state.layout.a4.cardWidthMm;
    state.layout.a4.marginMm = +document.getElementById('cdeMargin')?.value ?? state.layout.a4.marginMm;
    state.layout.a4.gapMm = +document.getElementById('cdeGap')?.value ?? state.layout.a4.gapMm;

    const body = {
      name: state.name,
      sides: state.sides,
      frame_width_mm: state.frame_width_mm,
      frame_height_mm: state.frame_height_mm,
      layout: state.layout,
      layer_uploads: state.pendingUploads,
      front_baked: await bakeSide('front'),
    };
    if (state.sides === 2) {
      body.back_baked = await bakeSide('back');
    }

    const btn = document.getElementById('cdeSaveBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Đang lưu…'; }

    try {
      let result;
      if (state.id) {
        result = await window.HTDApi.updateCardTemplate(state.id, body);
      } else {
        result = await window.HTDApi.createCardTemplate(body);
        state.id = result.id;
        boot.routes.preview = `${boot.apiBase.replace(/\/$/, '')}/admin/card-templates/${state.id}/preview`;
        boot.routes.update = `${boot.apiBase.replace(/\/$/, '')}/admin/card-templates/${state.id}`;
        history.replaceState(null, '', `${boot.apiBase.replace(/\/$/, '')}/admin/card-templates/${state.id}/edit`);
      }
      if (result?.layout) {
        state.layout = result.layout;
      }
      state.pendingUploads = { front: {}, back: {} };
      window.AdminToast?.show('Đã lưu mẫu thẻ.', 'success');
      return result;
    } catch (err) {
      window.AdminToast?.show(err.message || 'Lưu thất bại.', 'error');
      throw err;
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Lưu'; }
    }
  }

  async function openPreview() {
    try {
      await saveTemplate();
      const url = boot.routes.preview || `${boot.apiBase}/admin/card-templates/${state.id}/preview`;
      const modal = document.getElementById('cdePreviewModal');
      const frame = document.getElementById('cdePreviewFrame');
      if (frame) frame.src = url;
      if (modal) modal.removeAttribute('hidden');
    } catch (_) { /* toast shown */ }
  }

  /* ── Init ── */
  function bindUi() {
    document.getElementById('cdeUndo')?.addEventListener('click', undo);
    document.getElementById('cdeRedo')?.addEventListener('click', redo);
    document.getElementById('cdeSaveBtn')?.addEventListener('click', () => saveTemplate());
    document.getElementById('cdePreviewBtn')?.addEventListener('click', () => openPreview());
    document.getElementById('cdePreviewClose')?.addEventListener('click', () => {
      document.getElementById('cdePreviewModal')?.setAttribute('hidden', '');
    });
    document.getElementById('cdePreviewCloseBtn')?.addEventListener('click', () => {
      document.getElementById('cdePreviewModal')?.setAttribute('hidden', '');
    });

    document.querySelectorAll('.cde-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.cde-tab').forEach((t) => t.classList.toggle('is-active', t === tab));
        const name = tab.dataset.tab;
        document.getElementById('cdePanelDesign').hidden = name !== 'design';
        document.getElementById('cdePanelA4').hidden = name !== 'a4';
        if (name === 'a4') renderA4Grid();
      });
    });

    document.getElementById('cdeA4PreviewBtn')?.addEventListener('click', () => renderA4Grid());
    document.getElementById('cdeAutoTileBtn')?.addEventListener('click', () => renderA4Grid());

    document.querySelectorAll('.cde-side-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.currentSide = btn.dataset.side;
        document.querySelectorAll('.cde-side-btn').forEach((b) => b.classList.toggle('is-active', b === btn));
        state.selected = null;
        renderAll();
      });
    });

    document.getElementById('cdeAddImageBtn')?.addEventListener('click', () => {
      document.getElementById('cdeImageInput')?.click();
    });
    document.getElementById('cdeImageInput')?.addEventListener('change', (e) => {
      addImageFiles([...e.target.files]);
      e.target.value = '';
    });

    document.getElementById('cdeSides')?.addEventListener('change', (e) => {
      commit(() => {
        state.sides = +e.target.value;
        document.getElementById('cdeSideBackBtn').hidden = state.sides !== 2;
        if (state.sides === 1 && state.currentSide === 'back') state.currentSide = 'front';
      });
    });

    ['cdeFrameW', 'cdeFrameH'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', () => {
        commit(() => {
          state.frame_width_mm = +document.getElementById('cdeFrameW').value;
          state.frame_height_mm = +document.getElementById('cdeFrameH').value;
          clampFrameCropY();
        });
      });
    });

    document.getElementById('cdeCardWidth')?.addEventListener('input', () => {
      state.layout.a4 = state.layout.a4 || {};
      state.layout.a4.cardWidthMm = +document.getElementById('cdeCardWidth').value;
      renderA4Preview();
      if (!document.getElementById('cdePanelA4')?.hidden) renderA4Grid();
    });
    document.getElementById('cdeMargin')?.addEventListener('change', () => {
      state.layout.a4.marginMm = +document.getElementById('cdeMargin').value;
      if (!document.getElementById('cdePanelA4')?.hidden) renderA4Grid();
    });
    document.getElementById('cdeGap')?.addEventListener('change', () => {
      state.layout.a4.gapMm = +document.getElementById('cdeGap').value;
      if (!document.getElementById('cdePanelA4')?.hidden) renderA4Grid();
    });

    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
      if ((e.ctrlKey || e.metaKey) && e.key === 'y') { e.preventDefault(); redo(); }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (document.activeElement?.matches('input, textarea, select')) return;
        e.preventDefault();
        if (state.selected?.kind === 'element') deleteSelectedElement();
      }
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('#cdePopover') && !e.target.closest('.cde-el-settings, .cde-el-grip, .cde-el-handle')) {
        closePopover();
      }
    });

    const zoomInput = document.getElementById('cdeCanvasZoom');
    if (zoomInput) {
      zoomInput.value = String(state.canvasZoom);
      zoomInput.addEventListener('input', (e) => {
        state.canvasZoom = +e.target.value;
        layoutArtboard();
        renderElements();
      });
    }

    const stage = document.querySelector('.cde-stage');
    if (stage && typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver(() => {
        layoutArtboard();
        renderElements();
      });
      ro.observe(stage);
    }

    bindPopover();
  }

  function init() {
    if (!document.getElementById('cardEditorApp')) return;
    ['front', 'back'].forEach((side) => migrateLegacyCrop(side));
    renderChips();
    bindUi();
    renderAll();
  }

  function migrateLegacyCrop(side) {
    const sl = sideLayout(side);
    if ((sl.frameCropY ?? 0) > 0) return;
    const layers = sl.imageLayers || [];
    if (layers.length !== 1) return;
    const bg = layers[0];
    if (!bg || (bg.y ?? 0) <= 0.001) return;
    const ratio = bg.naturalRatio || frameRatio();
    const frameHFrac = frameRatio() * ratio;
    const maxTop = Math.max(0, 1 - frameHFrac);
    if (maxTop > 0) sl.frameCropY = Math.max(0, Math.min(1, bg.y / maxTop));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return { saveTemplate, openPreview };
})();
