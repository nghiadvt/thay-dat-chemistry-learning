/**
 * admin-loading — trạng thái đang xử lý cho form.
 *
 * Cách dùng: <form data-loading> — khi submit hợp lệ, nút submit bị khóa
 * và hiện spinner (tránh double-submit + có phản hồi thị giác).
 * Tự áp dụng cho MỌI form method POST trong admin trừ form đánh dấu data-no-loading.
 */
(function () {
  function markLoading(form) {
    if (form.dataset.loadingActive === '1') return;
    form.dataset.loadingActive = '1';
    const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])');
    buttons.forEach((btn) => {
      btn.disabled = true;
      btn.classList.add('is-loading');
      if (btn.tagName === 'BUTTON' && !btn.querySelector('.admin-btn-spinner')) {
        const sp = document.createElement('span');
        sp.className = 'admin-btn-spinner';
        sp.setAttribute('aria-hidden', 'true');
        btn.prepend(sp);
      }
    });
    // nếu 8s vẫn còn trang (submit lỗi/AJAX) → mở khóa lại
    setTimeout(() => {
      form.dataset.loadingActive = '';
      buttons.forEach((btn) => {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.querySelector('.admin-btn-spinner')?.remove();
      });
    }, 8000);
  }

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-loading')) return;
    const isPost = (form.method || '').toUpperCase() === 'POST';
    if (!isPost && !form.hasAttribute('data-loading')) return;
    // đợi mọi handler khác chạy xong — nếu có preventDefault (AJAX/AdminConfirm) thì bỏ qua
    setTimeout(() => {
      if (e.defaultPrevented) return;
      markLoading(form);
    }, 0);
  });
})();
