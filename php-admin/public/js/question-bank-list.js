(function () {
  'use strict';

  const card = document.getElementById('qbListCard');
  if (!card) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const bulkTagsUrl = card.dataset.bulkTagsUrl;

  const bulkBar = document.getElementById('qbBulkBar');
  const bulkCountEl = document.getElementById('qbBulkCount');
  const selectAll = document.getElementById('qbSelectAll');
  const bulkModal = document.getElementById('qbBulkModal');
  const bulkModalTitle = document.getElementById('qbBulkModalTitle');
  const bulkConfirmText = document.getElementById('qbBulkConfirmText');
  const btnBulkConfirm = document.getElementById('btnQbBulkConfirm');

  function getRowChecks() {
    return Array.from(document.querySelectorAll('.qb-row-check'));
  }

  function getSelectedIds() {
    return getRowChecks().filter((c) => c.checked).map((c) => parseInt(c.value, 10));
  }

  function updateBulkBar() {
    const ids = getSelectedIds();
    const hasSelection = ids.length > 0;
    if (!bulkBar) return;

    bulkBar.classList.toggle('qq-bulk-bar--active', hasSelection);
    bulkBar.classList.toggle('qq-bulk-bar--idle', !hasSelection);

    if (bulkCountEl) bulkCountEl.textContent = String(ids.length);

    bulkBar.querySelectorAll('[data-qb-bulk-action]').forEach((btn) => {
      btn.disabled = !hasSelection;
    });

    if (selectAll) {
      const checks = getRowChecks();
      selectAll.checked = checks.length > 0 && checks.every((c) => c.checked);
      selectAll.indeterminate = ids.length > 0 && ids.length < checks.length;
    }
  }

  updateBulkBar();

  selectAll?.addEventListener('change', () => {
    getRowChecks().forEach((c) => { c.checked = selectAll.checked; });
    updateBulkBar();
  });

  card.addEventListener('change', (e) => {
    if (e.target.classList.contains('qb-row-check')) updateBulkBar();
  });

  function openBulkModal() {
    const count = getSelectedIds().length;
    if (!bulkModal || !count) return;
    if (bulkModalTitle) bulkModalTitle.textContent = `Đổi chủ đề (${count} câu)`;
    if (bulkConfirmText) bulkConfirmText.textContent = `Áp dụng chủ đề mới cho ${count} câu hỏi đã chọn?`;
    bulkModal.hidden = false;
    bulkModal.setAttribute('aria-hidden', 'false');
  }

  function closeBulkModal() {
    if (!bulkModal) return;
    bulkModal.hidden = true;
    bulkModal.setAttribute('aria-hidden', 'true');
  }

  bulkModal?.querySelectorAll('[data-close-qb-bulk-modal]').forEach((el) => {
    el.addEventListener('click', closeBulkModal);
  });

  bulkBar?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-qb-bulk-action]');
    if (!btn || btn.disabled) return;
    if (btn.dataset.qbBulkAction === 'tags') openBulkModal();
  });

  btnBulkConfirm?.addEventListener('click', async () => {
    const ids = getSelectedIds();
    if (!ids.length) return;

    const selection = window.QuestionTagsCell?.getBulkTagSelection(bulkModal) || { tag_ids: [] };

    btnBulkConfirm.disabled = true;
    try {
      const res = await fetch(bulkTagsUrl, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          item_ids: ids,
          tag_ids: selection.tag_ids,
        }),
      });

      const json = await res.json().catch(() => ({}));
      if (!res.ok || !json.success) {
        throw new Error(json.error || json.message || 'Không thể cập nhật chủ đề.');
      }

      window.location.reload();
    } catch (err) {
      btnBulkConfirm.disabled = false;
      if (window.AdminToast) {
        AdminToast.show(err.message || 'Không thể cập nhật.', 'error');
      } else {
        alert(err.message || 'Không thể cập nhật.');
      }
    }
  });
})();
