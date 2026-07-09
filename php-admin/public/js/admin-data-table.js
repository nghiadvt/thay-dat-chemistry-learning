/**
 * Admin data tables — column visibility (localStorage) + column picker dropdown.
 */
(function () {
  const STORAGE_PREFIX = 'admin-table-cols:';

  function loadVisibleCols(tableId, defaultCols) {
    try {
      const raw = localStorage.getItem(STORAGE_PREFIX + tableId);
      if (!raw) return defaultCols.slice();
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed) && parsed.length) return parsed;
      return defaultCols.slice();
    } catch {
      return defaultCols.slice();
    }
  }

  function saveVisibleCols(tableId, cols) {
    localStorage.setItem(STORAGE_PREFIX + tableId, JSON.stringify(cols));
  }

  function parseDefaultCols(picker, checkboxes) {
    try {
      const parsed = JSON.parse(picker.dataset.defaultCols || '[]');
      if (Array.isArray(parsed) && parsed.length) return parsed;
    } catch {
      /* fall through */
    }
    return checkboxes.map((cb) => cb.value);
  }

  function findTargetTable(tableId) {
    return document.querySelector(`table[data-table-id="${tableId}"]`);
  }

  function applyColumnVisibility(table, visibleCols) {
    if (!table) return;
    const visible = new Set(visibleCols);
    table.querySelectorAll('[data-col]').forEach((cell) => {
      const key = cell.dataset.col;
      if (key === 'actions' || key === 'select') {
        cell.classList.remove('col-hidden');
        cell.hidden = false;
        return;
      }
      const show = visible.has(key);
      cell.classList.toggle('col-hidden', !show);
      cell.hidden = !show;
    });
    table.querySelectorAll('col[data-col]').forEach((col) => {
      const key = col.dataset.col;
      if (key === 'actions') {
        col.classList.remove('col-hidden');
        return;
      }
      col.classList.toggle('col-hidden', !visible.has(key));
    });
  }

  function initColumnPicker(picker) {
    const tableId = picker.dataset.tableTarget;
    if (!tableId) return;

    const table = findTargetTable(tableId);
    if (!table) return;

    const checkboxes = [...picker.querySelectorAll('input[type="checkbox"][data-col-toggle]')];
    const defaultCols = parseDefaultCols(picker, checkboxes);
    const visibleCols = loadVisibleCols(tableId, defaultCols);

    checkboxes.forEach((input) => {
      input.checked = visibleCols.includes(input.value);
    });

    function syncFromCheckboxes() {
      const next = checkboxes
        .filter((cb) => cb.checked)
        .map((cb) => cb.value);
      if (!next.length) {
        const fallback = checkboxes[0];
        if (fallback) fallback.checked = true;
        return;
      }
      saveVisibleCols(tableId, next);
      applyColumnVisibility(table, next);
    }

    checkboxes.forEach((input) => {
      input.addEventListener('change', syncFromCheckboxes);
      input.addEventListener('input', syncFromCheckboxes);
    });

    applyColumnVisibility(table, visibleCols);

    const trigger = picker.querySelector('[data-column-picker-toggle]');
    const panel = picker.querySelector('[data-column-picker-panel]');

    function closePanel() {
      if (!panel || !trigger) return;
      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    function openPanel() {
      if (!panel || !trigger) return;
      panel.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (panel?.hidden) openPanel();
      else closePanel();
    });

    panel?.addEventListener('click', (e) => {
      e.stopPropagation();
    });

    document.addEventListener('click', (e) => {
      if (!picker.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closePanel();
    });
  }

  function init() {
    document.querySelectorAll('[data-table-column-picker]').forEach(initColumnPicker);
  }

  function getVisibleCols(tableId, defaultCols) {
    return loadVisibleCols(tableId, defaultCols ?? []);
  }

  window.AdminDataTable = { init, applyColumnVisibility, findTargetTable, getVisibleCols, loadVisibleCols };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
