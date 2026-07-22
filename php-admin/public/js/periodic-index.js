/**
 * Thumbnail + modal phóng to trên trang danh sách phiên bản.
 * Luôn render mode «normal» = đúng thứ HS tài khoản THƯỜNG thấy
 * (ẩn ô hidden, khoá ô Pro) trên khung 118 ô như màn học sinh.
 */
(function () {
  var boot = window.__PERIODIC_INDEX__;
  if (!boot || !window.PeriodicGrid) return;

  var catalog = boot.catalog || [];
  var categories = boot.categories || [];

  function cellsFor(presetId) {
    var state = boot.states[presetId] || {};
    return catalog.map(function (c) {
      var s = state[c.id] || { lit: true, vis: true, pro: false };
      return {
        id: c.id, z: c.z, symbol: c.sym, name_vi: c.name_vi || '', mass: c.mass,
        group: c.g, period: c.p, cat: c.cat,
        lit: s.lit, vis: s.vis, pro: s.pro,
      };
    });
  }

  function fitThumb(container, grid) {
    var avail = container.clientWidth - 16;
    var natural = grid.scrollWidth || 247;
    var scale = Math.min(1, avail / natural);
    grid.style.transform = 'scale(' + scale + ')';
  }

  /* ---- Modal phóng to ---- */
  var modal = document.getElementById('periodicThumbModal');
  var modalTitle = document.getElementById('periodicThumbModalTitle');
  var modalStage = document.getElementById('periodicThumbModalStage');
  var openPresetId = null;

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('periodic-thumb-modal-open');
    if (modalStage) modalStage.innerHTML = '';
    openPresetId = null;
  }

  function openModal(presetId, title) {
    if (!modal || !modalStage) return;
    openPresetId = presetId;
    if (modalTitle) {
      modalTitle.textContent = (title || 'Phiên bản') + ' · như học sinh thường';
    }
    var grid = document.createElement('div');
    modalStage.innerHTML = '';
    modalStage.appendChild(grid);
    PeriodicGrid.render(grid, {
      elements: cellsFor(presetId),
      categories: categories,
      mode: 'normal',
      showLegend: true,
    });
    modal.hidden = false;
    document.body.classList.add('periodic-thumb-modal-open');
  }

  if (modal) {
    modal.querySelectorAll('[data-periodic-modal-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && openPresetId != null) closeModal();
    });
  }

  /* ---- Thumbnails trên card ---- */
  document.querySelectorAll('[data-thumb]').forEach(function (box) {
    var id = box.getAttribute('data-thumb');
    var card = box.closest('.preset-card');
    var titleEl = card ? card.querySelector('.preset-card__title') : null;
    var title = titleEl ? titleEl.textContent.trim() : '';

    var grid = document.createElement('div');
    box.appendChild(grid);
    PeriodicGrid.render(grid, {
      elements: cellsFor(id),
      categories: categories,
      mode: 'normal',
      compact: true,
      showLegend: false,
    });
    fitThumb(box, grid);
    window.addEventListener('resize', function () { fitThumb(box, grid); });

    box.setAttribute('role', 'button');
    box.setAttribute('tabindex', '0');
    box.setAttribute('aria-label', 'Phóng to xem bảng như học sinh thường');
    box.title = 'Bấm để phóng to · như học sinh thường';

    function onOpen(e) {
      e.preventDefault();
      e.stopPropagation();
      openModal(id, title);
    }
    box.addEventListener('click', onOpen);
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') onOpen(e);
    });
  });
})();
