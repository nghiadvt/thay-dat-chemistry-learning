(function () {
  const lightbox = document.getElementById('kbPreviewLightbox');
  if (!lightbox) return;

  const titleEl = document.getElementById('kbPreviewLightboxTitle');
  const imgEl = document.getElementById('kbPreviewLightboxImg');
  const closeBtn = lightbox.querySelector('.kb-preview-lightbox-close');
  const backdrop = lightbox.querySelector('.kb-preview-lightbox-backdrop');

  function openLightbox(src, name) {
    titleEl.textContent = name || 'Preview bàn phím';
    imgEl.src = src;
    imgEl.alt = name ? `Preview ${name}` : 'Preview bàn phím';
    lightbox.hidden = false;
    document.body.classList.add('kb-preview-lightbox-open');
  }

  function closeLightbox() {
    lightbox.hidden = true;
    imgEl.removeAttribute('src');
    document.body.classList.remove('kb-preview-lightbox-open');
  }

  document.querySelectorAll('.kb-preview-thumb').forEach((btn) => {
    btn.addEventListener('click', () => {
      openLightbox(btn.dataset.previewSrc, btn.dataset.previewName);
    });
  });

  closeBtn.addEventListener('click', closeLightbox);
  backdrop.addEventListener('click', closeLightbox);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !lightbox.hidden) {
      closeLightbox();
    }
  });
})();
