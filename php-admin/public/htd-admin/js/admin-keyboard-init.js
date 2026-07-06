(function () {
  const boot = window.ADMIN_BOOT && window.ADMIN_BOOT.keyboard;
  if (!boot || !boot.id) return;

  function start() {
    if (typeof initEditor !== 'function') {
      console.error('admin-keyboard-init: keyboard-editor.js not loaded');
      return;
    }
    initEditor();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
