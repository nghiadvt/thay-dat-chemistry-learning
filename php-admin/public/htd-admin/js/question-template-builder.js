/**
 * Admin — equation template builder for structured questions.
 */
window.QuestionTemplateBuilder = (function () {
  'use strict';

  const PART_TYPES = [
    { value: 'coef', label: 'Hệ số' },
    { value: 'blank', label: 'Ô điền' },
    { value: 'chem', label: 'Chất cố định' },
    { value: 'txt', label: 'Ký hiệu' },
  ];

  const INPUT_MODE_LABELS = {
    balance: 'Cân bằng hệ số',
    blank: 'Điền chỗ thiếu',
    blank_balance: 'Cân bằng + điền thiếu',
    product: 'Điền sản phẩm',
  };

  const PRESETS = {
    balance: {
      template: [
        { t: 'coef', id: 'c0' }, { t: 'chem', text: 'H2' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c1' }, { t: 'chem', text: 'O2' },
        { t: 'txt', text: ' → ' },
        { t: 'coef', id: 'c2' }, { t: 'chem', text: 'H2O' },
      ],
      correct_answer: { coef: { c0: '2', c1: '1', c2: '2' }, blank: {} },
    },
    blank: {
      template: [
        { t: 'chem', text: 'Fe' },
        { t: 'txt', text: ' + ' },
        { t: 'blank', id: 'b0' },
        { t: 'txt', text: ' → ' },
        { t: 'chem', text: 'Fe2O3' },
      ],
      correct_answer: { coef: {}, blank: { b0: 'O2' } },
    },
    blank_balance: {
      template: [
        { t: 'coef', id: 'c0' }, { t: 'blank', id: 'b0' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c1' }, { t: 'chem', text: 'O2' },
        { t: 'txt', text: ' → ' },
        { t: 'coef', id: 'c2' }, { t: 'chem', text: 'H2O' },
      ],
      correct_answer: { coef: { c0: '2', c1: '1', c2: '2' }, blank: { b0: 'H2' } },
    },
    product: {
      template: [
        { t: 'chem', text: 'NH3' },
        { t: 'txt', text: ' + ' },
        { t: 'chem', text: 'HCl' },
        { t: 'txt', text: ' → ' },
        { t: 'blank', id: 'b0' },
      ],
      correct_answer: { coef: {}, blank: { b0: 'NH4Cl' } },
    },
  };

  let template = [];
  let correctAnswer = { coef: {}, blank: {} };
  let inputMode = 'balance';
  let selectedIndex = null;
  let dragIndex = null;

  let previewEl = null;
  let partsListEl = null;
  let inputModeEl = null;
  let templateField = null;
  let correctAnswerField = null;

  function nextId(prefix) {
    let max = -1;
    template.forEach((part) => {
      if ((part.t === 'coef' && prefix === 'c') || (part.t === 'blank' && prefix === 'b')) {
        const match = String(part.id || '').match(new RegExp('^' + prefix + '(\\d+)$'));
        if (match) max = Math.max(max, parseInt(match[1], 10));
      }
    });
    return prefix + (max + 1);
  }

  function syncCorrectAnswerKeys() {
    const coef = {};
    const blank = {};
    template.forEach((part) => {
      if (part.t === 'coef' && part.id) {
        coef[part.id] = correctAnswer.coef?.[part.id] ?? '';
      }
      if (part.t === 'blank' && part.id) {
        blank[part.id] = correctAnswer.blank?.[part.id] ?? '';
      }
    });
    correctAnswer = { coef, blank };
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
  }

  function syncHiddenFields() {
    if (templateField) templateField.value = JSON.stringify(template);
    if (correctAnswerField) correctAnswerField.value = JSON.stringify(correctAnswer);
  }

  function renderPreview() {
    if (!previewEl) return;

    if (!template.length) {
      previewEl.innerHTML = '<p class="qe-template-empty">Chưa có phương trình. Dùng «Nhập nhanh» hoặc thêm phần tử.</p>';
      return;
    }

    previewEl.innerHTML = template.map((part, index) => {
      const selected = selectedIndex === index ? ' is-selected' : '';
      const label = PART_TYPES.find((p) => p.value === part.t)?.label || part.t;

      if (part.t === 'coef') {
        const val = correctAnswer.coef?.[part.id] || '';
        return (
          '<button type="button" class="qe-preview-chip qe-preview-chip--coef' + selected + '" data-preview-index="' + index + '" ' +
          'title="' + escapeAttr(label + ' — click để chọn') + '">' +
          (val ? escapeHtml(val) : '□') + '</button>'
        );
      }

      if (part.t === 'blank') {
        const val = correctAnswer.blank?.[part.id] || '';
        return (
          '<button type="button" class="qe-preview-chip qe-preview-chip--blank' + selected + '" data-preview-index="' + index + '" ' +
          'title="' + escapeAttr(label + ' — click để chọn') + '">' +
          (val ? escapeHtml(val) : '?') + '</button>'
        );
      }

      if (part.t === 'chem') {
        const html = typeof EquationUI !== 'undefined'
          ? EquationUI.chemToHtml(part.text || '')
          : escapeHtml(part.text || '');
        return (
          '<button type="button" class="qe-preview-chip qe-preview-chip--chem' + selected + '" data-preview-index="' + index + '" ' +
          'title="' + escapeAttr(label + ' — click để chọn') + '">' + html + '</button>'
        );
      }

      return (
        '<button type="button" class="qe-preview-chip qe-preview-chip--txt' + selected + '" data-preview-index="' + index + '" ' +
        'title="' + escapeAttr(label + ' — click để chọn') + '">' +
        escapeHtml((part.text || '').replace(/->/g, '→')) + '</button>'
      );
    }).join('');
  }

  function partTypeOptions(selected) {
    return PART_TYPES.map((opt) =>
      '<option value="' + opt.value + '"' + (opt.value === selected ? ' selected' : '') + '>' + escapeHtml(opt.label) + '</option>'
    ).join('');
  }

  function renderPartsList() {
    if (!partsListEl) return;

    if (!template.length) {
      partsListEl.innerHTML = '<p class="qe-template-empty">Chưa có phần tử. Dùng «Nhập nhanh» hoặc nút thêm bên trên.</p>';
      renderPreview();
      syncHiddenFields();
      return;
    }

    partsListEl.innerHTML = template.map((part, index) => {
      const selected = selectedIndex === index ? ' is-selected' : '';
      let body = '';

      if (part.t === 'coef') {
        body =
          '<label class="qe-part-answer-inline">' +
          '<span>Đáp án</span>' +
          '<input type="text" inputmode="numeric" class="qe-inline-coef" data-part-index="' + index + '" ' +
          'value="' + escapeAttr(correctAnswer.coef?.[part.id] || '') + '" placeholder="2" maxlength="3">' +
          '</label>';
      } else if (part.t === 'blank') {
        body =
          '<label class="qe-part-answer-inline">' +
          '<span>Đáp án</span>' +
          '<input type="text" class="qe-inline-blank" data-part-index="' + index + '" ' +
          'value="' + escapeAttr(correctAnswer.blank?.[part.id] || '') + '" placeholder="O2, H2O" maxlength="64">' +
          '</label>';
      } else if (part.t === 'chem') {
        body =
          '<input type="text" class="qe-part-input" data-part-index="' + index + '" data-field="text" ' +
          'value="' + escapeAttr(part.text || '') + '" placeholder="H2O, Fe2O3" maxlength="64">';
      } else if (part.t === 'txt') {
        body =
          '<input type="text" class="qe-part-input qe-part-input--short" data-part-index="' + index + '" data-field="text" ' +
          'value="' + escapeAttr(part.text || '') + '" placeholder="+ hoặc →" maxlength="16">';
      }

      return (
        '<div class="qe-template-part' + selected + '" data-part-index="' + index + '">' +
        '<button type="button" class="qe-part-drag" draggable="true" title="Kéo thả để sắp xếp" aria-label="Kéo thả">⋮⋮</button>' +
        '<select class="qe-part-type-select" data-part-index="' + index + '" aria-label="Loại phần tử">' +
        partTypeOptions(part.t) +
        '</select>' +
        '<div class="qe-template-part-body">' + body + '</div>' +
        '<button type="button" class="qe-part-remove" title="Xóa">&times;</button>' +
        '</div>'
      );
    }).join('');

    renderPreview();
    syncHiddenFields();
  }

  function selectPart(index) {
    selectedIndex = index;
    renderPreview();
    const row = partsListEl?.querySelector('.qe-template-part[data-part-index="' + index + '"]');
    if (row) {
      row.classList.add('is-selected');
      row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      row.querySelector('input, select')?.focus();
    }
  }

  function changePartType(index, newType) {
    const part = template[index];
    if (!part || part.t === newType) return;

    const preservedText = part.text || '';
    const oldCoef = part.t === 'coef' ? correctAnswer.coef?.[part.id] : null;
    const oldBlank = part.t === 'blank' ? correctAnswer.blank?.[part.id] : null;

    if (newType === 'coef') {
      const id = nextId('c');
      template[index] = { t: 'coef', id };
      if (oldCoef != null) correctAnswer.coef[id] = oldCoef;
      else if (oldBlank != null) correctAnswer.coef[id] = oldBlank.replace(/\D/g, '');
    } else if (newType === 'blank') {
      const id = nextId('b');
      template[index] = { t: 'blank', id };
      if (oldBlank != null) correctAnswer.blank[id] = oldBlank;
      else if (oldCoef != null) correctAnswer.blank[id] = oldCoef;
    } else if (newType === 'chem') {
      template[index] = { t: 'chem', text: preservedText || oldBlank || '' };
    } else if (newType === 'txt') {
      template[index] = { t: 'txt', text: preservedText || ' + ' };
    }

    syncCorrectAnswerKeys();
    selectedIndex = index;
    renderPartsList();
  }

  function movePart(from, to) {
    if (from === to || from < 0 || to < 0 || from >= template.length || to >= template.length) return;
    const [item] = template.splice(from, 1);
    template.splice(to, 0, item);
    selectedIndex = to;
    renderPartsList();
  }

  function addPart(part) {
    template.push(part);
    syncCorrectAnswerKeys();
    selectedIndex = template.length - 1;
    renderPartsList();
  }

  function applyPreset(mode) {
    const preset = PRESETS[mode];
    if (!preset) return;
    template = JSON.parse(JSON.stringify(preset.template));
    correctAnswer = JSON.parse(JSON.stringify(preset.correct_answer));
    selectedIndex = null;
    renderPartsList();
  }

  function isChemToken(token) {
    return /^[A-Z][a-z]?(\d+)?([A-Z][a-z]?\d*)*$/.test(token);
  }

  function normalizeArrowText(text) {
    return String(text ?? '')
      .replace(/=>/g, '→')
      .replace(/->/g, '→');
  }

  function bindArrowAutoReplace(input) {
    if (!input) return;
    input.addEventListener('input', () => {
      const before = input.value;
      const replaced = normalizeArrowText(before);
      if (replaced === before) return;

      const pos = input.selectionStart ?? replaced.length;
      const newPos = normalizeArrowText(before.slice(0, pos)).length;
      input.value = replaced;
      input.setSelectionRange(newPos, newPos);
    });
  }

  function parseQuickEquation(eqRaw, answerRaw, mode) {
    let eq = normalizeArrowText(String(eqRaw || '').trim())
      .replace(/\s+/g, '')
      .replace(/___+/g, '[?]');

    if (!eq) return null;

    const tokenRe = /(\[\?\]|\[\]|[+→=↑↓-]|[A-Z][a-z]?(?:\d+)?(?:[A-Z][a-z]?\d*)*)/g;
    const tokens = eq.match(tokenRe) || [];
    if (!tokens.length) return null;

    const newTemplate = [];
    const newAnswers = { coef: {}, blank: {} };
    let cIdx = 0;
    let bIdx = 0;
    const autoCoef = mode === 'balance' || mode === 'blank_balance';

    const pushTxt = (text) => {
      const last = newTemplate[newTemplate.length - 1];
      if (last?.t === 'txt') last.text += text;
      else newTemplate.push({ t: 'txt', text });
    };

    tokens.forEach((tok) => {
      if (tok === '[?]') {
        newTemplate.push({ t: 'blank', id: 'b' + bIdx++ });
        return;
      }
      if (tok === '[]') {
        newTemplate.push({ t: 'coef', id: 'c' + cIdx++ });
        return;
      }
      if (tok === '+' || tok === '-') {
        pushTxt(' + ');
        return;
      }
      if (tok === '→' || tok === '=') {
        pushTxt(' → ');
        return;
      }
      if (tok === '↑') {
        pushTxt(' ↑ ');
        return;
      }
      if (tok === '↓') {
        pushTxt(' ↓ ');
        return;
      }
      if (isChemToken(tok)) {
        if (autoCoef) {
          newTemplate.push({ t: 'coef', id: 'c' + cIdx++ });
        }
        newTemplate.push({ t: 'chem', text: tok });
      }
    });

    if (!newTemplate.length) return null;

    const answerParts = String(answerRaw || '').split(/[,;|]/).map((s) => s.trim()).filter(Boolean);
    const coefIds = newTemplate.filter((p) => p.t === 'coef').map((p) => p.id);
    const blankIds = newTemplate.filter((p) => p.t === 'blank').map((p) => p.id);

    if (mode === 'blank_balance' && coefIds.length && blankIds.length) {
      coefIds.forEach((id, i) => { newAnswers.coef[id] = answerParts[i] || ''; });
      blankIds.forEach((id, i) => { newAnswers.blank[id] = answerParts[coefIds.length + i] || ''; });
    } else if (coefIds.length && !blankIds.length) {
      coefIds.forEach((id, i) => { newAnswers.coef[id] = answerParts[i] || ''; });
    } else {
      blankIds.forEach((id, i) => { newAnswers.blank[id] = answerParts[i] || ''; });
    }

    return { template: newTemplate, correct_answer: newAnswers };
  }

  function tbConfirm(message) {
    if (window.AdminConfirm) return AdminConfirm.show({ message, confirmText: 'Thay thế' });
    return Promise.resolve(window.confirm(message));
  }

  function tbNotify(message) {
    if (window.AdminToast) AdminToast.show(message, 'warning', 6000);
    else window.alert(message);
  }

  function applyQuickEquation() {
    const eqInput = document.getElementById('quickEquationInput');
    const ansInput = document.getElementById('quickAnswerInput');
    const mode = inputModeEl?.value || inputMode;
    const parsed = parseQuickEquation(eqInput?.value, ansInput?.value, mode);

    if (!parsed) {
      tbNotify('Không đọc được phương trình. Ví dụ: H2 + O2 → H2O (đáp án: 2,1,2) hoặc Fe + ___ → Fe2O3 (đáp án: O2)');
      return;
    }

    const apply = () => {
      template = parsed.template;
      correctAnswer = parsed.correct_answer;
      selectedIndex = null;
      renderPartsList();
    };

    if (template.length) {
      tbConfirm('Thay phương trình hiện tại bằng bản nhập nhanh?').then((ok) => { if (ok) apply(); });
      return;
    }
    apply();
  }

  function bindDragDrop() {
    if (!partsListEl) return;

    partsListEl.addEventListener('dragstart', (event) => {
      const handle = event.target.closest('.qe-part-drag');
      if (!handle) {
        event.preventDefault();
        return;
      }
      const row = handle.closest('.qe-template-part');
      if (!row) return;
      dragIndex = parseInt(row.dataset.partIndex, 10);
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', String(dragIndex));
    });

    partsListEl.addEventListener('dragend', (event) => {
      event.target.closest('.qe-template-part')?.classList.remove('is-dragging');
      partsListEl.querySelectorAll('.qe-template-part').forEach((el) => el.classList.remove('is-drop-target'));
      dragIndex = null;
    });

    partsListEl.addEventListener('dragover', (event) => {
      event.preventDefault();
      const row = event.target.closest('.qe-template-part');
      partsListEl.querySelectorAll('.qe-template-part').forEach((el) => el.classList.remove('is-drop-target'));
      if (row) row.classList.add('is-drop-target');
    });

    partsListEl.addEventListener('drop', (event) => {
      event.preventDefault();
      const row = event.target.closest('.qe-template-part');
      if (!row || dragIndex === null) return;
      const to = parseInt(row.dataset.partIndex, 10);
      movePart(dragIndex, to);
    });
  }

  function bindRoot(root) {
    previewEl = root.querySelector('#eqTemplatePreview');
    partsListEl = root.querySelector('#templatePartsList');
    inputModeEl = root.querySelector('#input_mode');
    templateField = root.querySelector('#template_json');
    correctAnswerField = root.querySelector('#correct_answer_json');

    root.querySelector('#btnAddCoef')?.addEventListener('click', () => {
      addPart({ t: 'coef', id: nextId('c') });
    });

    root.querySelector('#btnAddBlank')?.addEventListener('click', () => {
      addPart({ t: 'blank', id: nextId('b') });
    });

    root.querySelector('#btnAddChem')?.addEventListener('click', () => {
      addPart({ t: 'chem', text: '' });
    });

    root.querySelectorAll('[data-txt-symbol]').forEach((btn) => {
      btn.addEventListener('click', () => {
        addPart({ t: 'txt', text: btn.dataset.txtSymbol || ' + ' });
      });
    });

    root.querySelector('#btnApplyPreset')?.addEventListener('click', () => {
      const mode = inputModeEl?.value || inputMode;
      if (template.length) {
        tbConfirm('Thay phương trình hiện tại bằng mẫu «' + (INPUT_MODE_LABELS[mode] || mode) + '»?')
          .then((ok) => { if (ok) applyPreset(mode); });
        return;
      }
      applyPreset(mode);
    });

    root.querySelector('#btnQuickApply')?.addEventListener('click', applyQuickEquation);

    bindArrowAutoReplace(root.querySelector('#quickEquationInput'));

    root.querySelector('#quickEquationInput')?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyQuickEquation();
      }
    });

    if (inputModeEl) {
      inputModeEl.addEventListener('change', () => {
        inputMode = inputModeEl.value;
      });
    }

    previewEl?.addEventListener('click', (event) => {
      const chip = event.target.closest('[data-preview-index]');
      if (!chip) return;
      selectPart(parseInt(chip.dataset.previewIndex, 10));
    });

    partsListEl?.addEventListener('input', (event) => {
      const textInput = event.target.closest('.qe-part-input[data-field="text"]');
      if (textInput) {
        const before = textInput.value;
        const replaced = normalizeArrowText(before);
        if (replaced !== before) {
          const pos = textInput.selectionStart ?? replaced.length;
          const newPos = normalizeArrowText(before.slice(0, pos)).length;
          textInput.value = replaced;
          textInput.setSelectionRange(newPos, newPos);
        }
      }

      const coefInput = event.target.closest('.qe-inline-coef');
      if (coefInput) {
        const index = parseInt(coefInput.dataset.partIndex, 10);
        const part = template[index];
        if (part?.t === 'coef') {
          correctAnswer.coef[part.id] = coefInput.value.replace(/\D/g, '');
          renderPreview();
          syncHiddenFields();
        }
        return;
      }

      const blankInput = event.target.closest('.qe-inline-blank');
      if (blankInput) {
        const index = parseInt(blankInput.dataset.partIndex, 10);
        const part = template[index];
        if (part?.t === 'blank') {
          correctAnswer.blank[part.id] = blankInput.value;
          renderPreview();
          syncHiddenFields();
        }
        return;
      }

      const input = event.target.closest('.qe-part-input');
      if (!input) return;
      const index = parseInt(input.dataset.partIndex, 10);
      if (!Number.isFinite(index) || !template[index]) return;
      template[index].text = normalizeArrowText(input.value);
      if (input.value !== template[index].text) {
        input.value = template[index].text;
      }
      renderPreview();
      syncHiddenFields();
    });

    partsListEl?.addEventListener('change', (event) => {
      const select = event.target.closest('.qe-part-type-select');
      if (!select) return;
      changePartType(parseInt(select.dataset.partIndex, 10), select.value);
    });

    partsListEl?.addEventListener('click', (event) => {
      const row = event.target.closest('.qe-template-part');
      if (!row) return;
      const index = parseInt(row.dataset.partIndex, 10);

      if (event.target.closest('.qe-part-remove')) {
        template.splice(index, 1);
        syncCorrectAnswerKeys();
        selectedIndex = null;
        renderPartsList();
        return;
      }

      if (!event.target.closest('.qe-part-drag')) {
        selectPart(index);
      }
    });

    bindDragDrop();
  }

  function init(boot) {
    inputMode = boot.inputMode || 'balance';
    template = Array.isArray(boot.template) ? JSON.parse(JSON.stringify(boot.template)) : [];
    correctAnswer = boot.correctAnswer && typeof boot.correctAnswer === 'object'
      ? JSON.parse(JSON.stringify(boot.correctAnswer))
      : { coef: {}, blank: {} };

    if (!correctAnswer.coef) correctAnswer.coef = {};
    if (!correctAnswer.blank) correctAnswer.blank = {};

    syncCorrectAnswerKeys();

    const root = document.getElementById('section-structured');
    if (!root) return;

    bindRoot(root);
    if (inputModeEl) inputModeEl.value = inputMode;
    renderPartsList();
  }

  function getState() {
    return {
      input_mode: inputModeEl?.value || inputMode,
      template: JSON.parse(JSON.stringify(template)),
      correct_answer: JSON.parse(JSON.stringify(correctAnswer)),
    };
  }

  function collectDraftQuestion(base) {
    syncHiddenFields();
    const state = getState();
    return {
      ...base,
      answer_type: 'structured',
      input_mode: state.input_mode,
      template: state.template,
      correct_answer: state.correct_answer,
      options: null,
      correct_index: null,
      correct_answer_normalized: null,
    };
  }

  return {
    INPUT_MODE_LABELS,
    PART_TYPES,
    init,
    getState,
    collectDraftQuestion,
    applyPreset,
    parseQuickEquation,
  };
})();
