/**
 * Workspace "Bảng nguyên tố" — chỉnh trực tiếp trên bảng.
 *
 * - Cấu hình lưu vào bản NHÁP (debounce PUT config). Không tới học sinh cho tới Xuất bản.
 * - Click ô → popover chỉnh sáng/ẩn/pro/thứ tự + sửa nhanh dữ liệu gốc & sound.
 * - Chọn nhiều ô → thao tác hàng loạt.
 * - Đổi chế độ xem: Sửa / như HS thường / như HS Pro.
 */
(function () {
  'use strict';
  var boot = window.__PERIODIC_BOOT__;
  if (!boot || !window.PeriodicGrid) return;

  var URLS = boot.urls;
  var cfg = {};                 // id -> {is_lit,is_visible,requires_pro,sort_override}
  Object.keys(boot.config).forEach(function (id) { cfg[id] = Object.assign({}, boot.config[id]); });
  var catalog = {};             // id -> base element
  boot.elements.forEach(function (e) { catalog[e.id] = e; });
  var cats = boot.categories.slice();  // list [{id,slug,name,color,deep}]

  var mode = 'edit';
  var multi = false;
  var selection = {};           // id -> true
  var currentEl = null;         // id đang mở popover
  var saveTimer = null;

  var gridEl = document.getElementById('pwGrid');
  var stage = document.querySelector('[data-stage]');
  var pop = document.querySelector('[data-pop]');

  /* ---------- helpers ---------- */
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  async function api(url, method, body) {
    var opts = { method: method, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, credentials: 'same-origin' };
    if (body instanceof FormData) { opts.body = body; }
    else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    var res = await fetch(url, opts);
    var json = null; try { json = await res.json(); } catch (e) {}
    if (!res.ok || (json && json.success === false)) throw new Error((json && json.error) || ('Lỗi ' + res.status));
    return json ? json.data : null;
  }
  function toast(msg) {
    if (window.AdminToast && AdminToast.show) AdminToast.show(msg);
    else console.log(msg);
  }
  function catColorMap() {
    // Trả mảng đầy đủ (id/slug/name/color/deep) — PeriodicGrid tự index.
    return cats.map(function (c) {
      return { id: c.id, slug: c.slug, name: c.name, color: c.color, deep: c.deep || c.deep_color };
    });
  }

  /* ---------- render bảng ---------- */
  function mergedElements() {
    return boot.elements.map(function (e) {
      var c = cfg[e.id] || { is_lit: true, is_visible: true, requires_pro: false };
      return {
        id: e.id, z: e.z, symbol: e.symbol, name_vi: e.name_vi, mass: e.mass,
        group: e.group_no, period: e.period_no, cat: e.category_id,
        lit: c.is_lit, vis: c.is_visible, pro: c.requires_pro,
      };
    });
  }

  function renderGrid() {
    PeriodicGrid.render(gridEl, {
      elements: mergedElements(),
      categories: catColorMap(),
      mode: mode,
      showLegend: true,
    });
    stage.classList.toggle('is-preview', mode !== 'edit');
    if (mode === 'edit') {
      gridEl.querySelectorAll('button.pg-cell').forEach(function (cell) {
        var id = cell.getAttribute('data-el');
        if (selection[id]) cell.classList.add('is-multi');
        cell.addEventListener('click', function (e) { onCellClick(id, cell, e); });
      });
    }
  }

  function onCellClick(id, cell, e) {
    if (multi) {
      if (selection[id]) { delete selection[id]; cell.classList.remove('is-multi'); }
      else { selection[id] = true; cell.classList.add('is-multi'); }
      updateSelCount();
      return;
    }
    openPopover(id, cell);
  }

  /* ---------- popover 1 ô ---------- */
  var popToggles = pop.querySelectorAll('.admin-toggle-input'); // [0]=lit [1]=visible [2]=pro
  function openPopover(id, cell) {
    currentEl = id;
    var el = catalog[id];
    var c = cfg[id];
    pop.querySelector('[data-pop-sym]').textContent = el.symbol;
    pop.querySelector('[data-pop-name]').textContent = el.name_vi;
    pop.querySelector('[data-pop-z]').textContent = 'Z = ' + el.z;
    popToggles[0].checked = !!c.is_lit;
    popToggles[1].checked = !!c.is_visible;
    popToggles[2].checked = !!c.requires_pro;

    // Sửa dữ liệu gốc
    pop.querySelector('[data-el-name-vi]').value = el.name_vi || '';
    pop.querySelector('[data-el-name-en]').value = el.name_en || '';
    pop.querySelector('[data-el-mass]').value = el.mass;
    pop.querySelector('[data-el-order]').value = (c.sort_override != null ? c.sort_override : el.sort_order);
    fillCatSelect(pop.querySelector('[data-el-cat]'), el.category_id);
    pop.querySelector('[data-el-sound-state]').textContent = el.sound_url ? '🔊 Đã có file âm thanh' : 'Chưa có file — đang dùng giọng máy (TTS)';
    pop.querySelector('[data-el-sound]').value = '';

    positionPop(cell);
    pop.hidden = false;
  }
  function positionPop(cell) {
    var r = cell.getBoundingClientRect();
    var w = 260, h = pop.offsetHeight || 320;
    var left = Math.min(r.right + 8, window.innerWidth - w - 8);
    if (r.right + 8 + w > window.innerWidth) left = Math.max(8, r.left - w - 8);
    var top = Math.min(Math.max(8, r.top), window.innerHeight - h - 8);
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
  }
  function closePopover() { pop.hidden = true; currentEl = null; }

  popToggles[0].addEventListener('change', function () { setCfg(currentEl, 'is_lit', this.checked); });
  popToggles[1].addEventListener('change', function () { setCfg(currentEl, 'is_visible', this.checked); });
  popToggles[2].addEventListener('change', function () { setCfg(currentEl, 'requires_pro', this.checked); });
  pop.querySelector('[data-pop-close]').addEventListener('click', closePopover);
  pop.querySelector('[data-el-order]').addEventListener('change', function () {
    var v = this.value === '' ? null : parseInt(this.value, 10);
    setCfg(currentEl, 'sort_override', isNaN(v) ? null : v);
  });

  function setCfg(id, key, val) {
    if (id == null || !cfg[id]) return;
    cfg[id][key] = val;
    renderGrid();
    markDirty();
    scheduleSave();
  }

  /* ---------- lưu nháp (debounce) ---------- */
  function scheduleSave() {
    setSaveState('saving');
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveNow, 600);
  }
  async function saveNow() {
    var name = document.querySelector('[data-name-input]').value;
    var elements = Object.keys(cfg).map(function (id) {
      var c = cfg[id];
      return {
        id: Number(id), is_lit: !!c.is_lit, is_visible: !!c.is_visible,
        requires_pro: !!c.requires_pro,
        sort_override: (c.sort_override == null || c.sort_override === '') ? null : Number(c.sort_override),
      };
    });
    try {
      await api(URLS.saveConfig, 'PUT', { name: name, elements: elements });
      setSaveState('saved');
    } catch (e) { setSaveState('error'); toast('Lưu nháp lỗi: ' + e.message, '⚠️'); }
  }
  function setSaveState(s) {
    document.querySelectorAll('[data-savestate]').forEach(function (n) {
      n.classList.remove('is-saving', 'is-saved');
      if (s === 'saving') { n.textContent = 'Đang lưu…'; n.classList.add('is-saving'); }
      else if (s === 'saved') { n.textContent = 'Đã lưu nháp'; n.classList.add('is-saved'); }
      else if (s === 'error') { n.textContent = 'Lưu lỗi'; }
    });
  }
  function markDirty() {
    var note = document.querySelector('[data-dirty-note]');
    if (note) note.hidden = false;
  }
  document.querySelector('[data-name-input]').addEventListener('input', function () {
    document.querySelector('[data-preset-name]').textContent = this.value;
    markDirty(); scheduleSave();
  });

  /* ---------- sửa dữ liệu gốc + sound ---------- */
  pop.querySelector('[data-el-save]').addEventListener('click', async function () {
    if (currentEl == null) return;
    var el = catalog[currentEl];
    var body = {
      name_vi: pop.querySelector('[data-el-name-vi]').value,
      name_en: pop.querySelector('[data-el-name-en]').value,
      mass: pop.querySelector('[data-el-mass]').value,
      category_id: pop.querySelector('[data-el-cat]').value || null,
      sort_order: pop.querySelector('[data-el-order]').value || 0,
    };
    try {
      var data = await api(URLS.element.replace('ELID', currentEl), 'PATCH', body);
      Object.assign(el, data.element);
      renderGrid(); markDirty();
      toast('Đã lưu thông tin gốc (áp dụng mọi phiên bản).', '✅');
    } catch (e) { toast('Lỗi: ' + e.message, '⚠️'); }
  });
  pop.querySelector('[data-el-sound]').addEventListener('change', async function () {
    if (currentEl == null || !this.files[0]) return;
    var fd = new FormData(); fd.append('sound', this.files[0]);
    try {
      var data = await api(URLS.elementSound.replace('ELID', currentEl), 'POST', fd);
      catalog[currentEl].sound_url = data.sound_url;
      pop.querySelector('[data-el-sound-state]').textContent = '🔊 Đã có file âm thanh';
      markDirty();
      toast('Đã tải âm thanh lên.', '🔊');
    } catch (e) { toast('Lỗi tải âm thanh: ' + e.message, '⚠️'); }
  });

  /* ---------- nhóm (legend) ---------- */
  function fillCatSelect(sel, current) {
    sel.innerHTML = '<option value="">— Không nhóm —</option>' + cats.map(function (c) {
      return '<option value="' + c.id + '"' + (String(c.id) === String(current) ? ' selected' : '') + '>' + c.name + '</option>';
    }).join('');
  }
  function renderLegend() {
    var box = document.querySelector('[data-legend]');
    box.innerHTML = cats.map(function (c) {
      return '<div class="pw-legend__item" data-cat="' + c.id + '">' +
        '<input type="color" class="pw-legend__swatch" data-cat-color value="' + toHex(c.color) + '" title="Màu ô">' +
        '<input type="text" class="pw-legend__name" data-cat-name value="' + escAttr(c.name) + '">' +
        '<button type="button" data-cat-del title="Xóa nhóm">🗑</button>' +
        '</div>';
    }).join('');
    box.querySelectorAll('[data-cat]').forEach(function (row) {
      var id = row.getAttribute('data-cat');
      row.querySelector('[data-cat-color]').addEventListener('change', function () { saveCat(id, row); });
      row.querySelector('[data-cat-name]').addEventListener('change', function () { saveCat(id, row); });
      row.querySelector('[data-cat-del]').addEventListener('click', function () { delCat(id); });
    });
    // Đồng bộ dropdown trong popover nếu đang mở
    if (currentEl != null) fillCatSelect(pop.querySelector('[data-el-cat]'), catalog[currentEl].category_id);
  }
  async function saveCat(id, row) {
    var color = row.querySelector('[data-cat-color]').value;
    var name = row.querySelector('[data-cat-name]').value;
    try {
      var data = await api(URLS.categoryUpdate.replace('CATID', id), 'PATCH', { name: name, color: color, deep_color: darken(color) });
      var idx = cats.findIndex(function (c) { return String(c.id) === String(id); });
      if (idx >= 0) cats[idx] = data.category;
      renderGrid(); markDirty();
    } catch (e) { toast('Lỗi lưu nhóm: ' + e.message, '⚠️'); }
  }
  async function delCat(id) {
    if (!confirm('Xóa nhóm này? Các nguyên tố thuộc nhóm sẽ về "không nhóm".')) return;
    try {
      await api(URLS.categoryDestroy.replace('CATID', id), 'DELETE');
      cats = cats.filter(function (c) { return String(c.id) !== String(id); });
      renderLegend(); renderGrid(); markDirty();
    } catch (e) { toast('Lỗi xóa nhóm: ' + e.message, '⚠️'); }
  }
  document.querySelector('[data-cat-add]').addEventListener('click', async function () {
    try {
      var data = await api(URLS.categoryStore, 'POST', { name: 'Nhóm mới', color: '#8888aa', deep_color: '#55557a' });
      cats.push(data.category);
      renderLegend(); populateBulkGroups(); markDirty();
    } catch (e) { toast('Lỗi thêm nhóm: ' + e.message, '⚠️'); }
  });

  /* ---------- chế độ xem ---------- */
  document.querySelectorAll('[data-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-view]').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      mode = btn.getAttribute('data-view');
      if (mode !== 'edit') { closePopover(); multi = false; syncMultiUi(); }
      renderGrid();
    });
  });

  /* ---------- chọn nhiều + thao tác hàng loạt ---------- */
  document.querySelector('[data-toggle-multi]').addEventListener('click', function () {
    multi = !multi; if (!multi) selection = {};
    syncMultiUi(); renderGrid();
  });
  function syncMultiUi() {
    document.querySelector('[data-bulk]').hidden = !multi;
    document.querySelector('[data-toggle-multi]').classList.toggle('btn-primary', multi);
    updateSelCount();
    if (multi) closePopover();
  }
  function updateSelCount() {
    document.querySelector('[data-selcount]').textContent = Object.keys(selection).length;
  }
  document.querySelector('[data-bulk-clear]').addEventListener('click', function () {
    selection = {}; renderGrid(); updateSelCount();
  });
  document.querySelector('[data-bulk-group]').addEventListener('change', function () {
    var catId = this.value; if (!catId) return;
    boot.elements.forEach(function (e) { if (String(e.category_id) === String(catId)) selection[e.id] = true; });
    this.value = '';
    renderGrid(); updateSelCount();
  });
  document.querySelectorAll('[data-bulk]').forEach(function (btn) {
    var op = btn.getAttribute('data-bulk');
    if (op === '' || op === null) return;
    btn.addEventListener('click', function () {
      var ids = Object.keys(selection); if (!ids.length) return;
      ids.forEach(function (id) {
        if (!cfg[id]) return;
        if (op === 'lit-on') cfg[id].is_lit = true;
        else if (op === 'lit-off') cfg[id].is_lit = false;
        else if (op === 'pro-on') cfg[id].requires_pro = true;
        else if (op === 'pro-off') cfg[id].requires_pro = false;
        else if (op === 'show') cfg[id].is_visible = true;
        else if (op === 'hide') cfg[id].is_visible = false;
      });
      renderGrid(); markDirty(); scheduleSave();
    });
  });
  function populateBulkGroups() {
    var sel = document.querySelector('[data-bulk-group]');
    sel.innerHTML = '<option value="">Chọn theo nhóm…</option>' + cats.map(function (c) {
      return '<option value="' + c.id + '">' + c.name + '</option>';
    }).join('');
  }

  /* ---------- tiện ích màu ---------- */
  function toHex(v) { return /^#[0-9a-f]{6}$/i.test(v) ? v : '#8888aa'; }
  function darken(hex) {
    var h = toHex(hex).slice(1);
    var r = Math.round(parseInt(h.slice(0, 2), 16) * 0.72);
    var g = Math.round(parseInt(h.slice(2, 4), 16) * 0.72);
    var b = Math.round(parseInt(h.slice(4, 6), 16) * 0.72);
    return '#' + [r, g, b].map(function (n) { return ('0' + n.toString(16)).slice(-2); }).join('');
  }
  function escAttr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;'); }

  document.addEventListener('click', function (e) {
    if (pop.hidden) return;
    if (pop.contains(e.target) || (e.target.closest && e.target.closest('.pg-cell'))) return;
    closePopover();
  });

  /* ---------- khởi tạo ---------- */
  renderLegend();
  populateBulkGroups();
  renderGrid();
})();
