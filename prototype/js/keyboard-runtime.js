/**
 * Render keyboards.config from admin DB on student question screen.
 */
const HTDKeyboardRuntime = (function () {
  const ROW_HEIGHT = { S: 36, M: 44, L: 52 };
  const FONT_SIZE = { S: 13, M: 16, L: 18 };

  function render(container, config, onPress) {
    if (!container || !config?.rows?.length) return false;

    container.innerHTML = '';
    container.classList.add('dyn-kb');

    config.rows.forEach(row => {
      if (row.hidden) return;

      const rowEl = document.createElement('div');
      rowEl.className = `dyn-kb-row height-${row.height || 'M'}`;
      rowEl.style.setProperty('--row-gap', `${row.spacing ?? 4}px`);
      rowEl.style.setProperty('--row-pad', `${row.padding ?? 2}px`);
      if (row.background) rowEl.style.background = row.background;

      (row.keys || []).forEach(key => {
        if (key.type === 'empty') return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dyn-kb-key';
        if (key.type && key.type !== 'normal') btn.classList.add(`type-${key.type}`);
        btn.style.flex = `${key.width || 1} 1 0`;
        btn.style.minHeight = `${ROW_HEIGHT[row.height] || ROW_HEIGHT.M}px`;
        btn.style.fontSize = `${FONT_SIZE[key.fontSize] || FONT_SIZE.M}px`;
        if (key.background) btn.style.background = key.background;
        if (key.color) btn.style.color = key.color;
        if (key.border) btn.style.borderColor = key.border;
        if (key.radius != null) btn.style.borderRadius = `${key.radius}px`;
        btn.textContent = key.type === 'delete' ? key.text || '⌫' : key.text || '';
        btn.disabled = Boolean(key.disabled);

        btn.addEventListener('click', () => {
          if (btn.disabled) return;
          onPress(key);
        });

        rowEl.appendChild(btn);
      });

      container.appendChild(rowEl);
    });

    return true;
  }

  function clear(container) {
    if (!container) return;
    container.innerHTML = '';
    container.classList.remove('dyn-kb');
  }

  /** Khớp `testKeyInputValue()` trong keyboard-editor — ưu tiên `value`, fallback `text`. */
  function resolveKeyInputValue(key) {
    if (!key) return '';
    const type = key.type || 'normal';
    if (type === 'space') return ' ';
    if (type === 'send') return '\n';
    if (type === 'delete' || key.value === '⌫' || key.value === 'BACKSPACE') return '⌫';
    if (key.value === 'SEND') return '\n';
    if (key.value !== undefined && key.value !== null && String(key.value) !== '') {
      return String(key.value);
    }
    return String(key.text ?? '');
  }

  return { render, clear, resolveKeyInputValue };
})();
