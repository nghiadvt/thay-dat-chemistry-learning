/**
 * Ô chọn nhóm: bật/tắt ô nhập tên nhóm mới ngay trong form.
 * Khi đang nhập nhóm mới thì vô hiệu hóa select để tránh gửi lẫn hai giá trị.
 */
(function () {
  'use strict';

  function setup(wrap) {
    var select = wrap.querySelector('[data-group-select-input]');
    var newBox = wrap.querySelector('[data-group-new]');
    var newInput = wrap.querySelector('[data-group-new-input]');
    var toggleBtn = wrap.querySelector('[data-group-new-toggle]');
    var cancelBtn = wrap.querySelector('[data-group-new-cancel]');

    if (!select || !newBox || !newInput || !toggleBtn || !cancelBtn) return;

    function showNew() {
      newBox.hidden = false;
      newInput.disabled = false;
      toggleBtn.hidden = true;
      select.disabled = true;
      newInput.focus();
    }

    function hideNew() {
      newBox.hidden = true;
      newInput.disabled = true;
      newInput.value = '';
      toggleBtn.hidden = false;
      select.disabled = false;
    }

    toggleBtn.addEventListener('click', showNew);
    cancelBtn.addEventListener('click', hideNew);

    // Enter trong ô tên nhóm không được submit cả form
    newInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });

    // Giữ trạng thái sau khi validate lỗi và quay lại form
    if (newInput.value.trim() !== '') showNew();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-group-select]').forEach(setup);
  });
})();
