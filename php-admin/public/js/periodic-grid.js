/**
 * PeriodicGrid — renderer bảng tuần hoàn dùng chung cho admin.
 *
 * render(container, { elements, categories, mode, compact, showLegend })
 *   mode   : 'edit' | 'normal' | 'pro'
 *            - 'normal' = đúng thứ HS tài khoản THƯỜNG thấy (ẩn ô hidden, khoá ô Pro)
 *            - 'pro'    = HS Pro (mở khoá)
 *            - 'edit'   = xem-tất-cả để chỉnh (ô ẩn/pro vẫn hiện kèm nhãn)
 *   compact: true = cỡ thumbnail (không nhãn/không header) — dùng trên card.
 *   showLegend: mặc định !compact — chú giải nhóm nhúng trong khung trống (như HS).
 *
 * Luôn vẽ khung 118 ô từ PERIODIC_LAYOUT + overlay catalog (elements).
 * Ô không có trong catalog / bị ẩn (mode HS) → hiển thị mờ «ngoài chương trình».
 *
 * elements[]: { id, z, symbol, name_vi, mass, group, period, cat, lit, vis, pro }
 * categories: mảng [{ id, slug, name, color, deep }] hoặc map id → {…}
 */
window.PeriodicGrid = (function () {
  'use strict';

  var ROW_OF = { La: 10, Ac: 11 };
  var LOCK_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2.2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>';

  function normalizeCategories(categories) {
    var byId = {};
    var bySlug = {};
    var list = [];
    if (!categories) return { byId: byId, bySlug: bySlug, list: list };
    if (Array.isArray(categories)) {
      list = categories.slice();
    } else {
      Object.keys(categories).forEach(function (k) {
        var c = Object.assign({ id: isNaN(Number(k)) ? k : Number(k) }, categories[k]);
        list.push(c);
      });
    }
    list.forEach(function (c) {
      if (c.id != null) byId[c.id] = c;
      if (c.slug) bySlug[c.slug] = c;
    });
    return { byId: byId, bySlug: bySlug, list: list };
  }

  function catColors(cats, idOrSlug) {
    var c = cats.byId[idOrSlug] || cats.bySlug[idOrSlug];
    return c ? { color: c.color, deep: c.deep || c.deep_color || '#64748b' } : { color: '#94a3b8', deep: '#64748b' };
  }

  function gridRowOf(period) {
    if (period === 'La' || period === 'Ac') return ROW_OF[period];
    return Number(period) + 1;
  }

  function fmtMass(m) {
    if (m == null || m === '') return '';
    return (Math.round(Number(m) * 1000) / 1000).toString();
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function taughtCellHtml(el, cats, mode, compact) {
    var cc = catColors(cats, el.cat);
    var cls = ['pg-cell'];
    var locked = mode === 'normal' && el.pro;
    var hidden = !el.vis;

    if (hidden) cls.push('is-hidden');
    else if (!el.lit) cls.push('is-inactive');
    if (locked) cls.push('is-locked');
    if (el.pro) cls.push('has-pro');

    // Vị trí ưu tiên theo layout (f-block); fallback group/period trên catalog.
    var group = el.layoutGroup != null ? el.layoutGroup : el.group;
    var row = el.layoutRow != null ? el.layoutRow : gridRowOf(el.period);
    var style = 'grid-column:' + (group + 1) + ';grid-row:' + row +
      ';--pg-c:' + cc.color + ';--pg-d:' + cc.deep;

    var lock = locked
      ? '<span class="pg-lock" aria-hidden="true">' + LOCK_SVG + '</span>'
      : '';
    var proTag = (mode === 'edit' && el.pro)
      ? '<span class="pg-protag" aria-hidden="true">PRO</span>' : '';
    var hideTag = (mode === 'edit' && hidden)
      ? '<span class="pg-hidetag" aria-hidden="true">ẩn</span>' : '';

    var inner = '<span class="pg-z">' + el.z + '</span>' +
      '<span class="pg-sym">' + esc(el.symbol) + '</span>';
    if (!compact) {
      inner += '<span class="pg-name">' + esc(el.name_vi || '') + '</span>' +
        '<span class="pg-mass">' + fmtMass(el.mass) + '</span>';
    }

    var tag = compact ? 'span' : 'button';
    var attrs = 'class="' + cls.join(' ') + '" style="' + style + '" data-el="' + el.id + '" data-z="' + el.z + '"';
    if (tag === 'button') attrs = 'type="button" ' + attrs;

    return '<' + tag + ' ' + attrs + '>' + inner + lock + proTag + hideTag + '</' + tag + '>';
  }

  function untaughtCellHtml(row, cats, compact) {
    var cc = catColors(cats, row.category);
    var style = 'grid-column:' + (row.group + 1) + ';grid-row:' + gridRowOf(row.period) +
      ';--pg-c:' + cc.color + ';--pg-d:' + cc.deep;
    var inner = '<span class="pg-z">' + row.z + '</span>' +
      '<span class="pg-sym">' + esc(row.symbol) + '</span>';
    if (!compact) {
      inner += '<span class="pg-name">' + esc(row.nameEn) + '</span>' +
        '<span class="pg-mass">' + fmtMass(row.mass) + '</span>';
    }
    return '<span class="pg-cell is-untaught" style="' + style + '" data-z="' + row.z + '" aria-hidden="true">' +
      inner + '</span>';
  }

  function legendHtml(cats) {
    if (!cats.list.length) return '';
    var chips = cats.list.map(function (c) {
      return '<span class="pg-legend-chip"><i style="background:' + esc(c.color) + '"></i>' + esc(c.name || c.slug) + '</span>';
    }).join('');
    return '<div class="pg-legend" style="grid-column:4/14;grid-row:2/5">' + chips + '</div>';
  }

  function frameHtml(compact) {
    if (compact) return '';
    var html = '<span class="pg-corner"></span>';
    for (var g = 1; g <= 18; g++) {
      html += '<span class="pg-head" style="grid-column:' + (g + 1) + ';grid-row:1">' + g + '</span>';
    }
    for (var p = 1; p <= 7; p++) {
      html += '<span class="pg-period" style="grid-column:1;grid-row:' + (p + 1) + '">' + p + '</span>';
    }
    html += '<span class="pg-frow" style="grid-column:1;grid-row:10">La</span>';
    html += '<span class="pg-frow" style="grid-column:1;grid-row:11">Ac</span>';
    html += '<span class="pg-fref" style="grid-column:4;grid-row:7">57–71</span>';
    html += '<span class="pg-fref" style="grid-column:4;grid-row:8">89–103</span>';
    return html;
  }

  function render(container, opts) {
    if (!container) return;
    var mode = opts.mode || 'edit';
    var compact = !!opts.compact;
    var cats = normalizeCategories(opts.categories);
    var els = opts.elements || [];
    var layout = window.PERIODIC_LAYOUT || [];
    var showLegend = opts.showLegend != null ? !!opts.showLegend : !compact;

    var byZ = {};
    els.forEach(function (el) { byZ[el.z] = el; });

    var html = frameHtml(compact);
    if (showLegend) html += legendHtml(cats);

    layout.forEach(function (row) {
      var taught = byZ[row.z];
      var layoutRow = gridRowOf(row.period);

      // Mode HS: ô catalog bị ẩn → hiện như ngoài chương trình (đúng thứ HS thấy).
      var showAsTaught = taught && (mode === 'edit' || taught.vis);
      if (showAsTaught) {
        var merged = Object.assign({}, taught, {
          layoutGroup: row.group,
          layoutRow: layoutRow,
          // Ưu tiên màu/slug từ layout nếu catalog chưa gán nhóm.
          cat: taught.cat != null ? taught.cat : row.category,
        });
        html += taughtCellHtml(merged, cats, mode, compact);
      } else {
        html += untaughtCellHtml(row, cats, compact);
      }
    });

    container.className = 'pg-grid pg-mode-' + mode + (compact ? ' pg-grid--thumb' : ' pg-grid--full');
    container.innerHTML = html;
    return container;
  }

  return { render: render, fmtMass: fmtMass, normalizeCategories: normalizeCategories };
})();
