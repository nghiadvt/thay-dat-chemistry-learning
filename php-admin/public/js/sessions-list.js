/**
 * Sessions list — delete modal, bulk delete, session-specific row actions.
 */
(function () {
  function initDeleteModal() {
    const modal = document.getElementById('sessionDeleteModal');
    const form = document.getElementById('sessionDeleteModalForm');
    const body = document.getElementById('sessionDeleteModalBody');
    const methodInput = document.getElementById('sessionDeleteModalMethod');
    const idsWrap = document.getElementById('sessionDeleteModalIds');
    const card = document.getElementById('sessionsListCard');
    const bulkUrl = card?.dataset.bulkDestroyUrl || '';
    if (!modal || !form || !body || !idsWrap) return;

    function closeDeleteModal() {
      modal.hidden = true;
    }

    modal.querySelectorAll('[data-close-delete-modal]').forEach((el) => {
      el.addEventListener('click', closeDeleteModal);
    });

    function openDeleteModal({ url, method, message, ids }) {
      form.action = url;
      if (methodInput) {
        methodInput.value = method;
        methodInput.disabled = method !== 'DELETE';
      }
      body.textContent = message;
      idsWrap.innerHTML = '';
      ids?.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = String(id);
        idsWrap.appendChild(input);
      });
      modal.hidden = false;
    }

    window.openSessionDeleteModal = function (menu) {
      const name = menu.dataset.sessionName || menu.dataset.sessionPin || 'phòng này';
      const url = menu.dataset.deleteUrl || '';
      if (!url) return;
      openDeleteModal({
        url,
        method: 'DELETE',
        message: `Xóa phòng «${name}»? Hành động này xóa vĩnh viễn phòng, kết quả và câu trả lời liên quan. Không thể hoàn tác.`,
        ids: [],
      });
    };

    window.openSessionsBulkDeleteModal = function (ids) {
      if (!bulkUrl || !ids.length) return;
      openDeleteModal({
        url: bulkUrl,
        method: 'POST',
        message: `Xóa vĩnh viễn ${ids.length} phòng đã chọn? Kết quả và câu trả lời liên quan cũng bị xóa. Phòng đang chơi sẽ được bỏ qua.`,
        ids,
      });
    };
  }

  function submitReplay(url) {
    const message = 'Chơi lại với cùng PIN? Kết quả lần trước vẫn lưu trong báo cáo.';
    const doSubmit = () => window.AdminListPage?.submitHiddenForm(url, 'POST');
    if (window.AdminConfirm) {
      AdminConfirm.show({ title: 'Chơi lại', message, confirmText: 'Chơi lại' }).then((ok) => { if (ok) doSubmit(); });
    } else if (confirm(message)) {
      doSubmit();
    }
  }

  function initBulkSelection() {
    const bulkBar = document.getElementById('sessionsBulkBar');
    const bulkCountEl = document.getElementById('sessionsBulkCount');
    const bulkDeleteBtn = document.querySelector('[data-sessions-bulk-delete]');

    function selectedIds() {
      return [...document.querySelectorAll('.sessions-row-check:not(:disabled)')]
        .filter((cb) => cb.checked)
        .map((cb) => cb.value);
    }

    function syncBulkBar() {
      const ids = selectedIds();
      const count = ids.length;
      if (bulkCountEl) bulkCountEl.textContent = String(count);
      bulkBar?.classList.toggle('sessions-bulk-bar--active', count > 0);
      bulkBar?.classList.toggle('sessions-bulk-bar--idle', count === 0);
      if (bulkDeleteBtn) bulkDeleteBtn.disabled = count === 0;
    }

    document.getElementById('sessionsSelectAll')?.addEventListener('change', (e) => {
      const checked = e.target.checked;
      document.querySelectorAll('.sessions-row-check:not(:disabled)').forEach((cb) => {
        cb.checked = checked;
      });
      syncBulkBar();
    });

    document.querySelectorAll('.sessions-row-check').forEach((cb) => {
      cb.addEventListener('change', syncBulkBar);
    });

    bulkDeleteBtn?.addEventListener('click', () => {
      const ids = selectedIds();
      if (ids.length) window.openSessionsBulkDeleteModal?.(ids);
    });

    syncBulkBar();
  }

  function initSessionActions() {
    window.AdminListPage = window.AdminListPage || {};
    const previous = window.AdminListPage.onAction;
    window.AdminListPage.onAction = function (menu, action) {
      if (action === 'replay') {
        submitReplay(menu.dataset.resetUrl);
        return;
      }
      if (action === 'delete') {
        window.openSessionDeleteModal?.(menu);
        return;
      }
      if (action === 'trial') {
        window.HTDQuizPreview?.openQuiz(
          menu.dataset.quizId,
          menu.dataset.quizName || 'Quiz',
          { trial: true },
        );
        return;
      }
      if (typeof previous === 'function') previous(menu, action);
    };
  }

  function init() {
    initDeleteModal();
    initBulkSelection();
    initSessionActions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
