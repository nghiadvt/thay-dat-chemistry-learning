/**
 * duck-sprite-manager — quản lý "vịt chuyển động" trong trang cấu hình game Đua vịt.
 *
 * Mỗi vịt = bộ frame ảnh (8-10 hình) + tốc độ phát (fps).
 * - Grid card: mỗi card tự phát animation, click ảnh để dừng/chạy riêng từng con.
 * - Modal editor: upload nhiều frame (tự xếp theo tên file), kéo thả / sửa số
 *   để đổi thứ tự, preview player + slider tốc độ (số frame mỗi giây).
 * - Lưu qua API: POST /api/duck-sprites (tạo), POST .../frames + PATCH (sửa).
 */
(function () {
  const root = document.getElementById('duckSpriteManager');
  const modal = document.getElementById('duckSpriteModal');
  if (!root || !modal) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const apiBase = root.dataset.apiBase;
  const imageCropApiBase = root.dataset.imageCropApi;
  const MAX_FRAMES = 20;
  const MAX_TICK_MS = 100;

  const $ = (name, scope) => (scope || document).querySelector('[data-dsm="' + name + '"]');

  // --- Section elements ---
  const grid = $('grid', root);
  const emptyBox = $('empty', root);
  const loadingBox = $('loading', root);
  const toolbar = $('toolbar', root);
  const countLabel = $('count', root);
  const toggleAllBtn = $('toggle-all', root);
  const createBtn = $('create', root);

  // --- Modal elements ---
  const modalTitle = $('modal-title', modal);
  const stageImg = $('stage-img', modal);
  const stageEmpty = $('stage-empty', modal);
  const playBtn = $('play', modal);
  const scrub = $('scrub', modal);
  const frameLabel = $('frame-label', modal);
  const fpsInput = document.getElementById('dsmFps');
  const fpsLabel = $('fps-label', modal);
  const fpsSub = $('fps-sub', modal);
  const nameInput = document.getElementById('dsmName');
  const dropzone = $('drop', modal);
  const fileInput = $('file-input', modal);
  const framesBox = $('frames', modal);
  const statusBox = $('status', modal);
  const saveBtn = $('save', modal);
  const pickBtn = $('pick-cropped', modal);

  // --- Picker modal elements (chọn ảnh đã cắt sẵn từ image-cropper) ---
  const pickerModal = document.getElementById('dsmPickerModal');
  const $p = (name, scope) => (scope || pickerModal).querySelector('[data-dsmp="' + name + '"]');
  const pickerSearch = pickerModal ? $p('search') : null;
  const pickerSourceList = pickerModal ? $p('source-list') : null;
  const pickerSourceLoading = pickerModal ? $p('source-loading') : null;
  const pickerSourceEmpty = pickerModal ? $p('source-empty') : null;
  const pickerRegionsHint = pickerModal ? $p('regions-hint') : null;
  const pickerRegionGrid = pickerModal ? $p('region-grid') : null;
  const pickerRegionsToolbar = pickerModal ? $p('regions-toolbar') : null;
  const pickerSelectAllBtn = pickerModal ? $p('select-all') : null;
  const pickerStatus = pickerModal ? $p('status') : null;
  const pickerAddBtn = pickerModal ? $p('add') : null;

  // --- State ---
  let ducks = [];
  let allPlaying = true;
  const cardAnims = new Map(); // duckId -> {img, urls, fps, idx, acc, playing, card}
  let uidSeq = 0;

  const editor = {
    open: false,
    id: null,          // null = tạo mới
    frames: [],        // {uid, id|null, url, file|null}
    idx: 0,
    acc: 0,
    playing: true,
    saving: false,
    thumbEls: [],
  };

  const picker = {
    open: false,
    sources: null,            // cache danh sách ảnh gốc image-cropper
    activeSourceId: null,
    regionsCache: new Map(),  // sourceId -> regions[]
    currentRegions: [],       // regions của ảnh gốc đang xem trong grid
    selected: new Map(),      // regionId -> {id, url, label} — thứ tự Map = thứ tự bấm chọn
  };
  let closePicker = () => {}; // gán lại bên dưới nếu picker modal có mặt trên trang

  // ===== Helpers =====

  async function api(method, url, body, isJson) {
    const headers = {
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (isJson) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, { method, headers, body });
    let payload = null;
    try { payload = await res.json(); } catch (e) { /* non-JSON */ }

    if (!res.ok || !payload || payload.success === false) {
      const firstValidation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
      throw new Error(payload?.error || firstValidation || payload?.message || ('Lỗi ' + res.status));
    }
    return payload.data;
  }

  let toastEl = null;
  let toastTimer = 0;
  function toast(message, isError) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'dsm-toast';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = message;
    toastEl.classList.toggle('is-error', !!isError);
    toastEl.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 2600);
  }

  function naturalCompare(a, b) {
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  }

  function preload(urls) {
    urls.forEach((u) => { const im = new Image(); im.src = u; });
  }

  async function urlToFile(url, label) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Không tải được ảnh đã chọn.');
    const blob = await res.blob();
    const extFromUrl = /\.([a-z0-9]+)(?:\?.*)?$/i.exec(url)?.[1];
    const ext = (extFromUrl || blob.type.split('/')[1] || 'png').toLowerCase();
    const safeLabel = (label || 'frame').replace(/[^a-z0-9-_]+/gi, '-').slice(0, 40) || 'frame';
    return new File([blob], safeLabel + '-' + Date.now() + '.' + ext, { type: blob.type || 'image/png' });
  }

  // ===== Grid (danh sách vịt) =====

  function renderGrid() {
    cardAnims.clear();
    grid.innerHTML = '';
    ducks.forEach((duck) => grid.appendChild(buildCard(duck)));

    const n = ducks.length;
    countLabel.textContent = n + ' con vịt';
    toolbar.classList.toggle('drc-hidden', n === 0);
    emptyBox.classList.toggle('drc-hidden', n !== 0);
  }

  function buildCard(duck) {
    const urls = duck.frames.map((f) => f.url);
    preload(urls);

    const card = document.createElement('div');
    card.className = 'dsm-card';

    const stage = document.createElement('div');
    stage.className = 'dsm-card__stage';
    stage.title = 'Bấm để dừng / chạy animation';

    const img = document.createElement('img');
    img.alt = duck.name;
    if (urls[0]) img.src = urls[0];
    stage.appendChild(img);

    const badge = document.createElement('span');
    badge.className = 'dsm-card__badge';
    badge.textContent = duck.frames.length + 'f · ' + duck.fps + 'fps';
    stage.appendChild(badge);

    const pausedIcon = document.createElement('span');
    pausedIcon.className = 'dsm-card__paused';
    pausedIcon.textContent = '▶';
    stage.appendChild(pausedIcon);

    const info = document.createElement('div');
    info.className = 'dsm-card__info';
    info.innerHTML =
      '<span class="dsm-card__name"></span>' +
      '<span class="dsm-card__meta">' + duck.frames.length + ' frame · ' + duck.fps + ' hình/giây</span>';
    info.querySelector('.dsm-card__name').textContent = duck.name;

    const actions = document.createElement('div');
    actions.className = 'dsm-card__actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'dsm-btn';
    editBtn.textContent = '✏️ Sửa';
    editBtn.addEventListener('click', () => openEditor(duck));

    const delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'dsm-btn dsm-btn--danger';
    delBtn.textContent = '🗑';
    delBtn.title = 'Xóa vịt';
    delBtn.addEventListener('click', () => deleteDuck(duck));

    actions.appendChild(editBtn);
    actions.appendChild(delBtn);

    card.appendChild(stage);
    card.appendChild(info);
    card.appendChild(actions);

    const anim = { img, urls, fps: duck.fps, idx: 0, acc: 0, playing: allPlaying, card };
    card.classList.toggle('is-paused', !anim.playing);
    cardAnims.set(duck.id, anim);

    stage.addEventListener('click', () => {
      anim.playing = !anim.playing;
      card.classList.toggle('is-paused', !anim.playing);
    });

    return card;
  }

  function upsertDuck(duck) {
    const i = ducks.findIndex((d) => d.id === duck.id);
    if (i >= 0) ducks[i] = duck;
    else ducks.push(duck);
    ducks.sort((a, b) => a.name.localeCompare(b.name, 'vi', { numeric: true, sensitivity: 'base' }));
  }

  async function deleteDuck(duck) {
    if (!confirm('Xóa vịt "' + duck.name + '" và toàn bộ ' + duck.frames.length + ' frame?')) return;
    try {
      await api('DELETE', apiBase + '/' + duck.id);
      ducks = ducks.filter((d) => d.id !== duck.id);
      renderGrid();
      toast('Đã xóa vịt "' + duck.name + '".');
    } catch (e) {
      toast(e.message, true);
    }
  }

  toggleAllBtn.addEventListener('click', () => {
    allPlaying = !allPlaying;
    toggleAllBtn.textContent = allPlaying ? '⏸ Dừng tất cả' : '▶ Chạy tất cả';
    toggleAllBtn.setAttribute('aria-pressed', String(allPlaying));
    cardAnims.forEach((anim) => {
      anim.playing = allPlaying;
      anim.card.classList.toggle('is-paused', !allPlaying);
    });
  });

  // ===== Modal editor =====

  function openEditor(duck) {
    editor.open = true;
    editor.id = duck ? duck.id : null;
    editor.frames = duck
      ? duck.frames.map((f) => ({ uid: ++uidSeq, id: f.id, url: f.url, file: null }))
      : [];
    editor.idx = 0;
    editor.acc = 0;
    editor.playing = true;
    editor.saving = false;

    modalTitle.textContent = duck ? 'Sửa vịt: ' + duck.name : 'Thêm vịt mới';
    nameInput.value = duck ? duck.name : '';
    fpsInput.value = duck ? duck.fps : 10;
    setStatus('');
    saveBtn.disabled = false;

    syncFpsLabels();
    renderFrames();
    syncPlayer(true);

    modal.classList.remove('drc-hidden');
    document.body.style.overflow = 'hidden';
    if (!duck) nameInput.focus();
  }

  function closeEditor() {
    if (editor.saving) return;
    editor.open = false;
    editor.frames.forEach((f) => { if (f.file) URL.revokeObjectURL(f.url); });
    editor.frames = [];
    editor.thumbEls = [];
    framesBox.innerHTML = '';
    modal.classList.add('drc-hidden');
    document.body.style.overflow = '';
  }

  modal.querySelectorAll('[data-dsm="close"]').forEach((btn) => {
    btn.addEventListener('click', closeEditor);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (picker.open) { closePicker(); return; }
    if (editor.open) closeEditor();
  });

  function setStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.classList.toggle('is-error', !!isError);
  }

  // --- Upload frame ---

  function addFiles(fileList) {
    const files = Array.from(fileList).filter((f) => /^image\//.test(f.type));
    if (!files.length) return;
    files.sort((a, b) => naturalCompare(a.name, b.name));

    const room = MAX_FRAMES - editor.frames.length;
    if (room <= 0) {
      toast('Tối đa ' + MAX_FRAMES + ' frame mỗi vịt.', true);
      return;
    }
    if (files.length > room) {
      toast('Chỉ thêm được ' + room + ' frame nữa (tối đa ' + MAX_FRAMES + ').', true);
      files.length = room;
    }

    const wasEmpty = editor.frames.length === 0;
    files.forEach((file) => {
      editor.frames.push({ uid: ++uidSeq, id: null, url: URL.createObjectURL(file), file });
    });

    if (wasEmpty) editor.idx = 0;
    renderFrames();
    syncPlayer();
    setStatus('Đã thêm ' + files.length + ' frame — kéo thả để chỉnh thứ tự nếu cần.');
  }

  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });
  fileInput.addEventListener('change', () => {
    addFiles(fileInput.files);
    fileInput.value = '';
  });

  ['dragenter', 'dragover'].forEach((evt) => {
    dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.add('is-over');
    });
  });
  ['dragleave', 'drop'].forEach((evt) => {
    dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.remove('is-over');
    });
  });
  dropzone.addEventListener('drop', (e) => {
    if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
  });

  // --- Frame strip (thumbnail + thứ tự) ---

  let dragUid = null;

  function renderFrames() {
    framesBox.innerHTML = '';
    editor.thumbEls = [];

    editor.frames.forEach((frame, i) => {
      const thumb = document.createElement('div');
      thumb.className = 'dsm-thumb' + (frame.id === null ? ' is-new' : '');
      thumb.draggable = true;
      thumb.dataset.uid = String(frame.uid);
      thumb.title = 'Kéo để đổi thứ tự · bấm để xem frame này';

      const img = document.createElement('img');
      img.src = frame.url;
      img.alt = 'Frame ' + (i + 1);
      thumb.appendChild(img);

      const order = document.createElement('input');
      order.type = 'number';
      order.className = 'dsm-thumb__order';
      order.min = '1';
      order.max = String(editor.frames.length);
      order.value = String(i + 1);
      order.title = 'Số thứ tự — sửa số để di chuyển frame';
      order.addEventListener('click', (e) => e.stopPropagation());
      order.addEventListener('change', () => {
        const to = Math.min(Math.max(parseInt(order.value, 10) || (i + 1), 1), editor.frames.length) - 1;
        const from = editor.frames.findIndex((f) => f.uid === frame.uid);
        if (to === from) { order.value = String(from + 1); return; }
        const [moved] = editor.frames.splice(from, 1);
        editor.frames.splice(to, 0, moved);
        editor.idx = to;
        renderFrames();
        syncPlayer();
      });
      thumb.appendChild(order);

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'dsm-thumb__remove';
      remove.textContent = '✕';
      remove.title = 'Xóa frame này';
      remove.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = editor.frames.findIndex((f) => f.uid === frame.uid);
        if (idx < 0) return;
        const [removed] = editor.frames.splice(idx, 1);
        if (removed.file) URL.revokeObjectURL(removed.url);
        if (editor.idx >= editor.frames.length) editor.idx = 0;
        renderFrames();
        syncPlayer();
      });
      thumb.appendChild(remove);

      thumb.addEventListener('click', () => {
        editor.idx = editor.frames.findIndex((f) => f.uid === frame.uid);
        editor.playing = false;
        syncPlayer();
      });

      thumb.addEventListener('dragstart', (e) => {
        dragUid = frame.uid;
        thumb.classList.add('is-dragging');
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', String(frame.uid));
        }
      });
      thumb.addEventListener('dragend', () => {
        thumb.classList.remove('is-dragging');
        if (dragUid === null) return;
        dragUid = null;
        // Đồng bộ mảng theo thứ tự DOM sau khi kéo, rồi vẽ lại để cập nhật số
        const domOrder = Array.from(framesBox.querySelectorAll('.dsm-thumb')).map((el) => Number(el.dataset.uid));
        editor.frames.sort((a, b) => domOrder.indexOf(a.uid) - domOrder.indexOf(b.uid));
        renderFrames();
        syncPlayer();
      });

      editor.thumbEls.push(thumb);
      framesBox.appendChild(thumb);
    });

    if (editor.frames.length > 0 && editor.frames.length < MAX_FRAMES) {
      const add = document.createElement('button');
      add.type = 'button';
      add.className = 'dsm-thumb-add';
      add.innerHTML = '<span>＋</span><span>Thêm frame</span>';
      add.addEventListener('click', () => fileInput.click());
      framesBox.appendChild(add);
    }

    highlightThumb();
  }

  framesBox.addEventListener('dragover', (e) => {
    if (dragUid === null) return;
    e.preventDefault();
    const dragged = framesBox.querySelector('.dsm-thumb.is-dragging');
    const target = e.target.closest('.dsm-thumb');
    if (!dragged || !target || target === dragged) return;

    const rect = target.getBoundingClientRect();
    const before = (e.clientX - rect.left) < rect.width / 2;
    framesBox.insertBefore(dragged, before ? target : target.nextSibling);
  });

  function highlightThumb() {
    editor.thumbEls.forEach((el, i) => el.classList.toggle('is-current', i === editor.idx));
  }

  // --- Player preview ---

  function syncPlayer(resetIdx) {
    const n = editor.frames.length;
    if (resetIdx || editor.idx >= n) editor.idx = 0;

    stageImg.classList.toggle('drc-hidden', n === 0);
    stageEmpty.classList.toggle('drc-hidden', n > 0);
    playBtn.disabled = n < 2;
    scrub.disabled = n < 2;
    scrub.max = String(Math.max(n, 1));

    if (n === 0) {
      frameLabel.textContent = '–/–';
      playBtn.textContent = '▶';
      return;
    }
    updatePlayerFrame();
    playBtn.textContent = editor.playing && n > 1 ? '⏸' : '▶';
  }

  function updatePlayerFrame() {
    const frame = editor.frames[editor.idx];
    if (!frame) return;
    stageImg.src = frame.url;
    scrub.value = String(editor.idx + 1);
    frameLabel.textContent = (editor.idx + 1) + '/' + editor.frames.length;
    highlightThumb();
  }

  playBtn.addEventListener('click', () => {
    editor.playing = !editor.playing;
    editor.acc = 0;
    playBtn.textContent = editor.playing ? '⏸' : '▶';
  });

  scrub.addEventListener('input', () => {
    editor.playing = false;
    playBtn.textContent = '▶';
    editor.idx = Math.min(Math.max(parseInt(scrub.value, 10) - 1, 0), editor.frames.length - 1);
    updatePlayerFrame();
  });

  function syncFpsLabels() {
    const fps = parseInt(fpsInput.value, 10) || 10;
    fpsLabel.textContent = fps + ' hình/giây';
    fpsSub.textContent = '1 giây phát ' + fps + ' frame · mỗi frame hiện ≈ ' + Math.round(1000 / fps) + ' ms';
  }

  fpsInput.addEventListener('input', syncFpsLabels);

  // --- Lưu ---

  saveBtn.addEventListener('click', async () => {
    if (editor.saving) return;

    const name = nameInput.value.trim();
    const fps = parseInt(fpsInput.value, 10) || 10;
    if (!name) {
      setStatus('Nhập tên vịt trước khi lưu.', true);
      nameInput.focus();
      return;
    }
    if (editor.frames.length === 0) {
      setStatus('Upload ít nhất 1 ảnh frame trước khi lưu (khuyến nghị 8–10).', true);
      return;
    }

    editor.saving = true;
    saveBtn.disabled = true;
    setStatus('Đang lưu…');

    try {
      let duck;
      if (editor.id === null) {
        const fd = new FormData();
        fd.append('name', name);
        fd.append('fps', String(fps));
        editor.frames.forEach((f) => fd.append('frames[]', f.file));
        duck = await api('POST', apiBase, fd);
      } else {
        const newFrames = editor.frames.filter((f) => f.id === null);
        if (newFrames.length) {
          setStatus('Đang upload ' + newFrames.length + ' frame mới…');
          const fd = new FormData();
          newFrames.forEach((f) => fd.append('frames[]', f.file));
          const res = await api('POST', apiBase + '/' + editor.id + '/frames', fd);
          res.frames.forEach((created, i) => { newFrames[i].id = created.id; });
        }
        duck = await api('PATCH', apiBase + '/' + editor.id, JSON.stringify({
          name,
          fps,
          frame_ids: editor.frames.map((f) => f.id),
        }), true);
      }

      upsertDuck(duck);
      renderGrid();
      editor.saving = false;
      closeEditor();
      toast('Đã lưu vịt "' + duck.name + '" (' + duck.frames.length + ' frame · ' + duck.fps + ' fps).');
    } catch (e) {
      editor.saving = false;
      saveBtn.disabled = false;
      setStatus(e.message, true);
    }
  });

  createBtn.addEventListener('click', () => openEditor(null));

  // ===== Picker: chọn ảnh đã cắt sẵn từ image-cropper =====

  if (pickBtn && pickerModal && imageCropApiBase) {
    let pickerRegionEls = new Map(); // regionId -> item element đang hiển thị trong grid

    async function openPicker() {
      picker.open = true;
      picker.activeSourceId = null;
      picker.currentRegions = [];
      picker.selected.clear();
      pickerRegionGrid.innerHTML = '';
      pickerRegionEls = new Map();
      pickerRegionsToolbar.classList.add('drc-hidden');
      pickerRegionsHint.classList.remove('drc-hidden');
      pickerRegionsHint.textContent = '← Chọn 1 ảnh gốc bên trái để xem "Ảnh đã lưu".';
      pickerStatus.textContent = '';
      updatePickerAddBtn();

      pickerModal.classList.remove('drc-hidden');
      document.body.style.overflow = 'hidden';

      if (!picker.sources) {
        pickerSourceLoading.classList.remove('drc-hidden');
        pickerSourceEmpty.classList.add('drc-hidden');
        try {
          picker.sources = await api('GET', imageCropApiBase);
        } catch (e) {
          pickerSourceLoading.textContent = 'Không tải được danh sách ảnh: ' + e.message;
          return;
        }
        pickerSourceLoading.classList.add('drc-hidden');
      }
      renderPickerSources();
    }

    closePicker = function () {
      picker.open = false;
      pickerModal.classList.add('drc-hidden');
      document.body.style.overflow = editor.open ? 'hidden' : '';
    };

    function renderPickerSources() {
      const q = pickerSearch.value.trim().toLowerCase();
      const list = (picker.sources || []).filter((s) => !q || s.name.toLowerCase().includes(q));
      pickerSourceList.innerHTML = '';
      pickerSourceEmpty.classList.toggle('drc-hidden', list.length !== 0 || !picker.sources);

      list.forEach((source) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dsm-picker-source-item' + (source.id === picker.activeSourceId ? ' is-active' : '');

        const thumb = document.createElement('span');
        thumb.className = 'dsm-picker-source-item__thumb';
        if (source.thumb_url) {
          const img = document.createElement('img');
          img.src = source.thumb_url;
          img.alt = '';
          thumb.appendChild(img);
        }

        const name = document.createElement('span');
        name.className = 'dsm-picker-source-item__name';
        name.textContent = source.name;

        const count = document.createElement('span');
        count.className = 'dsm-picker-source-item__count';
        count.textContent = String(source.regions_count);

        item.appendChild(thumb);
        item.appendChild(name);
        item.appendChild(count);
        item.addEventListener('click', () => selectPickerSource(source));
        pickerSourceList.appendChild(item);
      });
    }

    async function selectPickerSource(source) {
      picker.activeSourceId = source.id;
      picker.currentRegions = [];
      renderPickerSources();
      pickerRegionsToolbar.classList.add('drc-hidden');
      pickerRegionsHint.classList.remove('drc-hidden');
      pickerRegionsHint.textContent = 'Đang tải "Ảnh đã lưu"…';
      pickerRegionGrid.innerHTML = '';
      pickerRegionEls = new Map();

      let regions = picker.regionsCache.get(source.id);
      if (!regions) {
        try {
          const detail = await api('GET', imageCropApiBase + '/' + source.id);
          regions = detail.regions;
          picker.regionsCache.set(source.id, regions);
        } catch (e) {
          pickerRegionsHint.textContent = 'Không tải được ảnh: ' + e.message;
          return;
        }
      }
      if (picker.activeSourceId !== source.id) return; // đã chuyển sang ảnh khác trong lúc chờ tải

      if (!regions.length) {
        pickerRegionsHint.textContent = 'Ảnh gốc này chưa có "Ảnh đã lưu" nào.';
        return;
      }
      picker.currentRegions = regions;
      pickerRegionsHint.classList.add('drc-hidden');
      pickerRegionsToolbar.classList.remove('drc-hidden');
      renderPickerRegions(regions);
      updateSelectAllBtn();
    }

    function renderPickerRegions(regions) {
      pickerRegionGrid.innerHTML = '';
      pickerRegionEls = new Map();

      regions.forEach((region) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dsm-picker-region-item';

        const img = document.createElement('img');
        img.src = region.url;
        img.alt = region.label || '';
        item.appendChild(img);

        const check = document.createElement('span');
        check.className = 'dsm-picker-region-item__check';
        item.appendChild(check);

        if (region.label) {
          const label = document.createElement('span');
          label.className = 'dsm-picker-region-item__label';
          label.textContent = region.label;
          item.appendChild(label);
        }

        item.addEventListener('click', () => {
          if (picker.selected.has(region.id)) picker.selected.delete(region.id);
          else picker.selected.set(region.id, region);
          updateSelectionBadges();
          updatePickerAddBtn();
          updateSelectAllBtn();
        });

        pickerRegionEls.set(region.id, item);
        pickerRegionGrid.appendChild(item);
      });

      updateSelectionBadges();
    }

    // Đánh lại số thứ tự trên góc mỗi ảnh theo đúng thứ tự đã bấm chọn
    // (Map giữ thứ tự chèn) — chạy lại sau mỗi lần chọn/bỏ chọn.
    function updateSelectionBadges() {
      const order = Array.from(picker.selected.keys());
      pickerRegionEls.forEach((item, regionId) => {
        const idx = order.indexOf(regionId);
        const selected = idx !== -1;
        item.classList.toggle('is-selected', selected);
        const check = item.querySelector('.dsm-picker-region-item__check');
        if (check) check.textContent = selected ? String(idx + 1) : '';
      });
    }

    function updateSelectAllBtn() {
      if (!picker.currentRegions.length) {
        pickerSelectAllBtn.disabled = true;
        pickerSelectAllBtn.textContent = 'Chọn tất cả';
        return;
      }
      const allSelected = picker.currentRegions.every((r) => picker.selected.has(r.id));
      pickerSelectAllBtn.disabled = false;
      pickerSelectAllBtn.textContent = allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả (' + picker.currentRegions.length + ')';
    }

    function updatePickerAddBtn() {
      const n = picker.selected.size;
      pickerAddBtn.disabled = n === 0;
      pickerAddBtn.textContent = n ? 'Thêm ' + n + ' ảnh đã chọn' : 'Thêm ảnh đã chọn';
    }

    pickBtn.addEventListener('click', openPicker);
    pickerSearch.addEventListener('input', renderPickerSources);
    pickerModal.querySelectorAll('[data-dsmp="close"]').forEach((btn) => btn.addEventListener('click', closePicker));

    pickerSelectAllBtn.addEventListener('click', () => {
      const allSelected = picker.currentRegions.length > 0 && picker.currentRegions.every((r) => picker.selected.has(r.id));
      if (allSelected) {
        picker.currentRegions.forEach((r) => picker.selected.delete(r.id));
      } else {
        // Thêm theo đúng thứ tự hiển thị trong grid, bỏ qua ảnh đã chọn từ trước.
        picker.currentRegions.forEach((r) => { if (!picker.selected.has(r.id)) picker.selected.set(r.id, r); });
      }
      updateSelectionBadges();
      updatePickerAddBtn();
      updateSelectAllBtn();
    });

    pickerAddBtn.addEventListener('click', async () => {
      if (!picker.selected.size) return;
      const regions = Array.from(picker.selected.values());
      const room = MAX_FRAMES - editor.frames.length;
      if (room <= 0) {
        toast('Tối đa ' + MAX_FRAMES + ' frame mỗi vịt.', true);
        return;
      }
      const toAdd = regions.slice(0, room);
      if (toAdd.length < regions.length) {
        toast('Chỉ thêm được ' + toAdd.length + ' ảnh nữa (tối đa ' + MAX_FRAMES + ').', true);
      }

      pickerAddBtn.disabled = true;
      pickerStatus.textContent = 'Đang tải ảnh đã chọn…';
      try {
        const wasEmpty = editor.frames.length === 0;
        for (const region of toAdd) {
          const file = await urlToFile(region.url, region.label);
          editor.frames.push({ uid: ++uidSeq, id: null, url: URL.createObjectURL(file), file });
        }
        if (wasEmpty) editor.idx = 0;
        renderFrames();
        syncPlayer();
        setStatus('Đã thêm ' + toAdd.length + ' frame từ ảnh đã cắt sẵn.');
        closePicker();
      } catch (e) {
        pickerStatus.textContent = e.message;
      } finally {
        pickerAddBtn.disabled = picker.selected.size === 0;
      }
    });
  }

  // ===== Animation loop chung (grid + editor preview) =====

  let lastTs = 0;
  function tick(ts) {
    const dt = Math.min(lastTs ? ts - lastTs : 0, MAX_TICK_MS);
    lastTs = ts;

    cardAnims.forEach((anim) => {
      if (!anim.playing || anim.urls.length < 2) return;
      anim.acc += dt;
      const frameMs = 1000 / anim.fps;
      if (anim.acc >= frameMs) {
        anim.idx = (anim.idx + Math.floor(anim.acc / frameMs)) % anim.urls.length;
        anim.acc %= frameMs;
        anim.img.src = anim.urls[anim.idx];
      }
    });

    if (editor.open && editor.playing && editor.frames.length > 1) {
      editor.acc += dt;
      const fps = parseInt(fpsInput.value, 10) || 10;
      const frameMs = 1000 / fps;
      if (editor.acc >= frameMs) {
        editor.idx = (editor.idx + Math.floor(editor.acc / frameMs)) % editor.frames.length;
        editor.acc %= frameMs;
        updatePlayerFrame();
      }
    }

    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  // ===== Tải danh sách ban đầu =====

  (async function load() {
    try {
      ducks = await api('GET', apiBase);
      loadingBox.classList.add('drc-hidden');
      renderGrid();
    } catch (e) {
      loadingBox.textContent = 'Không tải được danh sách vịt: ' + e.message;
    }
  })();
})();
