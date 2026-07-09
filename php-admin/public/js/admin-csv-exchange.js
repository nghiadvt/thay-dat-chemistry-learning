/**
 * Admin list CSV export / import / template download.
 */
(function () {
  function parseJson(raw, fallback) {
    try {
      return JSON.parse(raw || '');
    } catch {
      return fallback;
    }
  }

  function getDefaultCols(tableId) {
    const picker = document.querySelector(`[data-table-column-picker][data-table-target="${tableId}"]`);
    if (!picker) return [];
    const checkboxes = [...picker.querySelectorAll('input[type="checkbox"][data-col-toggle]')];
    try {
      const parsed = JSON.parse(picker.dataset.defaultCols || '[]');
      if (Array.isArray(parsed) && parsed.length) return parsed;
    } catch {
      /* fall through */
    }
    return checkboxes.map((cb) => cb.value);
  }

  function getVisibleCols(tableId) {
    if (window.AdminDataTable?.getVisibleCols) {
      return window.AdminDataTable.getVisibleCols(tableId, getDefaultCols(tableId));
    }
    return getDefaultCols(tableId);
  }

  function buildUrl(base, params) {
    const url = new URL(base, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      if (value == null || value === '') return;
      if (Array.isArray(value)) {
        value.forEach((item) => url.searchParams.append(`${key}[]`, String(item)));
        return;
      }
      url.searchParams.set(key, String(value));
    });
    return url.toString();
  }

  function downloadUrl(url) {
    window.location.assign(url);
  }

  function initCsvExchange(root) {
    const tableId = root.dataset.tableId;
    const exportUrl = root.dataset.exportUrl;
    const templateUrl = root.dataset.templateUrl;
    const preserveQuery = parseJson(root.dataset.preserveQuery, {});

    const trigger = root.querySelector('[data-csv-exchange-toggle]');
    const panel = root.querySelector('[data-csv-exchange-panel]');
    const modal = root.querySelector('[data-csv-import-modal]');

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

    function closeModal() {
      if (!modal) return;
      modal.hidden = true;
      modal.setAttribute('aria-hidden', 'true');
    }

    function openModal() {
      closePanel();
      if (!modal) return;
      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      modal.querySelector('#csvFileInput')?.focus();
    }

    function actionParams() {
      const columns = getVisibleCols(tableId).filter((key) => key !== 'actions' && key !== 'select');
      return {
        ...preserveQuery,
        columns: columns.join(','),
      };
    }

    function handleAction(action) {
      if (action === 'export') {
        downloadUrl(buildUrl(exportUrl, actionParams()));
        closePanel();
        return;
      }
      if (action === 'template') {
        downloadUrl(buildUrl(templateUrl, { columns: actionParams().columns }));
        closePanel();
        return;
      }
      if (action === 'import') {
        openModal();
      }
    }

    trigger?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (panel?.hidden) openPanel();
      else closePanel();
    });

    panel?.addEventListener('click', (e) => {
      e.stopPropagation();
      const btn = e.target.closest('[data-csv-action]');
      if (!btn) return;
      handleAction(btn.dataset.csvAction);
    });

    root.querySelectorAll('[data-csv-action="template"]').forEach((btn) => {
      if (btn.closest('[data-csv-exchange-panel]')) return;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        handleAction('template');
      });
    });

    root.querySelectorAll('[data-csv-import-close]').forEach((el) => {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closePanel();
        closeModal();
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-csv-exchange]').forEach(initCsvExchange);
  }

  window.AdminCsvExchange = { init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
