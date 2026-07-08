/** Chemical Keyboard Editor — localStorage hoặc API admin khi có keyboard_id */
const LS_KEYBOARD = 'htd_chemical_keyboard';
const MAX_UNITS = 10;

const editorParams = new URLSearchParams(location.search);
const editorKeyboardId = parseInt(editorParams.get('keyboard_id'), 10) || null;
const editorEmbedded = editorParams.get('embedded') === 'admin';
const editorBackendMode = (window.HTD_CONFIG || {}).useBackend !== false && !editorParams.has('demo');

const KEY_LIBRARY = [
  'H', 'O', 'C', 'N', 'Cl', 'Na', 'K', 'Ca', 'Mg', 'Al', 'Fe', 'Cu', 'Zn', 'Ag', 'Ba', 'P', 'S',
  'He', 'Li', 'Be', 'B', 'F', 'Ne', 'Si', 'Ar',
  '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
  '(', ')', '+', '-', '=', '°C', '→',
  'SO₄²⁻', 'CO₂', 'H₂O', 'OH⁻', 'NH₃', 'H⁺', 'e⁻',
];

let uid = 0;
function genId(prefix) {
  return `${prefix}-${++uid}-${Date.now().toString(36)}`;
}

function makeKey(overrides = {}) {
  const text = overrides.text ?? 'A';
  return {
    id: overrides.id || genId('key'),
    text,
    value: overrides.value ?? text,
    width: overrides.width ?? 1,
    type: overrides.type || 'normal',
    background: overrides.background ?? '#FFFFFF',
    color: overrides.color ?? '#000000',
    border: overrides.border ?? '#D0D0D0',
    radius: overrides.radius ?? 6,
    fontSize: overrides.fontSize ?? 'M',
    keySize: overrides.keySize ?? 'M',
    tooltip: overrides.tooltip ?? '',
    disabled: overrides.disabled ?? false,
  };
}

function makeRow(overrides = {}) {
  return {
    id: overrides.id || genId('row'),
    name: overrides.name ?? 'Hàng mới',
    height: overrides.height ?? 'M',
    padding: overrides.padding ?? 2,
    spacing: overrides.spacing ?? 4,
    background: overrides.background ?? '#F5F5F5',
    border: overrides.border ?? '#E0E0E0',
    alignment: overrides.alignment ?? 'center',
    hidden: overrides.hidden ?? false,
    locked: overrides.locked ?? false,
    isSpaceRow: overrides.isSpaceRow ?? false,
    keys: overrides.keys ? overrides.keys.map(k => ({ ...k })) : [],
  };
}

function defaultKeyboard() {
  return {
    id: genId('kb'),
    name: 'Bàn phím Hóa học',
    defaults: {
      keySize: 'M',
      fontSize: 'M',
      textColor: '#000000',
      background: '#FFFFFF',
      border: '#D0D0D0',
    },
    rows: [
      makeRow({
        name: 'Numbers',
        keys: ['0','1','2','3','4','5','6','7','8','9'].map(t => makeKey({ text: t, value: t })),
      }),
      makeRow({
        name: 'Symbols',
        keys: [
          ...['(',')','+','-','=','°C','→'].map(t => makeKey({ text: t, value: t })),
          makeKey({ text: 'Delete', value: '⌫', width: 2, type: 'delete' }),
        ],
      }),
      makeRow({
        name: 'Elements 1',
        keys: ['H','O','C','N','Cl','Ca','Na','K'].map(t => makeKey({ text: t, value: t })),
      }),
      makeRow({
        name: 'Elements 2',
        keys: ['He','Li','Be','B','F','Ne','Mg','Al'].map(t => makeKey({ text: t, value: t })),
      }),
      makeRow({
        name: 'Elements 3',
        keys: ['Si','P','S','Ar','K','Ca','Fe','Cu'].map(t => makeKey({ text: t, value: t })),
      }),
      makeRow({
        name: 'Space & Send',
        isSpaceRow: true,
        locked: true,
        keys: [
          makeKey({ text: '🌐', value: '', width: 1, type: 'globe' }),
          makeKey({ text: '', value: '', width: 2, type: 'empty' }),
          makeKey({ text: 'Space', value: ' ', width: 4, type: 'space' }),
          makeKey({ text: 'Send', value: '\n', width: 3, type: 'send', background: '#2D46D6', color: '#FFFFFF', border: '#2D46D6' }),
        ],
      }),
    ],
    updatedAt: Date.now(),
  };
}

const editor = {
  data: null,
  selectedRowId: null,
  selectedKeyId: null,
  undoStack: [],
  redoStack: [],
  clipboardRow: null,
  contextRowId: null,
  device: 'phone',
  zoom: 1,
  previewMode: false,
  dragKey: null,
  dragRowId: null,
};

function cloneData(data) {
  return JSON.parse(JSON.stringify(data));
}

function loadKeyboard() {
  if (editorKeyboardId && editorBackendMode && window.HTDApi) {
    return null;
  }
  if (window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard) {
    return null;
  }
  try {
    const raw = localStorage.getItem(LS_KEYBOARD);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed?.rows?.length) return parsed;
    }
  } catch (_) {}
  return defaultKeyboard();
}

function ensureUniqueKeyIds(data) {
  const seen = new Set();
  (data.rows || []).forEach(row => {
    (row.keys || []).forEach(k => {
      if (!k.id || seen.has(k.id)) {
        k.id = genId('key');
      }
      seen.add(k.id);
    });
  });
}

function testKeyInputValue(key) {
  if (key.type === 'space') return ' ';
  if (key.type === 'send') return '\n';
  if (key.type === 'delete' || key.value === '⌫' || key.value === 'BACKSPACE') return null;
  if (key.value === 'SEND') return '\n';
  if (key.value !== undefined && key.value !== null && key.value !== '') return key.value;
  return key.text ?? '';
}

function editorDataFromKeyboard(keyboard) {
  const cfg = keyboard.config || {};
  const data = defaultKeyboard();
  data.name = keyboard.name || data.name;
  data.keyboardId = keyboard.id;
  data.subject = keyboard.subject || 'chemistry';
  if (cfg.defaults) data.defaults = { ...data.defaults, ...cfg.defaults };
  if (cfg.rows?.length) data.rows = cloneData(cfg.rows);
  if (cfg.smart_context) data.smart_context = cloneData(cfg.smart_context);
  ensureUniqueKeyIds(data);
  return data;
}

function configForApi(data) {
  return {
    schema_version: 1,
    defaults: data.defaults || {},
    rows: data.rows || [],
    smart_context: data.smart_context || {
      after_element: 'subscript',
      after_plus: 'coefficient',
    },
  };
}

function isAdminEditor() {
  const boot = window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard;
  return !!(boot && boot.id);
}

function getKeyboardId() {
  const boot = window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard;
  if (boot && boot.id) return boot.id;
  return editorKeyboardId || null;
}

function shouldUseApi() {
  return isAdminEditor() || (editorKeyboardId && editorBackendMode);
}

function saveKeyboard(silent) {
  editor.data.updatedAt = Date.now();
  if (!shouldUseApi()) {
    localStorage.setItem(LS_KEYBOARD, JSON.stringify(editor.data));
    if (!silent) notify('Đã lưu bản nháp', 'info');
  }
}

let previewCaptureTimer = null;
let previewCaptureInFlight = null;

function keyboardNeedsPreview() {
  const bootKb = window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard;
  return !!(bootKb && bootKb.id && !bootKb.preview_url);
}

async function waitForPreviewReady() {
  if (document.fonts && document.fonts.ready) {
    await document.fonts.ready;
  }
  await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}

function scheduleKeyboardPreviewCapture(delayMs = 600) {
  const bootKb = window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard;
  const kbId = bootKb && bootKb.id ? bootKb.id : editorKeyboardId;
  if (!kbId || !window.HTDApi) return;
  clearTimeout(previewCaptureTimer);
  previewCaptureTimer = setTimeout(() => {
    captureKeyboardPreview(kbId).catch(() => {});
  }, delayMs);
}

/** html2canvas không parse được oklch/oklab — chuyển sang dạng canvas chấp nhận (rgb/hex). */
function cssColorToCanvasSafe(color) {
  if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') return color;
  if (!/oklch|oklab|color\(|lab\(|lch\(/i.test(color)) return color;
  try {
    const probe = document.createElement('div');
    probe.style.color = color;
    document.body.appendChild(probe);
    const safe = getComputedStyle(probe).color;
    probe.remove();
    return safe || '#000000';
  } catch {
    return '#000000';
  }
}

const PREVIEW_CAPTURE_STYLE_PROPS = [
  'box-sizing', 'display', 'flex-direction', 'flex-wrap', 'align-items', 'justify-content',
  'align-self', 'flex', 'flex-grow', 'flex-shrink', 'flex-basis', 'gap', 'row-gap', 'column-gap',
  'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
  'margin', 'padding', 'border', 'border-radius', 'background', 'background-color',
  'color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height',
  'letter-spacing', 'text-align', 'vertical-align', 'white-space', 'overflow',
  'position', 'top', 'right', 'bottom', 'left', 'z-index', 'opacity',
  'box-shadow', 'outline', 'transform', 'transform-origin',
];

function bakeComputedStyles(sourceRoot, cloneRoot) {
  const sources = [sourceRoot, ...sourceRoot.querySelectorAll('*')];
  const clones = [cloneRoot, ...cloneRoot.querySelectorAll('*')];
  sources.forEach((src, i) => {
    const dst = clones[i];
    if (!(src instanceof Element) || !(dst instanceof Element)) return;
    if (dst.classList?.contains('kbe-row-hover-actions')) {
      dst.remove();
      return;
    }
    const cs = getComputedStyle(src);
    PREVIEW_CAPTURE_STYLE_PROPS.forEach((prop) => {
      let value = cs.getPropertyValue(prop);
      if (!value) return;
      if (/color|background|border|outline|shadow/i.test(prop)) {
        value = value.replace(
          /oklch\([^)]+\)|oklab\([^)]+\)|color\([^)]+\)|lab\([^)]+\)|lch\([^)]+\)/gi,
          (m) => cssColorToCanvasSafe(m)
        );
        if (/oklch|oklab|color\(/i.test(value)) {
          value = cssColorToCanvasSafe(value);
        }
      }
      dst.style.setProperty(prop, value);
    });
  });
}

/**
 * Clone bàn phím off-DOM + style inline RGB, bỏ stylesheet khi html2canvas clone
 * (tránh lỗi "unsupported color function oklch" từ CSS trang / browser).
 */
function buildKeyboardPreviewCaptureNode(sourceEl) {
  const host = document.createElement('div');
  host.id = 'kbePreviewCaptureHost';
  host.setAttribute('aria-hidden', 'true');
  Object.assign(host.style, {
    position: 'fixed',
    left: '-10000px',
    top: '0',
    width: `${Math.max(sourceEl.offsetWidth, 1)}px`,
    zIndex: '-1',
    pointerEvents: 'none',
    background: '#f5f5f5',
  });
  const clone = sourceEl.cloneNode(true);
  bakeComputedStyles(sourceEl, clone);
  host.appendChild(clone);
  document.body.appendChild(host);
  return { host, clone };
}

async function captureKeyboardPreview(kbId) {
  if (previewCaptureInFlight) return previewCaptureInFlight;

  previewCaptureInFlight = (async () => {
    const target = document.getElementById('kbePhoneKb');
    if (!target || !window.html2canvas || !window.HTDApi) {
      throw new Error('Thiếu html2canvas hoặc API client.');
    }
    if (!target.querySelector('.kbe-kb-row')) {
      throw new Error('Chưa render bàn phím để chụp preview.');
    }

    await waitForPreviewReady();

    const deviceWrap = document.getElementById('kbePhoneWrap');
    const stage = document.getElementById('kbePreviewStage');
    const area = document.getElementById('kbePreviewArea');
    const frame = document.getElementById('kbePhoneFrame');
    const restores = [];
    let captureHost = null;
    const stashStyle = (el, prop, value) => {
      if (!el) return;
      restores.push([el, prop, el.style.getPropertyValue(prop), el.style.getPropertyPriority(prop)]);
      el.style.setProperty(prop, value, 'important');
    };

    // html2canvas lệch khi parent có transform scale + overflow:hidden + filter
    stashStyle(deviceWrap, 'transform', 'none');
    stashStyle(stage, 'overflow', 'visible');
    stashStyle(area, 'overflow', 'visible');
    stashStyle(frame, 'filter', 'none');

    try {
      target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      await waitForPreviewReady();

      const rect = target.getBoundingClientRect();
      if (rect.width < 8 || rect.height < 8) {
        throw new Error('Khung bàn phím quá nhỏ để chụp preview.');
      }

      const capture = buildKeyboardPreviewCaptureNode(target);
      captureHost = capture.host;

      const canvas = await window.html2canvas(capture.clone, {
        backgroundColor: '#f5f5f5',
        scale: Math.min(2, window.devicePixelRatio || 1.5),
        logging: false,
        useCORS: true,
        foreignObjectRendering: false,
        onclone: (clonedDoc) => {
          // Bỏ mọi stylesheet — clone đã có style inline RGB an toàn cho html2canvas
          clonedDoc.querySelectorAll('link[rel="stylesheet"], style').forEach((node) => node.remove());
        },
      });
      if (!canvas.width || !canvas.height) {
        throw new Error('html2canvas trả ảnh trống.');
      }
      const image = canvas.toDataURL('image/png');
      const data = await HTDApi.uploadKeyboardPreview(kbId, image);
      const previewUrl = data.preview_url || (data.keyboard && data.keyboard.preview_url);
      if (previewUrl && window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard) {
        window.ADMIN_BOOT.keyboard.preview_url = previewUrl;
        window.ADMIN_BOOT.keyboard.preview_path = data.keyboard?.preview_path || null;
      }
      if (!previewUrl) {
        throw new Error('API không trả preview_url.');
      }
      return previewUrl;
    } finally {
      if (captureHost) captureHost.remove();
      restores.forEach(([el, prop, value, priority]) => {
        if (value) el.style.setProperty(prop, value, priority || '');
        else el.style.removeProperty(prop);
      });
    }
  })();

  try {
    return await previewCaptureInFlight;
  } catch (err) {
    console.warn('keyboard preview capture failed', err);
    throw err;
  } finally {
    previewCaptureInFlight = null;
  }
}

async function saveKeyboardToApi() {
  const kbId = getKeyboardId();
  const saveBtn = document.getElementById('kbeSaveBtn');
  if (!kbId || !window.HTDApi) {
    saveKeyboard();
    notify('Không kết nối được API — đã lưu bản nháp local', 'warning');
    return;
  }
  const issues = validateKeyboard();
  if (issues.length) {
    notify('Lỗi: ' + issues[0], 'error');
    return;
  }
  const name = document.getElementById('kbeNameInput')?.value?.trim() || editor.data.name;
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.classList.add('is-saving');
  }
  try {
    await HTDApi.updateKeyboard(kbId, {
      name,
      subject: editor.data.subject || 'chemistry',
      config: configForApi(editor.data),
    });
    editor.data.name = name;
    editor.data.keyboardId = kbId;
    notify('Đã lưu vào database', 'success');
    try {
      await captureKeyboardPreview(kbId);
    } catch (capErr) {
      notify(
        'Đã lưu config nhưng chưa tạo được ảnh preview: ' + (capErr.message || 'lỗi không rõ'),
        'warning'
      );
      scheduleKeyboardPreviewCapture(800);
    }
  } catch (err) {
    const msg = err.message || 'Không lưu được.';
    if (/Unauthenticated|401|419|đăng nhập/i.test(msg)) {
      location.href = HTDApi.loginUrl(location.pathname + location.search);
      return;
    }
    notify(msg, 'error');
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.classList.remove('is-saving');
    }
  }
}

function pushHistory() {
  editor.undoStack.push(cloneData(editor.data));
  if (editor.undoStack.length > 100) editor.undoStack.shift();
  editor.redoStack = [];
  updateHistoryBtns();
}

function undo() {
  if (!editor.undoStack.length) return;
  editor.redoStack.push(cloneData(editor.data));
  editor.data = editor.undoStack.pop();
  saveKeyboard(true);
  renderAll();
  updateHistoryBtns();
}

function redo() {
  if (!editor.redoStack.length) return;
  editor.undoStack.push(cloneData(editor.data));
  editor.data = editor.redoStack.pop();
  saveKeyboard(true);
  renderAll();
  updateHistoryBtns();
}

function updateHistoryBtns() {
  document.getElementById('kbeUndoBtn').disabled = !editor.undoStack.length;
  document.getElementById('kbeRedoBtn').disabled = !editor.redoStack.length;
}

function commit(mutator) {
  pushHistory();
  mutator();
  saveKeyboard(true);
  renderAll();
}

function rowUnits(row) {
  return row.keys.reduce((s, k) => s + (k.width || 1), 0);
}

function findRow(id) {
  return editor.data.rows.find(r => r.id === id);
}

function findKey(row, keyId) {
  return row?.keys.find(k => k.id === keyId);
}

function spaceRowIndex() {
  return editor.data.rows.findIndex(r => r.isSpaceRow);
}

function validateKeyboard() {
  const issues = [];
  let hasDelete = false;
  let hasSpace = false;
  let hasSend = false;

  editor.data.rows.forEach((row, i) => {
    if (row.hidden) return;
    const units = rowUnits(row);
    if (units > MAX_UNITS) issues.push(`Hàng "${row.name}" vượt ${MAX_UNITS} units (${units})`);
    if (row.keys.length === 0 && !row.isSpaceRow) issues.push(`Hàng "${row.name}" đang trống`);
    row.keys.forEach(k => {
      if (k.type === 'delete') hasDelete = true;
      if (k.type === 'space') hasSpace = true;
      if (k.type === 'send') hasSend = true;
      if (!k.text && k.type === 'normal') issues.push(`Phím trống ở hàng "${row.name}"`);
    });
    if (i < editor.data.rows.length - 1 && row.isSpaceRow) {
      issues.push('Hàng Space phải ở cuối');
    }
  });

  if (!hasDelete) issues.push('Thiếu phím Delete');
  if (!hasSpace) issues.push('Thiếu phím Space');
  if (!hasSend) issues.push('Thiếu phím Send');

  return issues;
}

function notify(msg, type = 'info') {
  if (window.AdminToast) {
    AdminToast.show(msg, type);
    return;
  }
  const el = document.getElementById('kbeToast');
  if (!el) return;
  el.textContent = msg;
  el.hidden = false;
  clearTimeout(notify._t);
  notify._t = setTimeout(() => { el.hidden = true; }, 3200);
}

function showToast(msg) {
  const isError = /^Lỗi|✕|không|Không/i.test(msg);
  notify(msg, isError ? 'error' : 'info');
}

/* ─── Render ─── */

function renderAll() {
  renderRowList();
  renderPhoneKb();
  renderProps();
  renderKeyLibrary();
  updateValidationBanner();
  syncDefaultOptions();
  document.getElementById('kbeNameInput').value = editor.data.name;
  requestAnimationFrame(applyZoom);
}

function renderRowList() {
  const el = document.getElementById('kbeRowList');
  el.innerHTML = editor.data.rows.map((row, idx) => {
    const units = rowUnits(row);
    const exceeded = units > MAX_UNITS;
    const sel = row.id === editor.selectedRowId ? ' selected' : '';
    const cls = [
      'kbe-row-item',
      sel,
      exceeded ? 'exceeded' : '',
      row.hidden ? 'hidden-row' : '',
      row.isSpaceRow ? 'space-row' : '',
    ].filter(Boolean).join(' ');

    const miniKeys = row.keys.map(k => {
      const w = k.width >= 2 ? ' w2' : '';
      const typeCls = k.type !== 'normal' ? ` ${k.type}` : '';
      const label = k.type === 'delete' ? '⌫' : k.type === 'space' ? '␣' : k.type === 'send' ? '⏎' : k.type === 'globe' ? '🌐' : k.text;
      if (k.type === 'empty') return '';
      return `<span class="kbe-mini-key${w}${typeCls}">${label}</span>`;
    }).join('');

    return `<div class="${cls}" data-row-id="${row.id}" draggable="${row.isSpaceRow ? 'false' : 'true'}">
      <div class="kbe-row-item-head">
        <span class="kbe-drag-handle" title="Kéo đổi thứ tự">⠿</span>
        <span class="kbe-row-item-name">Hàng ${idx + 1} — ${row.name}</span>
        <button type="button" class="kbe-row-menu-btn" data-menu-row="${row.id}" title="Tùy chọn">⋮</button>
      </div>
      <div class="kbe-row-mini-keys">${miniKeys}</div>
    </div>`;
  }).join('');

  el.querySelectorAll('.kbe-row-item').forEach(item => {
    item.addEventListener('click', e => {
      if (e.target.closest('.kbe-row-menu-btn') || e.target.closest('.kbe-drag-handle')) return;
      selectRow(item.dataset.rowId);
    });
    item.querySelector('.kbe-row-menu-btn')?.addEventListener('click', e => {
      e.stopPropagation();
      openRowMenu(item.dataset.rowId, e);
    });
    if (!findRow(item.dataset.rowId)?.isSpaceRow) {
      item.addEventListener('dragstart', onRowDragStart);
      item.addEventListener('dragend', onRowDragEnd);
      item.addEventListener('dragover', onRowDragOver);
      item.addEventListener('drop', onRowDrop);
    }
  });
}

function renderPhoneKb(container, options = {}) {
  const el = container || document.getElementById('kbePhoneKb');
  if (!el) return;
  const editable = options.editable !== false;

  el.innerHTML = editor.data.rows.map(row => {
    if (row.hidden) return `<div class="kbe-kb-row hidden-row" data-row-id="${row.id}"></div>`;
    const units = rowUnits(row);
    const exceeded = units > MAX_UNITS;
    const sel = editable && row.id === editor.selectedRowId && !editor.selectedKeyId ? ' selected' : '';
    const h = row.height || 'M';

    const keysHtml = row.keys.map(k => {
      if (k.type === 'empty') {
        return `<div class="kbe-kb-key type-empty" style="flex:${k.width} 1 0" data-key-id="${k.id}"></div>`;
      }
      const keySel = editable && k.id === editor.selectedKeyId ? ' selected' : '';
      const typeCls = k.type !== 'normal' ? ` type-${k.type}` : '';
      const sizeCls = ` size-${k.fontSize || 'M'}`;
      const dis = k.disabled ? ' disabled' : '';
      const label = k.type === 'delete' ? (k.text || '⌫') : k.text;
      return `<button type="button" class="kbe-kb-key${typeCls}${sizeCls}${keySel}${dis}"
        data-row-id="${row.id}" data-key-id="${k.id}"
        style="--key-units:${k.width};--key-bg:${k.background};--key-color:${k.color};--key-border:${k.border};--key-radius:${k.radius}px"
        title="${k.tooltip || ''}">${label}</button>`;
    }).join('');

    const hoverActions = editable
      ? `<div class="kbe-row-hover-actions">
        <button type="button" class="kbe-row-hover-btn" data-menu-row="${row.id}" title="Tùy chọn hàng">⋮</button>
      </div>`
      : '';

    return `<div class="kbe-kb-row height-${h}${sel}${exceeded ? ' exceeded' : ''}"
      data-row-id="${row.id}"
      style="--row-gap:${row.spacing}px;--row-pad:${row.padding}px;--row-bg:${row.background};--row-border:${row.border}">
      ${hoverActions}
      ${keysHtml}
    </div>`;
  }).join('');

  if (!editable) return;

  el.querySelectorAll('.kbe-kb-row').forEach(rowEl => {
    rowEl.addEventListener('click', e => {
      const keyBtn = e.target.closest('.kbe-kb-key');
      if (keyBtn?.dataset.keyId && !keyBtn.classList.contains('type-empty')) {
        selectKey(rowEl.dataset.rowId, keyBtn.dataset.keyId);
        return;
      }
      if (!e.target.closest('.kbe-row-hover-btn')) selectRow(rowEl.dataset.rowId);
    });
    rowEl.querySelector('.kbe-row-hover-btn')?.addEventListener('click', e => {
      e.stopPropagation();
      openRowMenu(rowEl.dataset.rowId, e);
    });
    rowEl.addEventListener('dragover', e => { e.preventDefault(); rowEl.classList.add('drop-target'); });
    rowEl.addEventListener('dragleave', () => rowEl.classList.remove('drop-target'));
    rowEl.addEventListener('drop', onKeyDropOnRow);
  });
}

function renderKeyDefaultOptionsBlock() {
  const d = editor.data.defaults;
  return `
    <div class="kbe-row-subcard kbe-row-subcard--defaults">
      <h4>Phím mới thêm vào hàng</h4>
      <p class="kbe-subcard-hint">Style mặc định khi thêm từ AVAILABLE KEYS</p>
      <div class="kbe-key-options" id="kbeKeyOptions">
        <div class="kbe-opt-group">
          <label>Kích thước</label>
          <div class="kbe-seg" data-opt="keySize">
            <button type="button" data-val="S" class="${d.keySize === 'S' ? 'active' : ''}">S</button>
            <button type="button" data-val="M" class="${d.keySize === 'M' ? 'active' : ''}">M</button>
            <button type="button" data-val="L" class="${d.keySize === 'L' ? 'active' : ''}">L</button>
          </div>
        </div>
        <div class="kbe-opt-group">
          <label>Cỡ chữ</label>
          <div class="kbe-seg" data-opt="fontSize">
            <button type="button" data-val="S" class="${d.fontSize === 'S' ? 'active' : ''}">A−</button>
            <button type="button" data-val="M" class="${d.fontSize === 'M' ? 'active' : ''}">A</button>
            <button type="button" data-val="L" class="${d.fontSize === 'L' ? 'active' : ''}">A+</button>
          </div>
        </div>
        <div class="kbe-opt-group">
          <label>Màu chữ</label>
          <div class="kbe-color-row">
            <input type="color" id="kbeDefaultTextColor" value="${toColor(d.textColor)}" aria-label="Màu chữ">
            <input type="text" class="kbe-hex-input" id="kbeDefaultTextHex" value="${d.textColor}" maxlength="7">
          </div>
        </div>
        <div class="kbe-opt-group">
          <label>Nền phím</label>
          <div class="kbe-color-row">
            <input type="color" id="kbeDefaultBgColor" value="${toColor(d.background)}" aria-label="Nền phím">
            <input type="text" class="kbe-hex-input" id="kbeDefaultBgHex" value="${d.background}" maxlength="7">
          </div>
        </div>
        <div class="kbe-opt-group kbe-opt-group--full">
          <label>Viền phím</label>
          <div class="kbe-color-row">
            <input type="color" id="kbeDefaultBorderColor" value="${toColor(d.border)}" aria-label="Viền phím">
            <input type="text" class="kbe-hex-input" id="kbeDefaultBorderHex" value="${d.border}" maxlength="7">
          </div>
        </div>
      </div>
    </div>`;
}

function bindDefaultKeyOptions() {
  document.querySelectorAll('#kbePropsContent [data-opt]').forEach(group => {
    group.querySelectorAll('button').forEach(btn => {
      btn.onclick = () => {
        const opt = group.dataset.opt;
        commit(() => {
          editor.data.defaults[opt === 'keySize' ? 'keySize' : 'fontSize'] = btn.dataset.val;
        });
      };
    });
  });
  bindColorPair('kbeDefaultTextColor', 'kbeDefaultTextHex', v => { editor.data.defaults.textColor = v; });
  bindColorPair('kbeDefaultBgColor', 'kbeDefaultBgHex', v => { editor.data.defaults.background = v; });
  bindColorPair('kbeDefaultBorderColor', 'kbeDefaultBorderHex', v => { editor.data.defaults.border = v; });
}

function renderProps() {
  const el = document.getElementById('kbePropsContent');
  const row = editor.selectedRowId ? findRow(editor.selectedRowId) : null;
  const key = row && editor.selectedKeyId ? findKey(row, editor.selectedKeyId) : null;

  if (key) {
    const isDelete = key.type === 'delete';
    const isSpecial = ['space', 'send', 'globe', 'empty'].includes(key.type);
    el.innerHTML = `
      <div class="kbe-props-section">
        <h3>Phím đã chọn</h3>
        <div class="kbe-row-props-card">
          <div class="kbe-field">
            <label>Text hiển thị</label>
            <input type="text" id="propKeyText" value="${esc(key.text)}">
          </div>
          <div class="kbe-field">
            <label>Giá trị output</label>
            <input type="text" id="propKeyValue" value="${esc(key.value)}" ${isDelete ? 'readonly' : ''}>
          </div>
          <div class="kbe-field-grid">
            <div class="kbe-field">
              <label>Width (units)</label>
              <input type="number" id="propKeyWidth" min="1" max="${MAX_UNITS}" value="${key.width}" ${isDelete || isSpecial ? 'readonly' : ''}>
            </div>
            <div class="kbe-field">
              <label>Bo góc (px)</label>
              <input type="number" id="propKeyRadius" min="0" max="20" value="${key.radius}">
            </div>
          </div>
          <div class="kbe-field">
            <label>Tooltip</label>
            <input type="text" id="propKeyTooltip" value="${esc(key.tooltip)}">
          </div>
          <div class="kbe-field-row">
            <label><input type="checkbox" id="propKeyDisabled" ${key.disabled ? 'checked' : ''}> Vô hiệu hóa</label>
          </div>
        </div>
      </div>
      <div class="kbe-props-section">
        <h3>Style phím</h3>
        <div class="kbe-row-props-card">
          <div class="kbe-field-grid">
            <div class="kbe-field">
              <label>Nền</label>
              <div class="kbe-color-row">
                <input type="color" id="propKeyBg" value="${toColor(key.background)}">
                <input type="text" class="kbe-hex-input" id="propKeyBgHex" value="${key.background}" maxlength="7">
              </div>
            </div>
            <div class="kbe-field">
              <label>Màu chữ</label>
              <div class="kbe-color-row">
                <input type="color" id="propKeyColor" value="${toColor(key.color)}">
                <input type="text" class="kbe-hex-input" id="propKeyColorHex" value="${key.color}" maxlength="7">
              </div>
            </div>
          </div>
          <div class="kbe-field">
            <label>Viền</label>
            <div class="kbe-color-row">
              <input type="color" id="propKeyBorder" value="${toColor(key.border)}">
              <input type="text" class="kbe-hex-input" id="propKeyBorderHex" value="${key.border}" maxlength="7">
            </div>
          </div>
          ${isDelete ? '<p class="kbe-subcard-hint">Phím Delete: width cố định 2 units, không thể xóa.</p>' : ''}
          ${!isDelete && !isSpecial ? `<div class="kbe-action-grid" style="margin-top:4px">
            <button type="button" id="propDeleteKey" class="danger">Xóa phím</button>
          </div>` : ''}
        </div>
      </div>`;
    bindKeyProps(key);
    return;
  }

  if (row) {
    const units = rowUnits(row);
    const unitsBad = units > MAX_UNITS;
    el.innerHTML = `
      <div class="kbe-props-section kbe-row-props">
        <div class="kbe-row-props-head">
          <h3>Hàng đã chọn</h3>
          <span class="kbe-units-badge${unitsBad ? ' is-bad' : ''}">${units} / ${MAX_UNITS}</span>
        </div>

        <div class="kbe-row-props-card">
          <div class="kbe-field">
            <label>Tên hàng</label>
            <input type="text" id="propRowName" value="${esc(row.name)}">
          </div>

          <div class="kbe-field">
            <label>Chiều cao hàng</label>
            <div class="kbe-seg" id="propRowHeight">
              <button type="button" data-val="S" class="${row.height === 'S' ? 'active' : ''}">Nhỏ</button>
              <button type="button" data-val="M" class="${row.height === 'M' ? 'active' : ''}">Vừa</button>
              <button type="button" data-val="L" class="${row.height === 'L' ? 'active' : ''}">Lớn</button>
            </div>
          </div>

          <div class="kbe-field-grid">
            <div class="kbe-field">
              <label>Padding</label>
              <input type="number" id="propRowPad" min="0" max="20" value="${row.padding}">
            </div>
            <div class="kbe-field">
              <label>Spacing</label>
              <input type="number" id="propRowSpace" min="0" max="20" value="${row.spacing}">
            </div>
          </div>

          <div class="kbe-field">
            <label>Căn chỉnh</label>
            <select id="propRowAlign">
              <option value="flex-start" ${row.alignment === 'flex-start' ? 'selected' : ''}>Trái</option>
              <option value="center" ${row.alignment === 'center' ? 'selected' : ''}>Giữa</option>
              <option value="flex-end" ${row.alignment === 'flex-end' ? 'selected' : ''}>Phải</option>
            </select>
          </div>

          <div class="kbe-field-grid">
            <div class="kbe-field">
              <label>Nền hàng</label>
              <div class="kbe-color-row">
                <input type="color" id="propRowBg" value="${toColor(row.background)}">
                <input type="text" class="kbe-hex-input" id="propRowBgHex" value="${row.background}" maxlength="7">
              </div>
            </div>
            <div class="kbe-field">
              <label>Viền hàng</label>
              <div class="kbe-color-row">
                <input type="color" id="propRowBorder" value="${toColor(row.border)}">
                <input type="text" class="kbe-hex-input" id="propRowBorderHex" value="${row.border}" maxlength="7">
              </div>
            </div>
          </div>
        </div>

        ${renderKeyDefaultOptionsBlock()}

        <div class="kbe-row-subcard kbe-row-subcard--actions">
          <h4>Hành động hàng</h4>
          <div class="kbe-action-grid">
            <button type="button" id="propDupRow">Nhân đôi</button>
            <button type="button" id="propMoveUp" ${row.isSpaceRow ? 'disabled' : ''}>Lên</button>
            <button type="button" id="propMoveDown" ${row.isSpaceRow ? 'disabled' : ''}>Xuống</button>
            <button type="button" id="propAutoFill">Lấp đầy</button>
            ${!row.isSpaceRow ? '<button type="button" id="propDeleteRow" class="danger">Xóa hàng</button>' : ''}
          </div>
        </div>
      </div>
      ${renderInfoCard()}`;
    bindRowProps(row);
    bindDefaultKeyOptions();
    return;
  }

  el.innerHTML = `<div class="kbe-empty-props">Chọn hàng hoặc phím trên preview để chỉnh thuộc tính.</div>${renderInfoCard()}`;
}

function renderInfoCard() {
  const totalKeys = editor.data.rows.reduce((s, r) => s + r.keys.filter(k => k.type !== 'empty').length, 0);
  const issues = validateKeyboard();
  const valid = issues.length === 0;
  return `<div class="kbe-props-section">
    <h3>Thông tin bàn phím</h3>
    <div class="kbe-info-card">
      <div class="kbe-info-row"><span>Tổng hàng</span><span>${editor.data.rows.length}</span></div>
      <div class="kbe-info-row"><span>Tổng phím</span><span>${totalKeys}</span></div>
      <div class="kbe-info-valid ${valid ? '' : 'invalid'}">
        ${valid ? '✓ Hợp lệ — tối đa 10 units/hàng' : '✕ ' + issues[0]}
      </div>
    </div>
  </div>`;
}

function renderKeyLibrary(filter = '') {
  const el = document.getElementById('kbeKeyLibrary');
  const q = filter.toLowerCase();
  const keys = KEY_LIBRARY.filter(k => !q || k.toLowerCase().includes(q));
  el.innerHTML = keys.map(k => {
    const wide = k.length > 2 ? ' wide' : '';
    return `<button type="button" class="kbe-lib-key${wide}" draggable="true" data-lib-key="${esc(k)}">${k}</button>`;
  }).join('');

  el.querySelectorAll('.kbe-lib-key').forEach(btn => {
    btn.addEventListener('dragstart', e => {
      editor.dragKey = { text: btn.dataset.libKey, fromLib: true };
      e.dataTransfer.setData('text/plain', btn.dataset.libKey);
    });
    btn.addEventListener('dragend', () => { editor.dragKey = null; });
    btn.addEventListener('click', () => {
      if (!editor.selectedRowId) {
        showToast('Chọn một hàng trước');
        return;
      }
      addKeyToRow(editor.selectedRowId, makeKey({ text: btn.dataset.libKey, value: btn.dataset.libKey }));
    });
  });
}

function updateValidationBanner() {
  const banner = document.getElementById('kbeValidationBanner');
  const issues = validateKeyboard();
  const exceeded = editor.data.rows.some(r => rowUnits(r) > MAX_UNITS);
  if (exceeded) {
    banner.hidden = false;
    banner.textContent = 'Exceeded maximum width — Một hoặc nhiều hàng vượt 10 units.';
  } else if (issues.length > 0 && issues[0].includes('vượt')) {
    banner.hidden = false;
    banner.textContent = issues[0];
  } else {
    banner.hidden = true;
  }
}

function syncDefaultOptions() {
  const d = editor.data.defaults;
  document.querySelectorAll('#kbePropsContent [data-opt="keySize"] button').forEach(b => {
    b.classList.toggle('active', b.dataset.val === d.keySize);
  });
  document.querySelectorAll('#kbePropsContent [data-opt="fontSize"] button').forEach(b => {
    b.classList.toggle('active', b.dataset.val === d.fontSize);
  });
  const textColor = document.getElementById('kbeDefaultTextColor');
  const textHex = document.getElementById('kbeDefaultTextHex');
  const bgColor = document.getElementById('kbeDefaultBgColor');
  const bgHex = document.getElementById('kbeDefaultBgHex');
  const borderColor = document.getElementById('kbeDefaultBorderColor');
  const borderHex = document.getElementById('kbeDefaultBorderHex');
  if (textColor) textColor.value = toColor(d.textColor);
  if (textHex) textHex.value = d.textColor;
  if (bgColor) bgColor.value = toColor(d.background);
  if (bgHex) bgHex.value = d.background;
  if (borderColor) borderColor.value = toColor(d.border);
  if (borderHex) borderHex.value = d.border;
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
}

function toColor(hex) {
  if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return '#000000';
  return hex;
}

/* ─── Selection ─── */

function selectRow(rowId) {
  editor.selectedRowId = rowId;
  editor.selectedKeyId = null;
  renderAll();
}

function selectKey(rowId, keyId) {
  editor.selectedRowId = rowId;
  editor.selectedKeyId = keyId;
  renderAll();
}

/* ─── Row operations ─── */

function addRow(atIndex) {
  const row = makeRow({ name: `Hàng ${editor.data.rows.length + 1}`, keys: [] });
  const spaceIdx = spaceRowIndex();
  const insertAt = atIndex ?? (spaceIdx >= 0 ? spaceIdx : editor.data.rows.length);
  commit(() => editor.data.rows.splice(insertAt, 0, row));
  selectRow(row.id);
}

function duplicateRow(rowId) {
  const src = findRow(rowId);
  if (!src || src.isSpaceRow) return;
  const copy = makeRow({
    ...src,
    id: genId('row'),
    name: src.name + ' (copy)',
    isSpaceRow: false,
    locked: false,
    keys: src.keys.map(k => ({ ...k, id: genId('key') })),
  });
  const idx = editor.data.rows.findIndex(r => r.id === rowId);
  commit(() => editor.data.rows.splice(idx + 1, 0, copy));
  selectRow(copy.id);
}

function deleteRow(rowId) {
  const row = findRow(rowId);
  if (!row || row.isSpaceRow) return;
  commit(() => {
    editor.data.rows = editor.data.rows.filter(r => r.id !== rowId);
  });
  editor.selectedRowId = null;
  editor.selectedKeyId = null;
  renderAll();
}

function moveRow(rowId, dir) {
  const row = findRow(rowId);
  if (!row || row.isSpaceRow) return;
  const idx = editor.data.rows.findIndex(r => r.id === rowId);
  const spaceIdx = spaceRowIndex();
  const newIdx = idx + dir;
  if (newIdx < 0) return;
  if (spaceIdx >= 0 && newIdx >= spaceIdx) return;
  commit(() => {
    const [r] = editor.data.rows.splice(idx, 1);
    editor.data.rows.splice(newIdx, 0, r);
  });
}

function autoFillRow(rowId) {
  const row = findRow(rowId);
  if (!row || row.isSpaceRow) return;
  const used = rowUnits(row);
  const remaining = MAX_UNITS - used;
  if (remaining <= 0) { showToast('Hàng đã đầy'); return; }
  commit(() => {
    for (let i = 0; i < remaining; i++) {
      row.keys.push(makeKey({
        text: '·',
        value: '',
        background: editor.data.defaults.background,
        color: editor.data.defaults.textColor,
        border: editor.data.defaults.border,
      }));
    }
  });
}

function addKeyToRow(rowId, key) {
  const row = findRow(rowId);
  if (!row || row.locked && row.isSpaceRow) return;
  if (rowUnits(row) + key.width > MAX_UNITS) {
    showToast('Không đủ chỗ — vượt 10 units');
    return;
  }
  const k = { ...key,
    background: key.background || editor.data.defaults.background,
    color: key.color || editor.data.defaults.textColor,
    border: key.border || editor.data.defaults.border,
    fontSize: key.fontSize || editor.data.defaults.fontSize,
    keySize: key.keySize || editor.data.defaults.keySize,
  };
  commit(() => row.keys.push(k));
  selectKey(rowId, k.id);
}

function deleteKey(rowId, keyId) {
  const row = findRow(rowId);
  const key = findKey(row, keyId);
  if (!key || key.type === 'delete' || ['space','send','globe','empty'].includes(key.type)) return;
  commit(() => {
    row.keys = row.keys.filter(k => k.id !== keyId);
  });
  editor.selectedKeyId = null;
  renderAll();
}

function rebalanceSpaceRow(row) {
  if (!row.isSpaceRow) return;
  const spaceKey = row.keys.find(k => k.type === 'space');
  const sendKey = row.keys.find(k => k.type === 'send');
  const emptyKey = row.keys.find(k => k.type === 'empty');
  if (!spaceKey || !sendKey) return;
  const spaceW = spaceKey.width;
  const sendW = sendKey.width;
  const leftW = MAX_UNITS - spaceW - sendW;
  if (emptyKey) emptyKey.width = Math.max(0, leftW);
}

/* ─── Context menu ─── */

function openRowMenu(rowId, e) {
  editor.contextRowId = rowId;
  const menu = document.getElementById('kbeRowMenu');
  const row = findRow(rowId);
  menu.querySelector('[data-action="delete"]').hidden = row?.isSpaceRow;
  menu.hidden = false;
  const x = e.clientX || e.pageX;
  const y = e.clientY || e.pageY;
  menu.style.left = Math.min(x, window.innerWidth - 200) + 'px';
  menu.style.top = Math.min(y, window.innerHeight - 280) + 'px';
}

function closeRowMenu() {
  document.getElementById('kbeRowMenu').hidden = true;
}

function handleRowMenuAction(action) {
  const rowId = editor.contextRowId;
  const row = findRow(rowId);
  if (!row) return;
  closeRowMenu();

  switch (action) {
    case 'add-above': {
      const idx = editor.data.rows.findIndex(r => r.id === rowId);
      const newRow = makeRow({ name: `Hàng ${editor.data.rows.length + 1}` });
      commit(() => editor.data.rows.splice(idx, 0, newRow));
      selectRow(newRow.id);
      break;
    }
    case 'add-below': {
      const idx = editor.data.rows.findIndex(r => r.id === rowId);
      const insertAt = row.isSpaceRow ? idx : idx + 1;
      const newRow = makeRow({ name: `Hàng ${editor.data.rows.length + 1}` });
      commit(() => editor.data.rows.splice(insertAt, 0, newRow));
      selectRow(newRow.id);
      break;
    }
    case 'duplicate': duplicateRow(rowId); break;
    case 'hide':
      commit(() => { row.hidden = !row.hidden; });
      break;
    case 'lock':
      commit(() => { row.locked = !row.locked; });
      break;
    case 'copy':
      editor.clipboardRow = cloneData(row);
      showToast('Đã sao chép hàng');
      break;
    case 'paste':
      if (!editor.clipboardRow) { showToast('Chưa có hàng sao chép'); return; }
      if (row.isSpaceRow) { showToast('Không thể dán vào hàng Space'); return; }
      commit(() => {
        row.keys = editor.clipboardRow.keys.map(k => ({ ...k, id: genId('key') }));
        row.name = editor.clipboardRow.name;
      });
      break;
    case 'delete': deleteRow(rowId); break;
  }
}

/* ─── Drag & drop ─── */

function onRowDragStart(e) {
  editor.dragRowId = e.currentTarget.dataset.rowId;
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}

function onRowDragEnd(e) {
  e.currentTarget.classList.remove('dragging');
  document.querySelectorAll('.kbe-row-item').forEach(el => el.classList.remove('drag-over'));
  editor.dragRowId = null;
}

function onRowDragOver(e) {
  e.preventDefault();
  const targetId = e.currentTarget.dataset.rowId;
  const target = findRow(targetId);
  if (target?.isSpaceRow) return;
  e.currentTarget.classList.add('drag-over');
}

function onRowDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
  const fromId = editor.dragRowId;
  const toId = e.currentTarget.dataset.rowId;
  if (!fromId || fromId === toId) return;
  const fromRow = findRow(fromId);
  const toRow = findRow(toId);
  if (fromRow?.isSpaceRow || toRow?.isSpaceRow) return;
  const fromIdx = editor.data.rows.findIndex(r => r.id === fromId);
  const toIdx = editor.data.rows.findIndex(r => r.id === toId);
  commit(() => {
    const [r] = editor.data.rows.splice(fromIdx, 1);
    editor.data.rows.splice(toIdx, 0, r);
  });
}

function onKeyDropOnRow(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-target');
  const rowId = e.currentTarget.dataset.rowId;
  const text = e.dataTransfer.getData('text/plain') || editor.dragKey?.text;
  if (!text || !rowId) return;
  addKeyToRow(rowId, makeKey({ text, value: text }));
}

/* ─── Property bindings ─── */

function bindRowProps(row) {
  const bind = (id, fn) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', () => commit(fn));
    el.addEventListener('input', () => { if (el.type === 'text' || el.type === 'number') commit(fn); });
  };

  bind('propRowName', () => { row.name = document.getElementById('propRowName').value; });
  bind('propRowPad', () => { row.padding = +document.getElementById('propRowPad').value || 0; });
  bind('propRowSpace', () => { row.spacing = +document.getElementById('propRowSpace').value || 0; });
  bind('propRowAlign', () => { row.alignment = document.getElementById('propRowAlign').value; });

  bindColorPair('propRowBg', 'propRowBgHex', v => { row.background = v; });
  bindColorPair('propRowBorder', 'propRowBorderHex', v => { row.border = v; });

  document.querySelectorAll('#propRowHeight button').forEach(btn => {
    btn.onclick = () => commit(() => { row.height = btn.dataset.val; });
  });

  document.getElementById('propDupRow')?.addEventListener('click', () => duplicateRow(row.id));
  document.getElementById('propMoveUp')?.addEventListener('click', () => moveRow(row.id, -1));
  document.getElementById('propMoveDown')?.addEventListener('click', () => moveRow(row.id, 1));
  document.getElementById('propAutoFill')?.addEventListener('click', () => autoFillRow(row.id));
  document.getElementById('propDeleteRow')?.addEventListener('click', () => deleteRow(row.id));
}

function bindKeyProps(key) {
  const row = findRow(editor.selectedRowId);
  const bind = (id, fn) => {
    const el = document.getElementById(id);
    if (!el) return;
    const evt = el.type === 'checkbox' ? 'change' : 'change';
    el.addEventListener(evt, () => commit(() => { fn(); if (row?.isSpaceRow) rebalanceSpaceRow(row); }));
    if (el.type === 'text' || el.type === 'number') {
      el.addEventListener('input', () => commit(() => { fn(); if (row?.isSpaceRow) rebalanceSpaceRow(row); }));
    }
  };

  bind('propKeyText', () => { key.text = document.getElementById('propKeyText').value; });
  bind('propKeyValue', () => { key.value = document.getElementById('propKeyValue').value; });
  bind('propKeyWidth', () => {
    if (key.type === 'delete') return;
    key.width = Math.max(1, Math.min(MAX_UNITS, +document.getElementById('propKeyWidth').value || 1));
  });
  bind('propKeyTooltip', () => { key.tooltip = document.getElementById('propKeyTooltip').value; });
  bind('propKeyDisabled', () => { key.disabled = document.getElementById('propKeyDisabled').checked; });
  bind('propKeyRadius', () => { key.radius = +document.getElementById('propKeyRadius').value || 0; });

  bindColorPair('propKeyBg', 'propKeyBgHex', v => { key.background = v; });
  bindColorPair('propKeyColor', 'propKeyColorHex', v => { key.color = v; });
  bindColorPair('propKeyBorder', 'propKeyBorderHex', v => { key.border = v; });

  document.getElementById('propDeleteKey')?.addEventListener('click', () => deleteKey(editor.selectedRowId, key.id));
}

function bindColorPair(colorId, hexId, setter) {
  const colorEl = document.getElementById(colorId);
  const hexEl = document.getElementById(hexId);
  if (!colorEl || !hexEl) return;
  colorEl.addEventListener('input', () => commit(() => { setter(colorEl.value); hexEl.value = colorEl.value.toUpperCase(); }));
  hexEl.addEventListener('change', () => commit(() => { setter(hexEl.value); colorEl.value = toColor(hexEl.value); }));
}

/* ─── Formula input (Test — smart_context) ─── */

const FORMULA_SUBSCRIPT = '₀₁₂₃₄₅₆₇₈₉';

function formulaEsc(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function formulaSubDisplay(digits) {
  return [...digits].map(d => {
    const i = d.charCodeAt(0) - 48;
    return i >= 0 && i <= 9 ? FORMULA_SUBSCRIPT[i] : d;
  }).join('');
}

function formulaIsElement(val) {
  return /^[A-Z][a-z]?$/.test(val);
}

function formulaIsDigit(val) {
  return /^[0-9]$/.test(val);
}

function formulaSmartContext(tokens, smartContext) {
  if (!tokens.length) return 'coefficient';
  const last = tokens[tokens.length - 1];
  if (last.type === 'element') return smartContext.after_element || 'subscript';
  if (last.type === 'subscript') return 'subscript';
  if (last.type === 'coefficient') return 'coefficient';
  if (last.type === 'symbol') {
    if (last.value === '+') return smartContext.after_plus || 'coefficient';
    if (last.value === ')') return 'subscript';
    return 'coefficient';
  }
  return 'coefficient';
}

function formulaMakeToken(type, value) {
  return {
    type,
    value,
    display: type === 'subscript' ? formulaSubDisplay(value) : value,
  };
}

function formulaAppendToken(tokens, raw, smartContext) {
  if (formulaIsDigit(raw)) {
    const ctx = formulaSmartContext(tokens, smartContext);
    const last = tokens[tokens.length - 1];
    if (ctx === 'subscript') {
      if (last?.type === 'subscript') {
        last.value += raw;
        last.display = formulaSubDisplay(last.value);
      } else {
        tokens.push(formulaMakeToken('subscript', raw));
      }
    } else if (last?.type === 'coefficient') {
      last.value += raw;
      last.display = last.value;
    } else {
      tokens.push(formulaMakeToken('coefficient', raw));
    }
    return;
  }
  if (formulaIsElement(raw)) {
    tokens.push(formulaMakeToken('element', raw));
    return;
  }
  tokens.push(formulaMakeToken('symbol', raw));
}

function formulaSerialize(tokens) {
  return tokens.map(t => t.value).join('');
}

function formulaRenderHtml(tokens) {
  return tokens.map(t => {
    if (t.type === 'subscript') return `<sub>${formulaEsc(t.value)}</sub>`;
    if (t.value === '\n') return '<br>';
    return formulaEsc(t.display);
  }).join('');
}

function formulaUpdateOutput(tokens) {
  const el = document.getElementById('kbeTestOutput');
  if (!el) return;
  el.innerHTML = formulaRenderHtml(tokens);
  el.dataset.serialized = formulaSerialize(tokens);
}

/* ─── Preview / Test ─── */

function buildOverlayPhone(containerId) {
  const container = document.getElementById(containerId);
  const isTablet = editor.device === 'tablet';
  const tabletCls = isTablet ? ' is-tablet' : '';
  container.innerHTML = `<div class="kbe-device-wrap${tabletCls}">
    <div class="kbe-device-frame">
      <div class="kbe-device-bezel">
        <div class="kbe-device-notch" aria-hidden="true"></div>
        <div class="kbe-device-camera" aria-hidden="true"></div>
        <div class="kbe-device-screen">
          <div class="kbe-phone-content"></div>
          <div class="kbe-phone-kb" id="${containerId}Kb"></div>
        </div>
        <div class="kbe-device-home" aria-hidden="true"></div>
      </div>
    </div>
  </div>`;
  renderPhoneKb(document.getElementById(`${containerId}Kb`), { editable: false });
  return container.querySelector('.kbe-device-wrap');
}

const FRAME_SIZE = { phone: { w: 268, h: 548 }, tablet: { w: 420, h: 560 } };

function fitOverlayDevice(wrap, hostEl) {
  if (!wrap) return 1;
  const size = FRAME_SIZE[editor.device] || FRAME_SIZE.phone;
  const host = hostEl || wrap.closest('.kbe-test-wrap') || wrap.parentElement;
  const pad = 48;
  const availH = Math.max((host?.clientHeight || window.innerHeight) - pad, 180);
  const availW = Math.max((host?.clientWidth || window.innerWidth) * 0.48 - 16, 140);
  const scale = Math.min(availW / size.w, availH / size.h, editor.zoom || 1, 1);
  wrap.style.transformOrigin = 'top center';
  wrap.style.transform = `scale(${scale})`;
  // transform không đổi layout box — thu chỗ chiếm chỗ theo scale để khỏi overflow
  const phoneHost = wrap.parentElement;
  if (phoneHost && phoneHost.classList.contains('kbe-overlay-phone')) {
    phoneHost.style.width = `${Math.round(size.w * scale)}px`;
    phoneHost.style.height = `${Math.round(size.h * scale)}px`;
    phoneHost.style.overflow = 'hidden';
  }
  return scale;
}

function openPreview() {
  const overlay = document.getElementById('kbePreviewOverlay');
  overlay.hidden = false;
  const wrap = buildOverlayPhone('kbeOverlayPhone');
  fitOverlayDevice(wrap, overlay);
}

function closePreview() {
  document.getElementById('kbePreviewOverlay').hidden = true;
}

let testTokens = [];

function openTest() {
  testTokens = [];
  const overlay = document.getElementById('kbeTestOverlay');
  overlay.hidden = false;
  formulaUpdateOutput(testTokens);
  const wrap = buildOverlayPhone('kbeTestPhone');
  fitOverlayDevice(wrap, document.querySelector('#kbeTestOverlay .kbe-test-wrap'));

  const smartContext = editor.data.smart_context || {
    after_element: 'subscript',
    after_plus: 'coefficient',
  };

  const kb = document.getElementById('kbeTestPhoneKb');
  kb.querySelectorAll('.kbe-kb-key').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      const row = findRow(btn.dataset.rowId);
      const key = findKey(row, btn.dataset.keyId);
      if (!key || key.disabled || key.type === 'empty') return;
      if (key.type === 'delete' || key.value === '⌫') {
        testTokens.pop();
      } else if (key.type === 'send') {
        testTokens.push(formulaMakeToken('symbol', '\n'));
      } else if (key.type === 'space') {
        testTokens.push(formulaMakeToken('symbol', ' '));
      } else {
        const raw = testKeyInputValue(key);
        if (raw != null) formulaAppendToken(testTokens, raw, smartContext);
      }
      formulaUpdateOutput(testTokens);
    });
  });
}

function closeTest() {
  document.getElementById('kbeTestOverlay').hidden = true;
}

/* ─── Export / Import ─── */

function exportJson() {
  const blob = new Blob([JSON.stringify(editor.data, null, 2)], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${editor.data.name.replace(/\s+/g, '_')}.json`;
  a.click();
  URL.revokeObjectURL(a.href);
  showToast('Đã xuất JSON');
}

function importJson(file) {
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const parsed = JSON.parse(reader.result);
      if (!parsed?.rows?.length) throw new Error('invalid');
      pushHistory();
      editor.data = parsed;
      ensureUniqueKeyIds(editor.data);
      saveKeyboard(true);
      renderAll();
      showToast('Đã nhập JSON');
    } catch (_) {
      showToast('File JSON không hợp lệ');
    }
  };
  reader.readAsText(file);
}

function validateAndSave() {
  if (shouldUseApi()) {
    saveKeyboardToApi();
    return;
  }
  const issues = validateKeyboard();
  if (issues.length) {
    showToast('Lỗi: ' + issues[0]);
    return;
  }
  saveKeyboard();
}

/* ─── Init ─── */

function previewAutoFitScale() {
  const stage = document.getElementById('kbePreviewStage');
  if (!stage) return 1;
  const size = FRAME_SIZE[editor.device] || FRAME_SIZE.phone;
  const pad = 24;
  const availW = Math.max(stage.clientWidth - pad, 100);
  const availH = Math.max(stage.clientHeight - pad, 100);
  return Math.min(availW / size.w, availH / size.h, 1);
}

function applyZoom() {
  const wrap = document.getElementById('kbePhoneWrap');
  if (!wrap) return;
  const autoFit = previewAutoFitScale();
  const scale = editor.zoom * autoFit;
  wrap.style.transform = `scale(${scale})`;
}

function applyDevice() {
  const wrap = document.getElementById('kbePhoneWrap');
  if (wrap) {
    wrap.classList.toggle('is-tablet', editor.device === 'tablet');
  }
  applyZoom();
}

function initEditor() {
  if (editorEmbedded) {
    document.body.classList.add('admin-embedded');
  }
  const logo = document.querySelector('.kbe-logo');
  if (logo && isAdminEditor()) {
    logo.href = '/admin/keyboards';
    logo.title = 'Về danh sách bàn phím';
    logo.addEventListener('click', async (e) => {
      if (!keyboardNeedsPreview()) return;
      e.preventDefault();
      const href = logo.href;
      try {
        await captureKeyboardPreview(getKeyboardId());
      } catch {
        // still navigate — preview can be retried on next visit
      }
      location.href = href;
    });
  }

  const boot = () => {
    editor.data = loadKeyboard() || defaultKeyboard();
    ensureUniqueKeyIds(editor.data);
    renderAll();
    scheduleKeyboardPreviewCapture(800);
  };

  if (window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard) {
    editor.data = editorDataFromKeyboard(window.ADMIN_BOOT.keyboard);
    renderAll();
    if (!window.ADMIN_BOOT.keyboard.preview_url) {
      scheduleKeyboardPreviewCapture(800);
    }
  } else if (editorKeyboardId && editorBackendMode && window.HTDApi) {
    HTDApi.getKeyboard(editorKeyboardId)
      .then((keyboard) => {
        editor.data = editorDataFromKeyboard(keyboard);
        renderAll();
        if (!keyboard.preview_url) {
          scheduleKeyboardPreviewCapture(800);
        }
      })
      .catch((err) => {
        const msg = err.message || 'Không tải được bàn phím.';
        if (/Unauthenticated|401|419|đăng nhập/i.test(msg)) {
          location.href = HTDApi.loginUrl(location.pathname + location.search);
          return;
        }
        showToast(msg);
        boot();
      });
  } else {
    boot();
  }

  document.getElementById('kbeUndoBtn').addEventListener('click', undo);
  document.getElementById('kbeRedoBtn').addEventListener('click', redo);
  document.getElementById('kbeSaveBtn').addEventListener('click', validateAndSave);
  const exportBtn = document.getElementById('kbeExportBtn');
  if (exportBtn) exportBtn.addEventListener('click', exportJson);
  const importBtn = document.getElementById('kbeImportBtn');
  if (importBtn) importBtn.addEventListener('click', () => document.getElementById('kbeImportFile').click());
  const importFile = document.getElementById('kbeImportFile');
  if (importFile) importFile.addEventListener('change', e => {
    if (e.target.files[0]) importJson(e.target.files[0]);
    e.target.value = '';
  });
  document.getElementById('kbePreviewBtn').addEventListener('click', openPreview);
  document.getElementById('kbePreviewClose').addEventListener('click', closePreview);
  document.getElementById('kbeTestBtn').addEventListener('click', openTest);
  document.getElementById('kbeTestClose').addEventListener('click', closeTest);
  document.getElementById('kbeTestClear').addEventListener('click', () => {
    testTokens = [];
    formulaUpdateOutput(testTokens);
  });
  document.getElementById('kbeAddRowBtn').addEventListener('click', () => addRow());

  document.getElementById('kbeNameInput').addEventListener('change', () => {
    commit(() => { editor.data.name = document.getElementById('kbeNameInput').value; });
  });

  document.querySelectorAll('.kbe-device-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.kbe-device-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      editor.device = btn.dataset.device;
      applyDevice();
    });
  });

  const zoomSelect = document.getElementById('kbeZoomSelect');
  if (zoomSelect) {
    zoomSelect.value = String(editor.zoom || 1);
    zoomSelect.addEventListener('change', () => {
      editor.zoom = parseFloat(zoomSelect.value) || 1;
      applyZoom();
    });
  }

  document.getElementById('kbeKeySearch').addEventListener('input', e => {
    renderKeyLibrary(e.target.value);
  });

  document.getElementById('kbeRowMenu').querySelectorAll('button').forEach(btn => {
    btn.addEventListener('click', () => handleRowMenuAction(btn.dataset.action));
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('#kbeRowMenu') && !e.target.closest('[data-menu-row]')) closeRowMenu();
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'y') { e.preventDefault(); redo(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); validateAndSave(); }
  });

  window.addEventListener('resize', applyZoom);
  const previewStage = document.getElementById('kbePreviewStage');
  if (previewStage && typeof ResizeObserver !== 'undefined') {
    new ResizeObserver(() => applyZoom()).observe(previewStage);
  }

  applyZoom();
  applyDevice();
}

// init via admin-keyboard-init.js when embedded in Laravel admin
