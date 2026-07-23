(function () {
  'use strict';

  const card = document.getElementById('practiceQuestionsCard');
  if (!card) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function notify(message, type = 'error') {
    if (window.AdminToast) AdminToast.show(message, type);
    else alert(message);
  }

  const attachUrl = card.dataset.attachUrl;
  const reorderUrl = card.dataset.reorderUrl;
  const bankSearchUrl = card.dataset.bankSearchUrl;
  let attachedIds = [];
  try {
    attachedIds = JSON.parse(card.dataset.attachedIds || '[]').map((id) => parseInt(id, 10));
  } catch {
    attachedIds = [];
  }

  /* ─── Drag and drop reorder ─── */
  const tbody = document.getElementById('practiceQuestionsBody');
  let dragRow = null;

  function getOrderFromDom() {
    return Array.from(document.querySelectorAll('#practiceQuestionsBody .qq-question-row'))
      .map((row) => parseInt(row.dataset.questionId, 10));
  }

  function updateSortCells() {
    document.querySelectorAll('#practiceQuestionsBody .qq-question-row').forEach((row, i) => {
      const cell = row.querySelector('.qq-sort-cell');
      if (cell) cell.textContent = String(i + 1);
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
      document.querySelectorAll('#practiceQuestionsBody .qq-question-row.drag-over').forEach((r) => r.classList.remove('drag-over'));
      dragRow = null;
    });

    tbody.addEventListener('dragover', (e) => {
      e.preventDefault();
      const row = e.target.closest('.qq-question-row');
      if (!row || row === dragRow) return;
      document.querySelectorAll('#practiceQuestionsBody .qq-question-row.drag-over').forEach((r) => {
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
      document.querySelectorAll('#practiceQuestionsBody .qq-question-row.drag-over').forEach((r) => r.classList.remove('drag-over'));
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
    if (!wrap || wrap.dataset.mode !== 'filter-multi') return;

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

    const params = new URLSearchParams();
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
        const alreadyAdded = attachedIds.includes(item.id);
        const el = document.createElement('label');
        el.className = 'qq-bank-item';
        if (alreadyAdded) {
          el.classList.add('is-disabled');
        } else if (selectedBank.has(item.id)) {
          el.classList.add('is-selected');
        }

        const checked = selectedBank.has(item.id);
        const disabled = alreadyAdded ? 'disabled' : '';
        const tags = (item.tags || []).map((t) => {
          const name = typeof t === 'string' ? t : t.name;
          const color = typeof t === 'object' && t.color ? t.color : '#e5e7eb';
          const text = colorLuminance(color) > 0.62 ? '#1f2937' : '#ffffff';
          return `<span class="qq-bank-badge" style="background:${color};color:${text}">${escapeHtml(name)}</span>`;
        }).join('');
        const warn = alreadyAdded ? '<span class="qq-bank-badge qq-bank-badge--warn">Đã có trong bài</span>' : '';

        el.innerHTML = `
          <input type="checkbox" value="${item.id}" ${checked ? 'checked' : ''} ${disabled}>
          <div class="qq-bank-item-body">
            <div class="qq-bank-item-meta">
              <span class="qq-bank-badge">${escapeHtml(item.answer_type_label)}</span>
              ${tags}${warn}
            </div>
            <div class="qq-bank-item-preview">${escapeHtml(item.content_preview)}</div>
          </div>`;

        const cb = el.querySelector('input');
        cb?.addEventListener('change', () => {
          if (cb.disabled) return;
          if (cb.checked) {
            selectedBank.set(item.id, { id: item.id, preview: item.content_preview });
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
      const res = await fetch(attachUrl, {
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
      btnAddFromBank.textContent = 'Thêm vào bài';
      notify('Không thể thêm câu hỏi. Vui lòng thử lại.');
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
