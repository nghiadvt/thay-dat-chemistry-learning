/**
 * Shared admin list pages — filter panel, search clear, row action menus.
 */
(function () {
  function closeAllActionMenus(except) {
    document.querySelectorAll('[data-row-action-menu]').forEach((wrap) => {
      if (except && wrap === except) return;
      const panel = wrap.querySelector('[data-row-action-panel]');
      const trigger = wrap.querySelector('[data-row-action-trigger]');
      if (panel) panel.hidden = true;
      trigger?.setAttribute('aria-expanded', 'false');
    });
  }

  function submitHiddenForm(url, method) {
    const form = document.createElement('form');
    form.method = method === 'GET' ? 'GET' : 'POST';
    form.action = url;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token && method !== 'GET') {
      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '_token';
      csrf.value = token;
      form.appendChild(csrf);
    }
    if (method && method !== 'GET' && method !== 'POST') {
      const spoof = document.createElement('input');
      spoof.type = 'hidden';
      spoof.name = '_method';
      spoof.value = method;
      form.appendChild(spoof);
    }
    document.body.appendChild(form);
    form.submit();
  }

  function handleRowAction(menu, action, btn) {
    const href = btn?.dataset?.href || menu.dataset[`${action.replace(/-/g, '')}Url`] || menu.dataset[`${action}Url`];

    if (action === 'link' || action === 'external') {
      const url = btn?.dataset?.href || menu.dataset.joinUrl || menu.dataset.href;
      if (url) window.open(url, '_blank', 'noopener');
      return;
    }

    if (action === 'navigate' || action === 'edit' || action === 'host' || action === 'report' || action === 'show' || action === 'detail') {
      const url = href
        || menu.dataset.editUrl
        || menu.dataset.hostUrl
        || menu.dataset.reportUrl
        || menu.dataset.showUrl
        || menu.dataset.detailUrl;
      if (!url) return;
      const method = btn?.dataset?.method || 'GET';
      const confirmMsg = btn?.dataset?.confirm;
      if (method === 'GET') {
        location.href = url;
        return;
      }
      if (confirmMsg && !confirm(confirmMsg)) return;
      submitHiddenForm(url, method);
      return;
    }

    if (action === 'delete') {
      const url = btn?.dataset?.href || menu.dataset.deleteUrl;
      const label = menu.dataset.itemLabel || menu.dataset.sessionName || 'mục này';
      const message = btn?.dataset?.confirm || `Xóa «${label}»? Hành động không thể hoàn tác.`;
      if (!url || !confirm(message)) return;
      submitHiddenForm(url, btn?.dataset?.method || 'DELETE');
      return;
    }

    if (typeof window.AdminListPage?.onAction === 'function') {
      window.AdminListPage.onAction(menu, action, btn);
    }
  }

  function initFilterPanels() {
    document.querySelectorAll('[data-filter-panel-toggle]').forEach((toggle) => {
      const panelId = toggle.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : toggle.closest('.admin-list-card, .sessions-list-card')?.querySelector('[data-filter-panel]');
      if (!panel) return;

      toggle.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  function initSearchClear() {
    document.querySelectorAll('[data-admin-list-search]').forEach((form) => {
      const input = form.querySelector('input[type="search"]');
      const clearBtn = form.querySelector('[data-search-clear]');
      if (!input || !clearBtn) return;

      function sync() {
        clearBtn.hidden = !input.value.trim();
      }

      input.addEventListener('input', sync);
      sync();

      clearBtn.addEventListener('click', () => {
        input.value = '';
        sync();
        form.requestSubmit();
      });
    });
  }

  function initRowActionMenus() {
    document.querySelectorAll('[data-row-action-menu]').forEach((menu) => {
      const trigger = menu.querySelector('[data-row-action-trigger]');
      const panel = menu.querySelector('[data-row-action-panel]');
      if (!trigger || !panel) return;

      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = panel.hidden;
        closeAllActionMenus(menu);
        panel.hidden = !willOpen;
        trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });

      panel.querySelectorAll('[data-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const action = btn.dataset.action;
          panel.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
          if (action) handleRowAction(menu, action, btn);
        });
      });
    });

    document.addEventListener('click', () => closeAllActionMenus());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllActionMenus();
    });
  }

  function initBulkSelectAll(selectAllId, rowCheckClass) {
    const selectAll = document.getElementById(selectAllId);
    if (!selectAll) return;

    const rowChecks = () => [...document.querySelectorAll(`.${rowCheckClass}:not(:disabled)`)];

    selectAll.addEventListener('change', () => {
      const checked = selectAll.checked;
      rowChecks().forEach((cb) => {
        cb.checked = checked;
      });
      selectAll.dispatchEvent(new CustomEvent('bulk-select-changed', { bubbles: true }));
    });

    document.querySelectorAll(`.${rowCheckClass}`).forEach((cb) => {
      cb.addEventListener('change', () => {
        const checks = rowChecks();
        selectAll.checked = checks.length > 0 && checks.every((c) => c.checked);
        selectAll.indeterminate = checks.some((c) => c.checked) && !selectAll.checked;
        selectAll.dispatchEvent(new CustomEvent('bulk-select-changed', { bubbles: true }));
      });
    });
  }

  function init() {
    initFilterPanels();
    initSearchClear();
    initRowActionMenus();
  }

  window.AdminListPage = {
    init,
    initBulkSelectAll,
    closeAllActionMenus,
    submitHiddenForm,
    onAction: null,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
