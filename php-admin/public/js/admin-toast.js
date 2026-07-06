/**
 * Admin toast notifications — top-right, không đẩy layout.
 */
window.AdminToast = (function () {
  let host = null;

  function ensureHost() {
    if (host) return host;
    host = document.getElementById('adminToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'adminToastHost';
      host.className = 'admin-toast-host';
      host.setAttribute('aria-live', 'polite');
      host.setAttribute('aria-atomic', 'false');
      document.body.appendChild(host);
    }
    return host;
  }

  function icon(type) {
    if (type === 'success') return '✓';
    if (type === 'error') return '✕';
    if (type === 'warning') return '!';
    return 'ℹ';
  }

  function show(message, type = 'success', duration = 4200) {
    const root = ensureHost();
    const el = document.createElement('div');
    el.className = `admin-toast admin-toast--${type}`;
    el.innerHTML = `<span class="admin-toast-icon" aria-hidden="true">${icon(type)}</span><span class="admin-toast-msg">${message}</span>`;
    root.appendChild(el);

    requestAnimationFrame(() => el.classList.add('is-visible'));

    const remove = () => {
      el.classList.remove('is-visible');
      el.classList.add('is-leaving');
      setTimeout(() => el.remove(), 280);
    };

    const timer = setTimeout(remove, duration);
    el.addEventListener('click', () => {
      clearTimeout(timer);
      remove();
    });

    return el;
  }

  return { show };
})();
