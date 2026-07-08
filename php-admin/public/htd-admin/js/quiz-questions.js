(function () {
  'use strict';

  const card = document.getElementById('quizQuestionsCard');
  if (!card) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const quizId = card.dataset.quizId;
  const fromBankUrl = card.dataset.fromBankUrl;
  const reorderUrl = card.dataset.reorderUrl;
  const bulkUrl = card.dataset.bulkUrl;
  const bankSearchUrl = card.dataset.bankSearchUrl;

  /* ─── Bulk selection ─── */
  const bulkBar = document.getElementById('qqBulkBar');
  const bulkCountEl = document.getElementById('qqBulkCount');
  const selectAll = document.getElementById('qqSelectAll');
  const tbody = document.getElementById('qqQuestionsBody');

  function getRowChecks() {
    return Array.from(document.querySelectorAll('.qq-row-check'));
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

    bulkBar.querySelectorAll('[data-bulk-action]').forEach((btn) => {
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
    if (e.target.classList.contains('qq-row-check')) updateBulkBar();
  });

  /* ─── Drag and drop reorder ─── */
  let dragRow = null;

  function getOrderFromDom() {
    return Array.from(document.querySelectorAll('.qq-question-row'))
      .map((row) => parseInt(row.dataset.questionId, 10));
  }

  function updateSortCells() {
    document.querySelectorAll('.qq-question-row').forEach((row, i) => {
      const cell = row.querySelector('.qq-sort-cell');
      if (cell) cell.textContent = String(i);
    });
  }

  async function saveOrder() {
    const order = getOrderFromDom();
    if (!order.length) return;
    try {
      const res = await fetch(reorderUrl, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify({ order }),
      });
      if (!res.ok) throw new Error('reorder failed');
      updateSortCells();
    } catch {
      window.location.reload();
    }
  }

  if (tbody) {
    tbody.addEventListener('dragstart', (e) => {
      const row = e.target.closest('.qq-question-row');
      if (!row || e.target.closest('input, button, a, form, label')) {
        e.preventDefault();
        return;
      }
      dragRow = row;
      row.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });

    tbody.addEventListener('dragend', () => {
      if (dragRow) dragRow.classList.remove('dragging');
      document.querySelectorAll('.qq-question-row.drag-over').forEach((r) => r.classList.remove('drag-over'));
      dragRow = null;
    });

    tbody.addEventListener('dragover', (e) => {
      e.preventDefault();
      const row = e.target.closest('.qq-question-row');
      if (!row || row === dragRow) return;
      document.querySelectorAll('.qq-question-row.drag-over').forEach((r) => {
        if (r !== row) r.classList.remove('drag-over');
      });
      row.classList.add('drag-over');
      const rect = row.getBoundingClientRect();
      const after = e.clientY > rect.top + rect.height / 2;
      if (after) row.after(dragRow);
      else row.before(dragRow);
    });

    tbody.addEventListener('drop', (e) => {
      e.preventDefault();
      document.querySelectorAll('.qq-question-row.drag-over').forEach((r) => r.classList.remove('drag-over'));
      saveOrder();
    });
  }

  /* ─── Bank modal ─── */
  const bankModal = document.getElementById('bankModal');
  const bankList = document.getElementById('bankList');
  const bankSelected = document.getElementById('bankSelected');
  const bankSelectedCount = document.getElementById('bankSelectedCount');
  const btnAddFromBank = document.getElementById('btnAddFromBank');
  const bankFilterType = document.getElementById('bankFilterType');
  const bankFilterQ = document.getElementById('bankFilterQ');

  const selectedBank = new Map();

  function openBankModal() {
    if (!bankModal) return;
    bankModal.hidden = false;
    bankModal.setAttribute('aria-hidden', 'false');
    selectedBank.clear();
    renderSelectedChips();
    loadBankList();
  }

  function closeBankModal() {
    if (!bankModal) return;
    bankModal.hidden = true;
    bankModal.setAttribute('aria-hidden', 'true');
  }

  document.getElementById('btnOpenBankModal')?.addEventListener('click', openBankModal);
  document.getElementById('btnOpenBankModalEmpty')?.addEventListener('click', openBankModal);
  bankModal?.querySelectorAll('[data-close-bank-modal]').forEach((el) => {
    el.addEventListener('click', closeBankModal);
  });

  let bankSearchTimer = null;
  function scheduleBankSearch() {
    clearTimeout(bankSearchTimer);
    bankSearchTimer = setTimeout(loadBankList, 300);
  }

  bankModal?.addEventListener('tagselect:change', loadBankList);
  bankFilterType?.addEventListener('change', loadBankList);
  bankFilterQ?.addEventListener('input', scheduleBankSearch);

  function appendBankTagFilterParams(params) {
    const wrap = bankModal?.querySelector('#bankFilterTagSelect');
    if (!wrap || wrap.dataset.mode !== 'filter-multi') {
      const tagId = bankModal?.querySelector('[name="tag_id"]')?.value ?? '';
      if (tagId) params.set('tag_id', tagId);
      return;
    }

    const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
    if (tagNone) params.set('tag_none', '1');

    const tagMatch = wrap.querySelector('[name="tag_match"]')?.value;
    if (tagMatch === 'or' || tagMatch === 'and') {
      params.set('tag_match', tagMatch);
    }

    let ids = [];
    try {
      ids = JSON.parse(wrap.dataset.selected || '[]');
    } catch {
      ids = [];
    }
    ids.forEach((id) => params.append('tag_ids[]', String(id)));
  }

  async function loadBankList() {
    if (!bankList) return;
    bankList.innerHTML = '<p class="qq-bank-loading">Đang tải...</p>';

    const params = new URLSearchParams({ quiz_id: quizId });
    appendBankTagFilterParams(params);
    if (bankFilterType?.value) params.set('answer_type', bankFilterType.value);
    if (bankFilterQ?.value.trim()) params.set('q', bankFilterQ.value.trim());

    try {
      const res = await fetch(`${bankSearchUrl}?${params}`, {
        headers: { Accept: 'application/json' },
      });
      const json = await res.json();
      const items = json.data || [];
      if (!items.length) {
        bankList.innerHTML = '<p class="qq-bank-empty">Không tìm thấy câu hỏi.</p>';
        return;
      }
      bankList.innerHTML = '';
      items.forEach((item) => {
        const el = document.createElement('label');
        el.className = 'qq-bank-item';
        if (item.already_in_quiz) {
          el.classList.add('is-disabled');
        } else if (selectedBank.has(item.id)) {
          el.classList.add('is-selected');
        }

        const checked = selectedBank.has(item.id);
        const disabled = item.already_in_quiz ? 'disabled' : '';
        const tags = (item.tags || []).map((t) => {
          const name = typeof t === 'string' ? t : t.name;
          const color = typeof t === 'object' && t.color ? t.color : '#e5e7eb';
          const text = colorLuminance(color) > 0.62 ? '#1f2937' : '#ffffff';
          return `<span class="qq-bank-badge" style="background:${color};color:${text}">${escapeHtml(name)}</span>`;
        }).join('');
        const warn = item.already_in_quiz ? '<span class="qq-bank-badge qq-bank-badge--warn">Đã có trong quiz</span>' : '';

        el.innerHTML = `
          <input type="checkbox" value="${item.id}" ${checked ? 'checked' : ''} ${disabled}
            data-preview="${escapeAttr(item.content_preview)}"
            data-type="${escapeAttr(item.answer_type_label)}">
          <div class="qq-bank-item-body">
            <div class="qq-bank-item-meta">
              <span class="qq-bank-badge">${escapeHtml(item.answer_type_label)}</span>
              <span class="qq-bank-badge">${item.time_limit_seconds}s · ${item.points}đ</span>
              ${tags}${warn}
            </div>
            <div class="qq-bank-item-preview">${escapeHtml(item.content_preview)}</div>
          </div>`;

        const cb = el.querySelector('input');
        cb?.addEventListener('change', () => {
          if (cb.disabled) return;
          if (cb.checked) {
            selectedBank.set(item.id, {
              id: item.id,
              preview: item.content_preview,
              type: item.answer_type_label,
            });
            el.classList.add('is-selected');
          } else {
            selectedBank.delete(item.id);
            el.classList.remove('is-selected');
          }
          renderSelectedChips();
        });

        bankList.appendChild(el);
      });
    } catch {
      bankList.innerHTML = '<p class="qq-bank-empty">Lỗi tải danh sách.</p>';
    }
  }

  function renderSelectedChips() {
    if (!bankSelected || !bankSelectedCount || !btnAddFromBank) return;
    bankSelectedCount.textContent = String(selectedBank.size);
    btnAddFromBank.disabled = selectedBank.size === 0;
    bankSelected.innerHTML = '';
    selectedBank.forEach((item) => {
      const chip = document.createElement('div');
      chip.className = 'qq-selected-chip';
      chip.innerHTML = `<span title="${escapeAttr(item.preview)}">${escapeHtml(truncate(item.preview, 40))}</span>
        <button type="button" aria-label="Bỏ chọn">×</button>`;
      chip.querySelector('button')?.addEventListener('click', () => {
        selectedBank.delete(item.id);
        const cb = bankList?.querySelector(`input[value="${item.id}"]`);
        if (cb) {
          cb.checked = false;
          cb.closest('.qq-bank-item')?.classList.remove('is-selected');
        }
        renderSelectedChips();
      });
      bankSelected.appendChild(chip);
    });
  }

  btnAddFromBank?.addEventListener('click', async () => {
    const ids = Array.from(selectedBank.keys());
    if (!ids.length) return;
    btnAddFromBank.disabled = true;
    btnAddFromBank.textContent = 'Đang thêm...';
    try {
      const res = await fetch(fromBankUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify({ bank_ids: ids }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.error || 'failed');
      window.location.reload();
    } catch {
      btnAddFromBank.disabled = false;
      btnAddFromBank.textContent = 'Thêm vào quiz';
      alert('Không thể thêm câu hỏi. Vui lòng thử lại.');
    }
  });

  /* ─── Bulk modal ─── */
  const bulkModal = document.getElementById('bulkModal');
  const bulkModalTitle = document.getElementById('bulkModalTitle');
  const bulkFieldTime = document.getElementById('bulkFieldTime');
  const bulkFieldPoints = document.getElementById('bulkFieldPoints');
  const bulkFieldTags = document.getElementById('bulkFieldTags');
  const bulkConfirmText = document.getElementById('bulkConfirmText');
  const btnBulkConfirm = document.getElementById('btnBulkConfirm');
  let bulkAction = null;

  function setBulkFieldVisible(el, visible) {
    if (!el) return;
    el.hidden = !visible;
    if (visible) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  function openBulkModal(action) {
    bulkAction = action;
    if (!bulkModal) return;

    setBulkFieldVisible(bulkFieldTime, action === 'time');
    setBulkFieldVisible(bulkFieldPoints, action === 'points');
    setBulkFieldVisible(bulkFieldTags, action === 'tags');
    setBulkFieldVisible(bulkConfirmText, ['enable', 'disable', 'delete', 'tags'].includes(action));

    const count = getSelectedIds().length;
    if (action === 'tags') {
      bulkModalTitle.textContent = `Đổi chủ đề (${count} câu)`;
      if (bulkConfirmText) bulkConfirmText.textContent = `Áp dụng chủ đề mới cho ${count} câu hỏi đã chọn?`;
    } else if (action === 'time') bulkModalTitle.textContent = `Đổi thời gian (${count} câu)`;
    else if (action === 'points') bulkModalTitle.textContent = `Đổi điểm (${count} câu)`;
    else if (action === 'enable') {
      bulkModalTitle.textContent = 'Bật câu hỏi';
      if (bulkConfirmText) bulkConfirmText.textContent = `Bật ${count} câu hỏi đã chọn?`;
    } else if (action === 'disable') {
      bulkModalTitle.textContent = 'Tắt câu hỏi';
      if (bulkConfirmText) bulkConfirmText.textContent = `Tắt ${count} câu hỏi đã chọn?`;
    } else if (action === 'delete') {
      bulkModalTitle.textContent = 'Xóa câu hỏi';
      if (bulkConfirmText) bulkConfirmText.textContent = `Xóa vĩnh viễn ${count} câu hỏi đã chọn?`;
    }

    bulkModal.hidden = false;
    bulkModal.setAttribute('aria-hidden', 'false');
  }

  function closeBulkModal() {
    if (!bulkModal) return;
    bulkModal.hidden = true;
    bulkModal.setAttribute('aria-hidden', 'true');
    bulkAction = null;
  }

  bulkModal?.querySelectorAll('[data-close-bulk-modal]').forEach((el) => {
    el.addEventListener('click', closeBulkModal);
  });

  bulkModal?.querySelector('.qq-modal-dialog')?.addEventListener('click', (e) => {
    e.stopPropagation();
  });

  bulkBar?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-bulk-action]');
    if (!btn || btn.disabled) return;
    const ids = getSelectedIds();
    if (!ids.length) return;
    openBulkModal(btn.dataset.bulkAction);
  });

  function parseApiError(json, fallback) {
    if (json?.error) return json.error;
    if (json?.message) return json.message;
    const first = json?.errors && Object.values(json.errors).flat()[0];
    return first || fallback;
  }

  btnBulkConfirm?.addEventListener('click', async () => {
    const ids = getSelectedIds();
    if (!ids.length || !bulkAction) return;

    const payload = { question_ids: ids };

    if (bulkAction === 'time') {
      const raw = document.getElementById('bulkTimeInput')?.value;
      const seconds = parseInt(raw, 10);
      if (!Number.isFinite(seconds) || seconds < 5 || seconds > 300) {
        alert('Thời gian phải từ 5 đến 300 giây.');
        return;
      }
      payload.time_limit_seconds = seconds;
    } else if (bulkAction === 'points') {
      const raw = document.getElementById('bulkPointsInput')?.value;
      const pts = parseInt(raw, 10);
      if (!Number.isFinite(pts) || pts < 1 || pts > 100) {
        alert('Điểm phải từ 1 đến 100.');
        return;
      }
      payload.points = pts;
    } else if (bulkAction === 'enable') {
      payload.is_active = true;
    } else if (bulkAction === 'disable') {
      payload.is_active = false;
    } else if (bulkAction === 'delete') {
      payload.action = 'delete';
    } else if (bulkAction === 'tags') {
      const selection = window.QuestionTagsCell?.getBulkTagSelection(bulkModal) || { tag_ids: [] };
      payload.tag_ids = selection.tag_ids;
    }

    btnBulkConfirm.disabled = true;
    try {
      const res = await fetch(bulkUrl, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        throw new Error(parseApiError(json, 'Không thể cập nhật.'));
      }
      window.location.reload();
    } catch (err) {
      btnBulkConfirm.disabled = false;
      alert(err.message || 'Không thể cập nhật. Vui lòng thử lại.');
    }
  });

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
  }

  function truncate(str, len) {
    const s = String(str);
    return s.length <= len ? s : `${s.slice(0, len)}…`;
  }

  function colorLuminance(hex) {
    const h = String(hex).replace('#', '');
    if (h.length !== 6) return 0;
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  }
})();
