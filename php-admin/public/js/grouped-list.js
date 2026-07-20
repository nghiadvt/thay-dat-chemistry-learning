/**
 * Danh sách dạng nhóm: mỗi nhóm là một <tbody> gập lại, bấm mở thì tải nội dung
 * bằng AJAX (20 record mỗi lần, kèm nút «Xem thêm»).
 *
 * Giữ nguyên một <table> duy nhất nên bộ chọn cột, chọn hàng loạt và menu thao tác
 * của trang vẫn hoạt động như với bảng phẳng.
 */
(function () {
  'use strict';

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function statusRow(section, text, className) {
    const tr = document.createElement('tr');
    tr.className = 'list-group-status ' + (className || '');
    const td = document.createElement('td');
    td.colSpan = Number(section.dataset.colspan) || 1;
    td.textContent = text;
    tr.appendChild(td);
    return tr;
  }

  function clearStatusRows(section) {
    section.querySelectorAll('.list-group-status, .list-group-more').forEach((el) => el.remove());
  }

  function renderMoreButton(section, nextOffset) {
    const tr = document.createElement('tr');
    tr.className = 'list-group-more';
    const td = document.createElement('td');
    td.colSpan = Number(section.dataset.colspan) || 1;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-secondary btn-sm';
    btn.textContent = 'Xem thêm';
    btn.addEventListener('click', function () {
      loadRows(section, nextOffset);
    });

    td.appendChild(btn);
    tr.appendChild(td);
    return tr;
  }

  function loadRows(section, offset) {
    if (section.dataset.loading === '1') return;
    section.dataset.loading = '1';

    clearStatusRows(section);
    section.appendChild(statusRow(section, 'Đang tải…', 'is-loading'));

    const url = new URL(section.dataset.rowsUrl, window.location.origin);
    url.searchParams.set('group_id', section.dataset.sectionKey);
    url.searchParams.set('offset', String(offset));

    fetch(url.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        clearStatusRows(section);

        const tpl = document.createElement('template');
        tpl.innerHTML = (data.html || '').trim();
        const rows = tpl.content.querySelectorAll('tr');
        rows.forEach(function (row) {
          section.appendChild(row);
        });

        const loaded = Number(section.dataset.loaded || 0) + rows.length;
        section.dataset.loaded = String(loaded);

        if (loaded === 0) {
          section.appendChild(statusRow(section, section.dataset.emptyText || 'Chưa có mục nào.'));
        } else if (data.has_more) {
          section.appendChild(renderMoreButton(section, data.next_offset));
        }

        // Menu thao tác, ô sửa chủ đề và cột đang ẩn phải áp dụng cho cả hàng vừa thêm.
        document.dispatchEvent(new CustomEvent('admin:rows-added', {
          detail: { table: section.closest('table') },
        }));
      })
      .catch(function () {
        clearStatusRows(section);
        const retry = statusRow(section, 'Không tải được. Bấm để thử lại.', 'is-error');
        retry.style.cursor = 'pointer';
        retry.addEventListener('click', function () {
          loadRows(section, offset);
        });
        section.appendChild(retry);
      })
      .finally(function () {
        section.dataset.loading = '0';
      });
  }

  function toggleSection(section) {
    const button = section.querySelector('[data-group-toggle]');
    const collapsed = section.classList.toggle('is-collapsed');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

    // Lần mở đầu tiên mới đi tải dữ liệu; mở lại sau đó dùng luôn nội dung đã có.
    if (!collapsed && section.dataset.rowsUrl && section.dataset.loaded === '0') {
      loadRows(section, 0);
    }
  }

  function init() {
    document.querySelectorAll('[data-group-section]').forEach(function (section) {
      const button = section.querySelector('[data-group-toggle]');
      if (!button || section.dataset.groupBound === '1') return;
      section.dataset.groupBound = '1';
      button.addEventListener('click', function () {
        toggleSection(section);
      });
    });
  }

  window.GroupedList = { init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
