(function () {
  'use strict';

  const PRESET_COLORS = window.TAG_PRESET_COLORS || [
    '#2D46D6', '#059669', '#DC2626', '#D97706', '#7C3AED', '#0891B2', '#DB2777',
  ];

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const tagsIndexUrl = document.body.dataset.tagsIndexUrl || '/admin/tags';
  const tagsStoreUrl = document.body.dataset.tagsStoreUrl || '/admin/tags';
  const tagsUpdateBaseUrl = document.body.dataset.tagsUpdateUrl || '/admin/tags';

  let tagCache = null;
  let selectedColor = PRESET_COLORS[0];
  let customColorActive = false;

  function contrastText(hex) {
    const h = hex.replace('#', '');
    if (h.length !== 6) return '#ffffff';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return lum > 0.62 ? '#1f2937' : '#ffffff';
  }

  async function fetchTags() {
    const res = await fetch(tagsIndexUrl, { headers: { Accept: 'application/json' } });
    const json = await res.json();
    tagCache = json.data || [];
    return tagCache;
  }

  function renderOptionRow(tag, mode = 'filter') {
    const row = document.createElement('div');
    row.className = 'tag-select-option-row';
    row.dataset.value = String(tag.id);
    row.dataset.label = tag.name;
    row.dataset.color = tag.color;

    const usesCheckbox = mode === 'multi' || mode === 'filter-multi';

    if (usesCheckbox) {
      const label = document.createElement('label');
      label.className = 'tag-select-option tag-select-option--check';
      label.innerHTML = `
        <input type="checkbox" class="tag-select-checkbox" value="${tag.id}">
        <span class="tag-select-dot" style="background:${tag.color}"></span>
        <span class="tag-select-option-label">${escapeHtml(tag.name)}</span>`;
      row.appendChild(label);
    } else {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tag-select-option';
      btn.innerHTML = `<span class="tag-select-dot" style="background:${tag.color}"></span><span class="tag-select-option-label">${escapeHtml(tag.name)}</span>`;
      row.appendChild(btn);
    }

    const menuWrap = document.createElement('div');
    menuWrap.className = 'tag-select-option-menu';
    menuWrap.innerHTML = `
      <button type="button" class="tag-select-kebab" aria-label="Thao tác chủ đề">⋮</button>
      <div class="tag-select-action-menu" hidden>
        <button type="button" data-edit-tag="${tag.id}">Sửa tên và màu</button>
      </div>`;

    row.appendChild(menuWrap);
    return row;
  }

  function rebuildOptionsList(wrap, tags) {
    const container = wrap.querySelector('[data-tag-options]');
    if (!container) return;
    const mode = wrap.dataset.mode || 'filter';
    container.innerHTML = '';
    tags.forEach((tag) => container.appendChild(renderOptionRow(tag, mode)));
  }

  function collectCheckboxIds(wrap) {
    return Array.from(wrap.querySelectorAll('.tag-select-checkbox:checked'))
      .map((cb) => parseInt(cb.value, 10))
      .filter((id) => id > 0);
  }

  function getTagMatch(wrap) {
    const input = wrap.querySelector('[name="tag_match"]');
    return input?.value === 'or' ? 'or' : 'and';
  }

  function setTagMatch(wrap, match) {
    const val = match === 'or' ? 'or' : 'and';
    const input = wrap.querySelector('[name="tag_match"]');
    if (input) input.value = val;
    wrap.dataset.tagMatch = val;
    wrap.querySelectorAll('[data-tag-match]').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.tagMatch === val);
    });
  }

  function tagMatchLabel(match) {
    return match === 'or' ? ' (HOẶC)' : ' (VÀ)';
  }

  function syncCheckboxStates(wrap, ids) {
    const idSet = new Set(ids.map(String));
    wrap.querySelectorAll('.tag-select-checkbox').forEach((cb) => {
      cb.checked = idSet.has(cb.value);
    });
  }

  function syncFilterMultiTopOptions(wrap, ids, tagNone) {
    wrap.querySelectorAll('.tag-select-dropdown > .tag-select-option').forEach((opt) => {
      const val = opt.dataset.value ?? '';
      if (val === '') {
        opt.classList.toggle('is-active', ids.length === 0 && !tagNone);
      } else if (val === 'none') {
        opt.classList.toggle('is-active', tagNone && ids.length === 0);
      }
    });
  }

  function getTriggerLabel(wrap) {
    const mode = wrap.dataset.mode;
    if (mode === 'filter') {
      const val = wrap.querySelector('input[type="hidden"]')?.value ?? '';
      if (val === '') return 'Tất cả chủ đề';
      if (val === 'none') return 'Chưa có chủ đề';
      const row = wrap.querySelector(`[data-tag-options] [data-value="${val}"]`);
      return row?.dataset.label || '—';
    }
    if (mode === 'filter-multi') {
      const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
      const ids = JSON.parse(wrap.dataset.selected || '[]');
      if (!ids.length && !tagNone) return 'Tất cả chủ đề';
      if (!ids.length && tagNone) return 'Chưa có chủ đề';
      if (ids.length === 1) {
        const row = wrap.querySelector(`[data-tag-options] [data-value="${ids[0]}"]`);
        const name = row?.dataset.label || '1 chủ đề';
        return tagNone ? `${name} (+ chưa có)` : name;
      }
      const matchSuffix = ids.length > 1 ? tagMatchLabel(getTagMatch(wrap)) : '';
      return tagNone ? `${ids.length} chủ đề${matchSuffix} (+ chưa có)` : `${ids.length} chủ đề${matchSuffix}`;
    }
    const ids = JSON.parse(wrap.dataset.selected || '[]');
    if (!ids.length) return 'Chọn chủ đề...';
    if (ids.length === 1) {
      const row = wrap.querySelector(`[data-tag-options] [data-value="${ids[0]}"]`);
      return row?.dataset.label || '1 chủ đề';
    }
    return `${ids.length} chủ đề`;
  }

  function getTriggerColor(wrap) {
    const mode = wrap.dataset.mode;
    let ids;
    if (mode === 'filter') {
      const val = wrap.querySelector('input[type="hidden"]')?.value ?? '';
      ids = val && val !== 'none' ? [val] : [];
    } else if (mode === 'filter-multi') {
      ids = JSON.parse(wrap.dataset.selected || '[]');
    } else {
      ids = JSON.parse(wrap.dataset.selected || '[]');
    }
    if (ids.length !== 1) return null;
    const row = wrap.querySelector(`[data-tag-options] [data-value="${ids[0]}"]`);
    return row?.dataset.color || null;
  }

  function updateTrigger(wrap) {
    const label = wrap.querySelector('[data-trigger-label]');
    if (label) label.textContent = getTriggerLabel(wrap);
    const dot = wrap.querySelector('[data-trigger-dot]');
    if (dot) {
      const color = getTriggerColor(wrap);
      dot.hidden = !color;
      if (color) dot.style.background = color;
    }
  }

  function syncActiveStates(wrap, filterValue, multiIds) {
    const mode = wrap.dataset.mode;
    wrap.querySelectorAll('.tag-select-option-row').forEach((row) => {
      const id = parseInt(row.dataset.value, 10);
      if (mode === 'filter') {
        row.classList.toggle('is-active', row.dataset.value === filterValue);
      } else {
        row.classList.toggle('is-active', multiIds.includes(id));
      }
    });
    if (mode === 'filter-multi') {
      const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
      syncFilterMultiTopOptions(wrap, multiIds, tagNone);
    } else {
      wrap.querySelectorAll('.tag-select-dropdown > .tag-select-option').forEach((opt) => {
        opt.classList.toggle('is-active', opt.dataset.value === filterValue);
      });
    }
  }

  function setFilterValue(wrap, value, { submit = false } = {}) {
    const input = wrap.querySelector('input[type="hidden"]');
    if (input) input.value = value;
    wrap.dataset.selected = value;
    updateTrigger(wrap);
    syncActiveStates(wrap, value, []);
    if (submit && wrap.dataset.autoSubmit === '1') {
      wrap.closest('form')?.submit();
    }
    wrap.dispatchEvent(new CustomEvent('tagselect:change', { bubbles: true, detail: { value, mode: 'filter' } }));
  }

  function setFilterMultiValue(wrap, ids, tagNone, { submit = false } = {}) {
    wrap.dataset.selected = JSON.stringify(ids);
    const noneInput = wrap.querySelector('[name="tag_none"]');
    if (noneInput) noneInput.value = tagNone ? '1' : '0';
    const container = wrap.querySelector('.tag-select-hidden-inputs');
    if (container) {
      container.innerHTML = ids.map((id) => `<input type="hidden" name="tag_ids[]" value="${id}">`).join('');
    }
    syncCheckboxStates(wrap, ids);
    syncActiveStates(wrap, '', ids);
    updateTrigger(wrap);
    if (submit && wrap.dataset.autoSubmit === '1') {
      wrap.closest('form')?.submit();
    }
    wrap.dispatchEvent(new CustomEvent('tagselect:change', {
      bubbles: true,
      detail: { ids, tagNone, tagMatch: getTagMatch(wrap), mode: 'filter-multi' },
    }));
  }

  function setMultiValue(wrap, ids) {
    wrap.dataset.selected = JSON.stringify(ids);
    const container = wrap.querySelector('.tag-select-hidden-inputs');
    if (container) {
      container.innerHTML = ids.map((id) => `<input type="hidden" name="tag_ids[]" value="${id}">`).join('');
    }
    syncCheckboxStates(wrap, ids);
    syncActiveStates(wrap, '', ids);
    updateTrigger(wrap);
  }

  function closeAllActionMenus(except) {
    document.querySelectorAll('.tag-select-action-menu').forEach((menu) => {
      if (menu === except) return;
      menu.hidden = true;
    });
  }

  function closeAllDropdowns(except) {
    document.querySelectorAll('[data-tag-select]').forEach((wrap) => {
      if (wrap === except) return;
      const dd = wrap.querySelector('.tag-select-dropdown');
      const trigger = wrap.querySelector('.tag-select-trigger');
      if (dd) dd.hidden = true;
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
    closeAllActionMenus(null);
  }

  function initTagSelect(wrap) {
    const trigger = wrap.querySelector('.tag-select-trigger');
    const dropdown = wrap.querySelector('.tag-select-dropdown');
    if (!trigger || !dropdown) return;

    if (wrap.dataset.mode === 'multi') {
      const ids = JSON.parse(wrap.dataset.selected || '[]');
      setMultiValue(wrap, ids);
    } else if (wrap.dataset.mode === 'filter-multi') {
      const ids = JSON.parse(wrap.dataset.selected || '[]');
      const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
      setTagMatch(wrap, wrap.dataset.tagMatch || getTagMatch(wrap));
      setFilterMultiValue(wrap, ids, tagNone, { submit: false });
    } else {
      setFilterValue(wrap, wrap.dataset.selected || '', { submit: false });
    }

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = dropdown.hidden;
      closeAllDropdowns(wrap);
      dropdown.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    dropdown.addEventListener('click', (e) => {
      const addBtn = e.target.closest('[data-open-tag-modal]');
      if (addBtn) {
        e.preventDefault();
        e.stopPropagation();
        openTagModal(wrap, null);
        return;
      }

      const kebab = e.target.closest('.tag-select-kebab');
      if (kebab) {
        e.stopPropagation();
        const menu = kebab.nextElementSibling;
        const willOpen = menu && menu.hidden;
        closeAllActionMenus(menu);
        if (menu) menu.hidden = !willOpen;
        return;
      }

      const editBtn = e.target.closest('[data-edit-tag]');
      if (editBtn) {
        e.stopPropagation();
        closeAllActionMenus(null);
        const tagId = parseInt(editBtn.dataset.editTag, 10);
        const row = editBtn.closest('.tag-select-option-row');
        const cached = (tagCache || []).find((t) => t.id === tagId);
        const tag = cached || {
          id: tagId,
          name: row?.dataset.label || '',
          color: row?.dataset.color || PRESET_COLORS[0],
        };
        openTagModal(wrap, tag);
        return;
      }

      const matchBtn = e.target.closest('[data-tag-match]');
      if (matchBtn && wrap.dataset.mode === 'filter-multi') {
        e.stopPropagation();
        setTagMatch(wrap, matchBtn.dataset.tagMatch);
        const ids = collectCheckboxIds(wrap);
        const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
        setFilterMultiValue(wrap, ids, tagNone, { submit: wrap.dataset.autoSubmit === '1' });
        return;
      }

      const row = e.target.closest('.tag-select-option-row');
      if (row) {
        const mode = wrap.dataset.mode;
        if (mode === 'filter') {
          const value = row.dataset.value ?? '';
          setFilterValue(wrap, value, { submit: true });
          dropdown.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
        } else if (mode === 'multi' || mode === 'filter-multi') {
          e.stopPropagation();
          if (e.target.closest('.tag-select-kebab') || e.target.closest('[data-edit-tag]')) return;
          const cb = row.querySelector('.tag-select-checkbox');
          if (!cb) return;
          if (e.target !== cb) {
            e.preventDefault();
            cb.checked = !cb.checked;
          }
          const ids = collectCheckboxIds(wrap);
          if (mode === 'filter-multi') {
            setFilterMultiValue(wrap, ids, false, { submit: wrap.dataset.autoSubmit === '1' });
          } else {
            setMultiValue(wrap, ids);
          }
        }
        return;
      }

      const opt = e.target.closest('.tag-select-option');
      if (!opt) return;

      const value = opt.dataset.value ?? '';
      const mode = wrap.dataset.mode;
      if (mode === 'filter-multi') {
        if (value === '') {
          setFilterMultiValue(wrap, [], false, { submit: wrap.dataset.autoSubmit === '1' });
        } else if (value === 'none') {
          setFilterMultiValue(wrap, [], true, { submit: wrap.dataset.autoSubmit === '1' });
        }
        if (wrap.dataset.autoSubmit === '1') {
          dropdown.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
        }
        return;
      }
      if (mode === 'filter') {
        setFilterValue(wrap, value, { submit: true });
        dropdown.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function seedTagCacheFromDom() {
    if (tagCache && tagCache.length) return;
    const seen = new Map();
    document.querySelectorAll('.tag-select-option-row').forEach((row) => {
      const id = parseInt(row.dataset.value, 10);
      if (!id || seen.has(id)) return;
      seen.set(id, {
        id,
        name: row.dataset.label || '',
        color: row.dataset.color || PRESET_COLORS[0],
      });
    });
    if (seen.size) {
      tagCache = Array.from(seen.values()).sort((a, b) => a.name.localeCompare(b.name, 'vi'));
    }
  }

  function initAllTagSelects() {
    seedTagCacheFromDom();
    document.querySelectorAll('[data-tag-select]').forEach(initTagSelect);
  }

  /* ─── Tag form modal (create / edit) ─── */
  const modal = document.getElementById('tagFormModal');
  const modalTitle = document.getElementById('tagFormModalTitle');
  const idInput = document.getElementById('tagFormId');
  const nameInput = document.getElementById('tagFormName');
  const errorEl = document.getElementById('tagFormError');
  const submitBtn = document.getElementById('tagFormSubmit');
  const customColorInput = document.getElementById('tagFormCustomColor');
  let modalSourceWrap = null;
  let modalSourceChecklist = null;

  function setColorPalette(color) {
    const normalized = (color || PRESET_COLORS[0]).toUpperCase();
    const presetIdx = PRESET_COLORS.findIndex((c) => c.toUpperCase() === normalized);
    customColorActive = presetIdx < 0;
    selectedColor = presetIdx >= 0 ? PRESET_COLORS[presetIdx] : normalized;

    document.querySelectorAll('.tag-color-swatch').forEach((sw, i) => {
      sw.classList.toggle('is-selected', !customColorActive && i === presetIdx);
    });
    if (customColorInput) {
      customColorInput.value = customColorActive ? normalized : (customColorInput.value || '#6366F1');
    }
  }

  function openTagModal(sourceWrap, tag, sourceChecklist = null) {
    modalSourceWrap = sourceWrap;
    modalSourceChecklist = sourceChecklist;
    if (!modal) return;

    const isEdit = Boolean(tag);
    if (modalTitle) modalTitle.textContent = isEdit ? 'Sửa chủ đề' : 'Thêm chủ đề mới';
    if (submitBtn) submitBtn.textContent = isEdit ? 'Cập nhật' : 'Lưu chủ đề';
    if (idInput) idInput.value = isEdit ? String(tag.id) : '';
    if (nameInput) {
      nameInput.value = isEdit ? tag.name : '';
      nameInput.focus();
    }
    if (errorEl) errorEl.hidden = true;
    setColorPalette(isEdit ? tag.color : PRESET_COLORS[0]);

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    closeAllDropdowns(null);
  }

  function closeTagModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modalSourceWrap = null;
    modalSourceChecklist = null;
  }

  modal?.querySelectorAll('[data-close-tag-modal]').forEach((el) => {
    el.addEventListener('click', closeTagModal);
  });

  document.getElementById('tagColorPalette')?.addEventListener('click', (e) => {
    const swatch = e.target.closest('.tag-color-swatch');
    if (swatch) {
      customColorActive = false;
      selectedColor = swatch.dataset.color;
      document.querySelectorAll('.tag-color-swatch').forEach((s) => s.classList.remove('is-selected'));
      swatch.classList.add('is-selected');
    }
  });

  customColorInput?.addEventListener('input', () => {
    customColorActive = true;
    selectedColor = customColorInput.value.toUpperCase();
    document.querySelectorAll('.tag-color-swatch').forEach((s) => s.classList.remove('is-selected'));
  });

  customColorInput?.addEventListener('click', () => {
    customColorActive = true;
    document.querySelectorAll('.tag-color-swatch').forEach((s) => s.classList.remove('is-selected'));
  });

  submitBtn?.addEventListener('click', async () => {
    const name = nameInput?.value.trim();
    if (!name) {
      if (errorEl) {
        errorEl.textContent = 'Nhập tên chủ đề.';
        errorEl.hidden = false;
      }
      return;
    }

    const color = customColorActive ? customColorInput.value.toUpperCase() : selectedColor;
    const tagId = idInput?.value ? parseInt(idInput.value, 10) : null;
    const isEdit = Boolean(tagId);
    submitBtn.disabled = true;

    try {
      const url = isEdit ? `${tagsUpdateBaseUrl}/${tagId}` : tagsStoreUrl;
      const res = await fetch(url, {
        method: isEdit ? 'PATCH' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
        },
        body: JSON.stringify({ name, color }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        throw new Error(json.error || (isEdit ? 'Lỗi cập nhật chủ đề' : 'Lỗi tạo chủ đề'));
      }

      const tag = json.data;
      tagCache = null;
      await refreshAllTagSelects(tag);
      refreshAllTagChecklists(tag);
      updateTagChipsOnPage(tag);

      if (!isEdit && modalSourceWrap?.dataset.mode === 'multi') {
        const ids = JSON.parse(modalSourceWrap.dataset.selected || '[]');
        if (!ids.includes(tag.id)) {
          setMultiValue(modalSourceWrap, [...ids, tag.id]);
        }
      } else if (!isEdit && modalSourceWrap?.dataset.mode === 'filter') {
        setFilterValue(modalSourceWrap, String(tag.id), { submit: true });
      } else if (!isEdit && modalSourceWrap?.dataset.mode === 'filter-multi') {
        const ids = JSON.parse(modalSourceWrap.dataset.selected || '[]');
        if (!ids.includes(tag.id)) {
          setFilterMultiValue(
            modalSourceWrap,
            [...ids, tag.id],
            false,
            { submit: modalSourceWrap.dataset.autoSubmit === '1' },
          );
        }
      } else if (!isEdit && modalSourceChecklist) {
        const cb = modalSourceChecklist.querySelector(`input[type="checkbox"][value="${tag.id}"]`);
        if (cb) cb.checked = true;
      }

      closeTagModal();
      if (window.AdminToast) {
        AdminToast.show(isEdit ? `Đã cập nhật "${tag.name}"` : `Đã thêm chủ đề "${tag.name}"`, 'success');
      }
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = err.message || 'Không thể lưu chủ đề.';
        errorEl.hidden = false;
      }
    } finally {
      submitBtn.disabled = false;
    }
  });

  function updateTagChipsOnPage(tag) {
    document.querySelectorAll(`[data-tag-id="${tag.id}"]`).forEach((el) => {
      el.textContent = tag.name;
      el.style.background = tag.color;
      el.style.color = contrastText(tag.color);
      el.style.borderColor = tag.color;
    });
  }

  function createChecklistItem(tag, checked = false) {
    const row = document.createElement('div');
    row.className = 'tag-checklist-row';
    row.dataset.value = String(tag.id);
    row.dataset.label = tag.name;
    row.dataset.color = tag.color;
    row.innerHTML = `
      <label class="tag-checklist-item">
        <input type="checkbox" name="tag_ids[]" value="${tag.id}"${checked ? ' checked' : ''}>
        <span class="tag-checklist-dot" style="background:${escapeHtml(tag.color)}"></span>
        <span class="tag-checklist-label">${escapeHtml(tag.name)}</span>
      </label>
      <div class="tag-checklist-option-menu">
        <button type="button" class="tag-select-kebab" aria-label="Sửa chủ đề">⋮</button>
        <div class="tag-select-action-menu" hidden>
          <button type="button" data-edit-tag="${tag.id}">Sửa tên và màu</button>
        </div>
      </div>`;
    return row;
  }

  function handleTagChecklistClick(e, checklist) {
    if (!checklist) return false;

    const kebab = e.target.closest('.tag-select-kebab');
    if (kebab && checklist.contains(kebab)) {
      e.preventDefault();
      e.stopPropagation();
      const menu = kebab.nextElementSibling;
      const willOpen = menu && menu.hidden;
      closeAllActionMenus(menu);
      if (menu) menu.hidden = !willOpen;
      return true;
    }

    const editBtn = e.target.closest('[data-edit-tag]');
    if (editBtn && checklist.contains(editBtn)) {
      e.preventDefault();
      e.stopPropagation();
      closeAllActionMenus(null);
      const row = editBtn.closest('.tag-checklist-row');
      const tagId = parseInt(editBtn.dataset.editTag, 10);
      const cached = (tagCache || []).find((t) => t.id === tagId);
      const tag = cached || {
        id: tagId,
        name: row?.dataset.label || '',
        color: row?.dataset.color || PRESET_COLORS[0],
      };
      openTagModal(null, tag, checklist);
      return true;
    }

    return false;
  }

  function refreshAllTagChecklists(changedTag) {
    if (!changedTag) return;

    document.querySelectorAll('[data-tag-checklist]').forEach((checklist) => {
      checklist.querySelector('.tag-checklist-empty')?.remove();

      const existing = checklist.querySelector(`input[type="checkbox"][value="${changedTag.id}"]`);
      if (existing) {
        const row = existing.closest('.tag-checklist-row');
        const dot = row?.querySelector('.tag-checklist-dot');
        const nameEl = row?.querySelector('.tag-checklist-label');
        if (row) {
          row.dataset.label = changedTag.name;
          row.dataset.color = changedTag.color;
        }
        if (nameEl) nameEl.textContent = changedTag.name;
        if (dot) dot.style.background = changedTag.color;
        return;
      }

      const newItem = createChecklistItem(changedTag, false);
      const rows = Array.from(checklist.querySelectorAll('.tag-checklist-row'));
      const insertBefore = rows.find((row) => {
        const name = row.querySelector('.tag-checklist-label')?.textContent || '';
        return name.localeCompare(changedTag.name, 'vi') > 0;
      });
      if (insertBefore) {
        checklist.insertBefore(newItem, insertBefore);
      } else {
        checklist.appendChild(newItem);
      }
    });
  }

  async function refreshAllTagSelects(changedTag) {
    let tags = tagCache || await fetchTags();
    if (changedTag) {
      const idx = tags.findIndex((t) => t.id === changedTag.id);
      if (idx >= 0) tags[idx] = changedTag;
      else {
        tags.push(changedTag);
        tags.sort((a, b) => a.name.localeCompare(b.name, 'vi'));
      }
      tagCache = tags;
    }
    document.querySelectorAll('[data-tag-select]').forEach((wrap) => {
      rebuildOptionsList(wrap, tags);
      if (wrap.dataset.mode === 'multi') {
        setMultiValue(wrap, JSON.parse(wrap.dataset.selected || '[]'));
      } else if (wrap.dataset.mode === 'filter-multi') {
        const tagNone = wrap.querySelector('[name="tag_none"]')?.value === '1';
        setFilterMultiValue(wrap, JSON.parse(wrap.dataset.selected || '[]'), tagNone, { submit: false });
      } else {
        setFilterValue(wrap, wrap.dataset.selected || '', { submit: false });
      }
    });
  }

  document.addEventListener('click', (e) => {
    const addBtn = e.target.closest('[data-open-tag-modal-from-checklist]');
    if (addBtn) {
      e.preventDefault();
      e.stopPropagation();
      const checklist = addBtn.closest('[data-tags-editor]')?.querySelector('[data-tag-checklist]')
        || addBtn.closest('.form-group')?.querySelector('[data-tag-checklist]')
        || addBtn.parentElement?.querySelector('[data-tag-checklist]');
      openTagModal(null, null, checklist || null);
      return;
    }

    const checklist = e.target.closest('[data-tag-checklist]');
    if (checklist && handleTagChecklistClick(e, checklist)) return;

    if (!e.target.closest('.tag-select-action-menu') && !e.target.closest('.tag-select-kebab')) {
      closeAllDropdowns(null);
    }
  });

  document.addEventListener('DOMContentLoaded', initAllTagSelects);

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.AdminTags = {
    refreshAllTagSelects,
    refreshAllTagChecklists,
    fetchTags,
    openTagModal,
    handleTagChecklistClick,
  };
})();
