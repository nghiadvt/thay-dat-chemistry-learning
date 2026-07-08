(function () {
  const lightbox = document.getElementById('kbPreviewLightbox');
  const titleEl = document.getElementById('kbPreviewLightboxTitle');
  const imgEl = document.getElementById('kbPreviewLightboxImg');

  function escapeAttr(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function openLightbox(src, name) {
    if (!lightbox || !titleEl || !imgEl || !src) return;

    titleEl.textContent = name || 'Preview bàn phím';
    imgEl.src = src;
    imgEl.alt = name ? `Preview ${name}` : 'Preview bàn phím';
    lightbox.hidden = false;
    document.body.classList.add('kb-preview-lightbox-open');
  }

  function closeLightbox() {
    if (!lightbox || !imgEl) return;

    lightbox.hidden = true;
    imgEl.removeAttribute('src');
    document.body.classList.remove('kb-preview-lightbox-open');
  }

  function initLightbox() {
    if (!lightbox) return;

    const closeBtn = lightbox.querySelector('.kb-preview-lightbox-close');
    const backdrop = lightbox.querySelector('.kb-preview-lightbox-backdrop');

    document.addEventListener('click', (event) => {
      const thumb = event.target.closest('.kb-preview-thumb');
      if (!thumb) return;
      openLightbox(thumb.dataset.previewSrc, thumb.dataset.previewName);
    });

    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    if (backdrop) backdrop.addEventListener('click', closeLightbox);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !lightbox.hidden) {
        closeLightbox();
      }
    });
  }

  function initKeyboardSelectPreview() {
    const select = document.getElementById('keyboard_id');
    const previewWrap = document.getElementById('kbSelectPreview');
    const dataEl = document.getElementById('kbSelectPreviewData');
    if (!select || !previewWrap || !dataEl) return;

    let keyboards = {};
    try {
      keyboards = JSON.parse(dataEl.textContent || '{}');
    } catch (error) {
      return;
    }

    function renderPreview(keyboardId) {
      const keyboard = keyboards[String(keyboardId)];
      if (!keyboard) {
        previewWrap.hidden = true;
        previewWrap.innerHTML = '';
        return;
      }

      previewWrap.hidden = false;

      if (keyboard.preview_url) {
        previewWrap.innerHTML =
          '<button type="button" class="kb-preview-thumb kb-preview-thumb--large"' +
          ' data-preview-src="' + escapeAttr(keyboard.preview_url) + '"' +
          ' data-preview-name="' + escapeAttr(keyboard.name) + '"' +
          ' title="Click để phóng to — ' + escapeAttr(keyboard.name) + '">' +
          '<img src="' + escapeAttr(keyboard.preview_url) + '" alt="Preview ' + escapeAttr(keyboard.name) + '">' +
          '</button>' +
          '<p class="kb-select-preview-hint">Click ảnh để xem phóng to</p>';
        return;
      }

      const editorLink = keyboard.editor_url
        ? '<a href="' + escapeAttr(keyboard.editor_url) + '">Mở editor</a> để tạo.'
        : 'Mở editor bàn phím để tạo preview.';

      previewWrap.innerHTML =
        '<p class="kb-select-preview-empty">Chưa có ảnh preview. ' + editorLink + '</p>';
    }

    select.addEventListener('change', () => renderPreview(select.value));
    renderPreview(select.value);
  }

  initLightbox();
  initKeyboardSelectPreview();
})();
