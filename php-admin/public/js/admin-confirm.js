/**
 * AdminConfirm — modal xác nhận dùng chung, thay thế window.confirm().
 *
 *   AdminConfirm.show({ title, message, confirmText, cancelText, danger }) → Promise<boolean>
 *
 * Kèm interceptor cho form Blade: <form data-confirm="Thông điệp" data-confirm-danger="1">
 * (thay cho onsubmit="return confirm(...)").
 */
window.AdminConfirm = (function () {
  let overlay = null;
  let activeResolve = null;
  let lastFocused = null;

  function ensureDom() {
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.className = 'admin-confirm-overlay';
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="admin-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle">' +
      '  <h3 class="admin-confirm-title" id="adminConfirmTitle"></h3>' +
      '  <p class="admin-confirm-message"></p>' +
      '  <div class="admin-confirm-actions">' +
      '    <button type="button" class="btn btn-secondary admin-confirm-cancel"></button>' +
      '    <button type="button" class="btn admin-confirm-ok"></button>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) settle(false);
    });
    overlay.querySelector('.admin-confirm-cancel').addEventListener('click', () => settle(false));
    overlay.querySelector('.admin-confirm-ok').addEventListener('click', () => settle(true));
    overlay.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        settle(false);
      } else if (e.key === 'Tab') {
        // focus trap giữa 2 nút
        const cancel = overlay.querySelector('.admin-confirm-cancel');
        const ok = overlay.querySelector('.admin-confirm-ok');
        e.preventDefault();
        (document.activeElement === ok ? cancel : ok).focus();
      }
    });
    return overlay;
  }

  function settle(result) {
    if (!overlay || overlay.hidden) return;
    overlay.hidden = true;
    document.body.classList.remove('admin-confirm-open');
    const resolve = activeResolve;
    activeResolve = null;
    lastFocused?.focus?.();
    lastFocused = null;
    if (resolve) resolve(result);
  }

  function show(opts = {}) {
    ensureDom();
    // nếu đang mở modal khác → hủy modal cũ
    if (activeResolve) settle(false);

    overlay.querySelector('.admin-confirm-title').textContent = opts.title || 'Xác nhận';
    overlay.querySelector('.admin-confirm-message').textContent = opts.message || 'Bạn có chắc chắn?';
    overlay.querySelector('.admin-confirm-cancel').textContent = opts.cancelText || 'Hủy';
    const ok = overlay.querySelector('.admin-confirm-ok');
    ok.textContent = opts.confirmText || 'Đồng ý';
    ok.className = 'btn admin-confirm-ok ' + (opts.danger ? 'btn-danger' : 'btn-primary');

    lastFocused = document.activeElement;
    overlay.hidden = false;
    document.body.classList.add('admin-confirm-open');
    ok.focus();

    return new Promise((resolve) => {
      activeResolve = resolve;
    });
  }

  // Interceptor form data-confirm (capture để chặn trước handler khác)
  document.addEventListener(
    'submit',
    (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      const message = form.dataset.confirm;
      if (!message || form.dataset.confirmed === '1') return;
      e.preventDefault();
      e.stopImmediatePropagation();
      show({
        title: form.dataset.confirmTitle || 'Xác nhận',
        message,
        confirmText: form.dataset.confirmOk || 'Đồng ý',
        danger: form.dataset.confirmDanger === '1',
      }).then((yes) => {
        if (!yes) return;
        form.dataset.confirmed = '1';
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
        // reset để lần sau vẫn hỏi (trường hợp submit AJAX/không điều hướng)
        setTimeout(() => { delete form.dataset.confirmed; }, 0);
      });
    },
    true
  );

  return { show };
})();
