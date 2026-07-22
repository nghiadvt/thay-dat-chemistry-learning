/* Theme màn hình học sinh — do GIÁO VIÊN chọn từ host panel, HS không tự đổi.
 * Server gửi theme qua room_joined / theme_update; localStorage chỉ là cache
 * để giữ theme qua reload giữa buổi chơi (tránh chớp giao diện mặc định).
 * Áp dụng bằng attribute html[data-student-theme]; CSS: css/student-themes.css.
 * 'default' = candy hiện tại (không set attribute).
 */
window.HTDTheme = (function () {
  'use strict';

  var STORAGE_KEY = 'htd_student_theme';
  var THEMES = [
    { id: 'default', label: 'Candy trời mây' },
    { id: 'lab', label: '🧪 Phòng thí nghiệm' },
    { id: 'galaxy', label: '🌌 Vũ trụ' },
    { id: 'arcade', label: '🕹️ Arcade 8-bit' },
    { id: 'chalk', label: '🧑‍🏫 Bảng phấn' },
  ];

  function isValid(id) {
    for (var i = 0; i < THEMES.length; i++) {
      if (THEMES[i].id === id) return true;
    }
    return false;
  }

  function get() {
    try {
      var t = localStorage.getItem(STORAGE_KEY);
      return isValid(t) ? t : 'default';
    } catch (e) {
      return 'default';
    }
  }

  function apply(id) {
    if (id === 'default') {
      document.documentElement.removeAttribute('data-student-theme');
    } else {
      document.documentElement.setAttribute('data-student-theme', id);
    }
  }

  /** Gọi khi server báo theme (room_joined / theme_update). */
  function set(id) {
    if (!isValid(id)) return;
    try { localStorage.setItem(STORAGE_KEY, id); } catch (e) { /* private mode */ }
    apply(id);
  }

  apply(get());

  return { get: get, set: set, apply: apply, themes: THEMES };
})();
