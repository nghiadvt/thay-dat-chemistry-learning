(function () {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function contrastTextColor(hex) {
    const h = String(hex).replace('#', '');
    if (h.length !== 6) return '#ffffff';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.62 ? '#1f2937' : '#ffffff';
  }

  function parseSelectedIds(raw) {
    try {
      const parsed = JSON.parse(raw || '[]');
      return Array.isArray(parsed) ? parsed.map((id) => parseInt(id, 10)).filter((id) => id > 0) : [];
    } catch {
      return [];
    }
  }

  function getChecklistSelection(checklist) {
    if (!checklist) return [];
    return Array.from(checklist.querySelectorAll('input[type="checkbox"]:checked'))
      .map((cb) => parseInt(cb.value, 10))
      .filter((id) => id > 0);
  }

  function setChecklistSelection(checklist, ids) {
    if (!checklist) return;
    const idSet = new Set(ids.map((id) => String(id)));
    checklist.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
      cb.checked = idSet.has(cb.value);
    });
  }

  function renderTagsDisplay(container, tags) {
    if (!container) return;

    if (!tags.length) {
      container.innerHTML = '<span class="tag-chip tag-chip--untagged">Chưa có chủ đề</span>';
      return;
    }

    const list = document.createElement('div');
    list.className = 'tag-list tag-list--compact';

    tags.forEach((tag) => {
      const chip = document.createElement('span');
      chip.className = 'tag-chip';
      const color = tag.color || '#2D46D6';
      const textColor = contrastTextColor(color);
      chip.style.background = color;
      chip.style.color = textColor;
      chip.style.borderColor = color;
      chip.dataset.tagId = String(tag.id);
      chip.textContent = tag.name;
      list.appendChild(chip);
    });

    container.innerHTML = '';
    container.appendChild(list);
  }

  function arraysEqual(a, b) {
    if (a.length !== b.length) return false;
    const sa = [...a].sort((x, y) => x - y);
    const sb = [...b].sort((x, y) => x - y);
    return sa.every((v, i) => v === sb[i]);
  }

  let openEditor = null;

  function positionEditor(editor, kebab) {
    if (!editor || !kebab) return;
    editor.classList.add('question-tags-editor--fixed');
    const rect = kebab.getBoundingClientRect();
    const width = 260;
    let left = rect.right - width;
    left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
    let top = rect.bottom + 4;
    const maxH = 340;
    if (top + maxH > window.innerHeight - 8) {
      top = Math.max(8, rect.top - maxH - 4);
    }
    editor.style.top = `${top}px`;
    editor.style.left = `${left}px`;
  }

  function resetEditorPosition(editor) {
    if (!editor) return;
    editor.classList.remove('question-tags-editor--fixed');
    editor.style.top = '';
    editor.style.left = '';
  }

  function closeEditor(editor, revert) {
    if (!editor) return;
    const cell = editor.closest('[data-question-tags-cell]');
    const checklist = editor.querySelector('[data-tag-checklist]');
    const kebab = cell?.querySelector('[data-tags-kebab]');

    if (revert && cell && checklist) {
      setChecklistSelection(checklist, parseSelectedIds(cell.dataset.selectedIds));
    }

    editor.hidden = true;
    kebab?.setAttribute('aria-expanded', 'false');
    resetEditorPosition(editor);
    if (openEditor === editor) openEditor = null;
  }

  function closeAllEditors(revert) {
    document.querySelectorAll('[data-tags-editor]').forEach((editor) => {
      if (!editor.hidden) closeEditor(editor, revert);
    });
  }

  async function saveCellTags(cell) {
    const editor = cell.querySelector('[data-tags-editor]');
    const checklist = editor?.querySelector('[data-tag-checklist]');
    const saveBtn = editor?.querySelector('[data-tags-save]');
    const url = cell.dataset.updateUrl;
    const tagIds = getChecklistSelection(checklist);
    const previousIds = parseSelectedIds(cell.dataset.selectedIds);

    if (arraysEqual(tagIds, previousIds)) {
      closeEditor(editor, false);
      return;
    }

    if (saveBtn) saveBtn.disabled = true;

    try {
      const res = await fetch(url, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ tag_ids: tagIds }),
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(data.message || data.error || 'Không thể cập nhật chủ đề.');
      }

      const savedTags = data.data?.tags || [];
      const savedIds = savedTags.map((t) => parseInt(t.id, 10));
      cell.dataset.selectedIds = JSON.stringify(savedIds);
      renderTagsDisplay(cell.querySelector('[data-tags-display]'), savedTags);
      setChecklistSelection(checklist, savedIds);
      closeEditor(editor, false);

      if (window.AdminToast) {
        AdminToast.show('Đã cập nhật chủ đề.', 'success');
      }
    } catch (err) {
      if (window.AdminToast) {
        AdminToast.show(err.message || 'Lỗi khi cập nhật chủ đề.', 'error');
      } else {
        alert(err.message || 'Lỗi khi cập nhật chủ đề.');
      }
    } finally {
      if (saveBtn) saveBtn.disabled = false;
    }
  }

  function initQuestionTagsCells() {
    document.querySelectorAll('[data-question-tags-cell]').forEach((cell) => {
      // Gọi lại được sau khi nạp thêm hàng mà không gắn trùng listener.
      if (cell.dataset.tagsCellBound === '1') return;
      cell.dataset.tagsCellBound = '1';

      const kebab = cell.querySelector('[data-tags-kebab]');
      const editor = cell.querySelector('[data-tags-editor]');
      const checklist = editor?.querySelector('[data-tag-checklist]');

      kebab?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = openEditor === editor && editor && !editor.hidden;

        closeAllEditors(true);

        if (!isOpen && editor) {
          setChecklistSelection(checklist, parseSelectedIds(cell.dataset.selectedIds));
          editor.hidden = false;
          positionEditor(editor, kebab);
          kebab.setAttribute('aria-expanded', 'true');
          openEditor = editor;
        }
      });

      editor?.querySelector('[data-tags-save]')?.addEventListener('click', (e) => {
        e.stopPropagation();
        saveCellTags(cell);
      });

      editor?.querySelector('[data-tags-cancel]')?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeEditor(editor, true);
      });

      editor?.querySelector('[data-tags-clear]')?.addEventListener('click', (e) => {
        e.stopPropagation();
        setChecklistSelection(checklist, []);
      });

      editor?.querySelector('[data-open-tag-modal-from-checklist]')?.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (window.AdminTags?.openTagModal) {
          window.AdminTags.openTagModal(null, null, checklist);
        }
      });

      editor?.addEventListener('click', (e) => {
        if (e.target.closest('[data-open-tag-modal-from-checklist]')) return;
        if (window.AdminTags?.handleTagChecklistClick?.(e, checklist)) return;
        e.stopPropagation();
      });
    });

    window.addEventListener('resize', () => {
      if (openEditor && !openEditor.hidden) {
        const cell = openEditor.closest('[data-question-tags-cell]');
        const kebab = cell?.querySelector('[data-tags-kebab]');
        positionEditor(openEditor, kebab);
      }
    });

    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-open-tag-modal-from-checklist]') || e.target.closest('#tagFormModal')) {
        return;
      }
      if (e.target.closest('.tag-select-action-menu') || e.target.closest('.tag-select-kebab')) {
        return;
      }
      closeAllEditors(true);
    });
  }

  function getBulkTagSelection(root) {
    const checklist = root?.querySelector('[data-tag-checklist]');
    return { tag_ids: getChecklistSelection(checklist) };
  }

  // Hàng nạp thêm khi mở nhóm cần được gắn lại ô sửa chủ đề.
  document.addEventListener('admin:rows-added', initQuestionTagsCells);

  window.QuestionTagsCell = {
    getBulkTagSelection,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQuestionTagsCells);
  } else {
    initQuestionTagsCells();
  }
})();
