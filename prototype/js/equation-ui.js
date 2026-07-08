/** Equation rendering & interactive input for chemistry questions */
const EquationUI = (() => {
  const FORMULA_SUBSCRIPT = '₀₁₂₃₄₅₆₇₈₉';
  const DEFAULT_SMART_CONTEXT = { after_element: 'subscript', after_plus: 'coefficient' };

  function formulaEsc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function formulaSubDisplay(digits) {
    return [...digits].map(d => {
      const i = d.charCodeAt(0) - 48;
      return i >= 0 && i <= 9 ? FORMULA_SUBSCRIPT[i] : d;
    }).join('');
  }

  function formulaIsElement(val) {
    return /^[A-Z][a-z]?$/.test(val);
  }

  function formulaIsDigit(val) {
    return /^[0-9]$/.test(val);
  }

  function formulaSmartContext(tokens, smartContext) {
    const ctx = smartContext || DEFAULT_SMART_CONTEXT;
    if (!tokens.length) return 'coefficient';
    const last = tokens[tokens.length - 1];
    if (last.type === 'element') return ctx.after_element || 'subscript';
    if (last.type === 'subscript') return 'subscript';
    if (last.type === 'coefficient') return 'coefficient';
    if (last.type === 'symbol') {
      if (last.value === '+') return ctx.after_plus || 'coefficient';
      if (last.value === ')') return 'subscript';
      return 'coefficient';
    }
    return 'coefficient';
  }

  function formulaMakeToken(type, value) {
    return {
      type,
      value,
      display: type === 'subscript' ? formulaSubDisplay(value) : value,
    };
  }

  function formulaAppendToken(tokens, raw, smartContext) {
    if (formulaIsDigit(raw)) {
      const ctx = formulaSmartContext(tokens, smartContext);
      const last = tokens[tokens.length - 1];
      if (ctx === 'subscript') {
        if (last?.type === 'subscript') {
          last.value += raw;
          last.display = formulaSubDisplay(last.value);
        } else {
          tokens.push(formulaMakeToken('subscript', raw));
        }
      } else if (last?.type === 'coefficient') {
        last.value += raw;
        last.display = last.value;
      } else {
        tokens.push(formulaMakeToken('coefficient', raw));
      }
      return;
    }
    if (formulaIsElement(raw)) {
      tokens.push(formulaMakeToken('element', raw));
      return;
    }
    tokens.push(formulaMakeToken('symbol', raw));
  }

  function formulaSerialize(tokens) {
    return tokens.map(t => t.value).join('');
  }

  function formulaRenderHtml(tokens) {
    return tokens.map(t => {
      if (t.type === 'subscript') return `<sub>${formulaEsc(t.value)}</sub>`;
      if (t.value === '\n') return '<br>';
      return formulaEsc(t.display);
    }).join('');
  }

  function chemToHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/->/g, '→')
      .replace(/(\d+)/g, '<sub>$1</sub>');
  }

  function normalizeFormula(s) {
    return String(s || '')
      .toUpperCase()
      .replace(/\s/g, '')
      .replace(/→/g, '->')
      .replace(/₂/g, '2')
      .replace(/₃/g, '3')
      .replace(/₄/g, '4')
      .replace(/₅/g, '5')
      .replace(/₆/g, '6')
      .replace(/₇/g, '7')
      .replace(/₈/g, '8')
      .replace(/₉/g, '9')
      .replace(/₀/g, '0');
  }

  function renderPart(part, values, focusedId) {
    if (part.t === 'txt') {
      return `<span class="eq-txt">${part.text.replace(/->/g, '→')}</span>`;
    }
    if (part.t === 'chem') {
      return `<span class="eq-chem">${chemToHtml(part.text)}</span>`;
    }
    if (part.t === 'coef') {
      const v = values.coef[part.id] || '';
      const focused = focusedId === part.id ? ' focused' : '';
      const filled = v ? ' filled' : '';
      return `<button type="button" class="eq-coef-box${focused}${filled}" data-slot="coef" data-id="${part.id}" aria-label="Hệ số">${v || '&nbsp;'}</button>`;
    }
    if (part.t === 'blank') {
      const tokens = values.blankTokens?.[part.id];
      const raw = values.blank[part.id] || '';
      const focused = focusedId === part.id ? ' focused' : '';
      const inner = tokens?.length
        ? formulaRenderHtml(tokens)
        : raw
          ? chemToHtml(raw)
          : '<span class="eq-placeholder">?</span>';
      return `<button type="button" class="eq-blank-box${focused}${raw ? ' filled' : ''}" data-slot="blank" data-id="${part.id}" aria-label="Điền công thức">${inner}</button>`;
    }
    return '';
  }

  function renderEquation(template, values, focusedId) {
    return `<div class="eq-line">${template.map(p => renderPart(p, values, focusedId)).join('')}</div>`;
  }

  function createInputState(template) {
    const values = { coef: {}, blank: {}, blankTokens: {} };
    (template || []).forEach(p => {
      if (p.t === 'coef') values.coef[p.id] = '';
      if (p.t === 'blank') {
        values.blank[p.id] = '';
        values.blankTokens[p.id] = [];
      }
    });
    return values;
  }

  function createFormulaState() {
    return { formulaTokens: [] };
  }

  function getSlotOrder(template) {
    const order = [];
    template.forEach(p => {
      if (p.t === 'coef' || p.t === 'blank') order.push({ type: p.t, id: p.id });
    });
    return order;
  }

  function CoefController({ container, template, values, onFocus, onChange }) {
    const order = getSlotOrder(template).filter(s => s.type === 'coef');

    function render(focusedId) {
      container.innerHTML = renderEquation(template, values, focusedId);
      container.querySelectorAll('[data-slot]').forEach(el => {
        el.onclick = e => {
          e.stopPropagation();
          onFocus(el.dataset.id);
          render(el.dataset.id);
        };
      });
    }

    function focusNextEmpty() {
      const next = order.find(s => !values.coef[s.id]);
      return next ? next.id : null;
    }

    function lastFilledId() {
      for (let i = order.length - 1; i >= 0; i--) {
        if (values.coef[order[i].id]) return order[i].id;
      }
      return null;
    }

    return {
      render,
      inputDigit(digit, focusedId) {
        let id = focusedId || focusNextEmpty();
        if (!id) return focusedId;
        values.coef[id] = digit;
        onChange();
        const next = focusNextEmpty();
        return next || id;
      },
      backspace(focusedId) {
        if (focusedId && values.coef[focusedId]) {
          values.coef[focusedId] = '';
        } else {
          const last = lastFilledId();
          if (last) values.coef[last] = '';
        }
        onChange();
        return focusedId || focusNextEmpty();
      },
      clearAll() {
        order.forEach(s => { values.coef[s.id] = ''; });
        onChange();
        return null;
      },
    };
  }

  function BlankController({ container, template, values, onFocus, onChange, smartContext }) {
    function render(focusedId) {
      container.innerHTML = renderEquation(template, values, focusedId);
      container.querySelectorAll('[data-slot]').forEach(el => {
        el.onclick = e => {
          e.stopPropagation();
          onFocus(el.dataset.id);
          render(el.dataset.id);
        };
      });
    }

    function getBlankIds() {
      return getSlotOrder(template).filter(s => s.type === 'blank').map(s => s.id);
    }

    function syncBlank(id) {
      const tokens = values.blankTokens?.[id];
      values.blank[id] = tokens?.length ? formulaSerialize(tokens) : '';
    }

    return {
      render,
      append(val, focusedId) {
        const ids = getBlankIds();
        let id = focusedId;
        if (!id) id = ids[0];
        if (!id) return null;
        if (!values.blankTokens[id]) values.blankTokens[id] = [];
        if (val === '\n') return id;
        formulaAppendToken(values.blankTokens[id], val, smartContext);
        syncBlank(id);
        onChange();
        return id;
      },
      backspace(focusedId) {
        const ids = getBlankIds();
        let id = focusedId || ids[ids.length - 1];
        if (!id) return null;
        if (!values.blankTokens[id]) values.blankTokens[id] = [];
        values.blankTokens[id].pop();
        syncBlank(id);
        onChange();
        return id;
      },
      clearAll() {
        getBlankIds().forEach(id => {
          values.blank[id] = '';
          values.blankTokens[id] = [];
        });
        onChange();
        return null;
      },
      clearFocused(focusedId) {
        if (focusedId) {
          values.blank[focusedId] = '';
          values.blankTokens[focusedId] = [];
        } else {
          getBlankIds().forEach(id => {
            values.blank[id] = '';
            values.blankTokens[id] = [];
          });
        }
        onChange();
        return null;
      },
    };
  }

  function FormulaController({ container, values, smartContext, onChange }) {
    const tokens = values.formulaTokens;

    function render() {
      const inner = tokens.length
        ? formulaRenderHtml(tokens)
        : '<span class="eq-placeholder">Nhập công thức...</span>';
      container.innerHTML =
        `<div class="formula-display">${inner}<span class="formula-cursor" aria-hidden="true">|</span></div>`;
    }

    return {
      render,
      append(val) {
        if (val === '\n') return null;
        formulaAppendToken(tokens, val, smartContext);
        onChange();
        render();
        return null;
      },
      backspace() {
        tokens.pop();
        onChange();
        render();
        return null;
      },
      clearAll() {
        tokens.length = 0;
        onChange();
        render();
        return null;
      },
      getSerialized() {
        return formulaSerialize(tokens);
      },
    };
  }

  function MixedController({ container, template, values, onFocus, onChange, smartContext }) {
    const coefCtrl = CoefController({ container, template, values, onFocus, onChange });
    const blankCtrl = BlankController({ container, template, values, onFocus, onChange, smartContext });

    return {
      render(focusedId) {
        container.innerHTML = renderEquation(template, values, focusedId);
        container.querySelectorAll('[data-slot]').forEach(el => {
          el.onclick = e => {
            e.stopPropagation();
            onFocus(el.dataset.id);
            this.render(el.dataset.id);
          };
        });
      },
      inputDigit(digit, focusedId) {
        const el = container.querySelector(`[data-id="${focusedId}"]`);
        if (el?.dataset.slot === 'coef') {
          return coefCtrl.inputDigit(digit, focusedId);
        }
        const next = coefCtrl.inputDigit(digit, null);
        if (next) return next;
        return focusedId;
      },
      append(val, focusedId) {
        const el = focusedId && container.querySelector(`[data-id="${focusedId}"]`);
        if (el?.dataset.slot === 'blank') return blankCtrl.append(val, focusedId);
        const firstBlank = getSlotOrder(template).find(s => s.type === 'blank');
        return blankCtrl.append(val, firstBlank?.id || null);
      },
      backspace(focusedId) {
        const el = focusedId && container.querySelector(`[data-id="${focusedId}"]`);
        if (el?.dataset.slot === 'blank' && values.blank[focusedId]) {
          return blankCtrl.backspace(focusedId);
        }
        if (el?.dataset.slot === 'coef' && values.coef[focusedId]) {
          return coefCtrl.backspace(focusedId);
        }
        if (values.blank[focusedId]) return blankCtrl.backspace(focusedId);
        return coefCtrl.backspace(focusedId);
      },
      clearAll() {
        coefCtrl.clearAll();
        blankCtrl.clearAll();
        return null;
      },
    };
  }

  function checkAnswer(q, values) {
    if (q.inputMode === 'formula') {
      const expected = q.correctAnswerNormalized || q.correctAnswer || '';
      const submitted = formulaSerialize(values.formulaTokens || []);
      return normalizeFormula(submitted) === normalizeFormula(expected);
    }

    const c = q.correct || {};
    if (c.coef) {
      for (const [id, ans] of Object.entries(c.coef)) {
        if ((values.coef[id] || '') !== ans) return false;
      }
    }
    if (c.blank) {
      for (const [id, ans] of Object.entries(c.blank)) {
        if (normalizeFormula(values.blank[id]) !== normalizeFormula(ans)) return false;
      }
    }
    return true;
  }

  function formatCorrectAnswer(q) {
    const parts = [];
    if (q.correct?.blank) {
      Object.values(q.correct.blank).forEach(v => parts.push(chemToHtml(v)));
    }
    if (q.correct?.coef) {
      parts.push('Hệ số: ' + Object.values(q.correct.coef).join(', '));
    }
    return parts.join(' · ') || '—';
  }

  return {
    chemToHtml,
    normalizeFormula,
    formulaSerialize,
    formulaRenderHtml,
    renderEquation,
    createInputState,
    createFormulaState,
    CoefController,
    BlankController,
    MixedController,
    FormulaController,
    checkAnswer,
    formatCorrectAnswer,
    DEFAULT_SMART_CONTEXT,
  };
})();
