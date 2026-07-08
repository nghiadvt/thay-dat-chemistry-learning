(function () {
  'use strict';

  const STORAGE_KEY = 'htd_admin_sidebar_collapsed';

  function init() {
    const shell = document.getElementById('adminShell');
    if (!shell) return;

    const toggles = document.querySelectorAll('[data-admin-sidebar-toggle]');

    function isCollapsed() {
      return shell.classList.contains('sidebar-collapsed');
    }

    function setCollapsed(collapsed) {
      shell.classList.toggle('sidebar-collapsed', collapsed);
      document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
      toggles.forEach((btn) => {
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
      try {
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
      } catch (_) {}
    }

    function toggle() {
      setCollapsed(!isCollapsed());
    }

    toggles.forEach((btn) => btn.addEventListener('click', toggle));

    try {
      // Trang host: thu gọn sidebar để rộng màn hình — không ghi localStorage
      if (document.body.classList.contains('admin-body--session-host')) {
        shell.classList.add('sidebar-collapsed');
        document.body.classList.add('admin-sidebar-collapsed');
        toggles.forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
      } else if (localStorage.getItem(STORAGE_KEY) === '1') {
        setCollapsed(true);
      }
    } catch (_) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
