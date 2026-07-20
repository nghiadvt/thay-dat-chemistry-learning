/* ElementsModule — «Đọc nguyên tố»
 *
 * - Bảng tuần hoàn đầy đủ 118 ô (7 chu kỳ + 2 hàng Lanthanide/Actinide).
 *   45 nguyên tố trong chương trình = ô sáng, bấm được → mở thẻ chi tiết lật 3D.
 *   73 ô còn lại chỉ hiển thị mờ để HS thấy đúng vị trí nhóm/chu kỳ.
 * - Chỉ đọc tên IUPAC tiếng Anh qua Web Speech API. KHÔNG đọc tên tiếng Việt:
 *   giọng vi-VN trên thiết bị HS phát âm tên hóa chất sai nhiều hơn là giúp.
 * - Chế độ luyện tập «Nghe & đoán»: máy đọc tên IUPAC → chọn 1 trong 4 nguyên tố, 10 vòng,
 *   tổng kết 1–3 sao, best score lưu localStorage.
 */
window.ElementsModule = (function () {
  'use strict';

  var ELS = window.HTD_ELEMENTS || [];
  var CATS = window.HTD_ELEMENT_CATEGORIES || {};
  var LAYOUT = window.HTD_PERIODIC_TABLE || [];
  var BEST_KEY = 'htd_elements_best';
  var MASTERY_KEY = 'htd_el_mastery';
  var PRACTICE_ROUNDS = 10;
  var MASTERY_FULL = 3; // trả lời đúng 3 lần = coi như đã thuộc

  var built = false;
  var detailIdx = 0;
  var filter = 'all';     // 'all' | 'priority' | 'unlearned'

  /* Tra ngược từ số hiệu về nguyên tố trong chương trình (nếu có dạy). */
  var idxByZ = {};
  ELS.forEach(function (el, i) { idxByZ[el.z] = i; });

  /* ── Mức thuộc bài (localStorage, tách khỏi điểm luyện tập) ─── */
  var mastery = {};
  try { mastery = JSON.parse(localStorage.getItem(MASTERY_KEY) || '{}') || {}; } catch (e) { mastery = {}; }

  function masteryOf(z) { return Number(mastery[z] || 0); }
  function isLearned(z) { return masteryOf(z) >= MASTERY_FULL; }

  function bumpMastery(z) {
    mastery[z] = Math.min(masteryOf(z) + 1, MASTERY_FULL);
    try { localStorage.setItem(MASTERY_KEY, JSON.stringify(mastery)); } catch (e) {}
  }

  function matchesFilter(el) {
    if (filter === 'priority') return el.priority === 1;
    if (filter === 'unlearned') return !isLearned(el.z);
    return true;
  }

  function masteryBar(z) {
    var pct = Math.round((masteryOf(z) / MASTERY_FULL) * 100);
    return '<span class="el-mastery"><i style="width:' + pct + '%"></i></span>';
  }

  /* ── Speech: chỉ tên IUPAC tiếng Anh ────────────────────────── */
  var hasSpeech = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  var enVoice = null;

  function pickVoices() {
    if (!hasSpeech) return;
    var voices = window.speechSynthesis.getVoices() || [];
    enVoice = voices.find(function (v) { return /^en[-_](US|GB)/i.test(v.lang); })
      || voices.find(function (v) { return /^en([-_]|$)/i.test(v.lang); })
      || null;
  }
  if (hasSpeech) {
    pickVoices();
    window.speechSynthesis.addEventListener?.('voiceschanged', pickVoices);
    if (window.speechSynthesis.onvoiceschanged === null) {
      window.speechSynthesis.onvoiceschanged = pickVoices;
    }
  }

  function speakElement(el) {
    if (!hasSpeech || !el || !el.nameEn) return;
    window.speechSynthesis.cancel(); // Android Chrome hay kẹt queue
    var u = new SpeechSynthesisUtterance(el.nameEn);
    u.rate = 0.9;
    if (enVoice) u.voice = enVoice;
    u.lang = enVoice ? enVoice.lang : 'en-US';
    window.speechSynthesis.speak(u);
  }

  function sfxLocal(name) {
    if (window.HTDSound) HTDSound.play(name);
  }

  /* ── Vào màn: dựng bảng + legend ────────────────────────────── */
  function enter() {
    closeDetail();
    closeMenu();
    lockLandscape();
    maybeShowRotateGate();
    if (built) return;
    built = true;

    buildTable();
    bindToolbar();
    applyFilter();
  }

  /* ── Menu trượt màn ngang: loa/luyện tập/filter dồn vào đây ────────
   * Đóng/mở qua class trên <body> (không phải trên .elements-screen) vì
   * #soundToggle nằm ngoài #app, cần chung 1 "cờ" để CSS đưa nó vào đúng
   * chỗ trong menu. Luôn đóng menu khi rời màn hoặc vào lại màn (enter()),
   * tránh kẹt trạng thái mở khi chuyển màn/xoay lại portrait. */
  function openMenu() { document.body.classList.add('el-menu-open'); }
  function closeMenu() { document.body.classList.remove('el-menu-open'); }
  function toggleMenu() { document.body.classList.toggle('el-menu-open'); }

  /* ── Tự xoay ngang khi vào màn, tự hết khi thoát ──────────────────
   * screen.orientation.lock() chỉ hoạt động khi tài liệu đang fullscreen
   * (trừ PWA cài đặt ở chế độ standalone) và KHÔNG được hỗ trợ trên iOS
   * Safari — nên luôn bọc try/catch, coi rotate gate (toast mời xoay tay)
   * là phương án dự phòng khi trình duyệt không cho tự xoay. */
  function lockLandscape() {
    if (!window.screen || !screen.orientation || !screen.orientation.lock) return;
    var root = document.documentElement;
    var goFullscreen = (!document.fullscreenElement && root.requestFullscreen)
      ? root.requestFullscreen().catch(function () {})
      : Promise.resolve();
    goFullscreen.then(function () {
      screen.orientation.lock('landscape').catch(function () {});
    });
  }

  function exitLandscape() {
    closeMenu();
    if (window.screen && screen.orientation && screen.orientation.unlock) {
      try { screen.orientation.unlock(); } catch (e) {}
    }
    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen().catch(function () {});
    }
  }

  /* ── Bảng tuần hoàn đầy đủ ──────────────────────────────────────
   * 18 cột × 7 chu kỳ + 2 hàng f-block (Lanthanide/Actinide) tách dưới, đúng
   * cách bảng chuẩn vẫn in. Ô nào có trong chương trình (HTD_ELEMENTS) thì sáng
   * và bấm được; ô còn lại mờ, không bấm — HS vẫn thấy đúng vị trí nhóm/chu kỳ.
   * Hàng lưới: 1 = header nhóm, 2–8 = chu kỳ 1–7, 9 = khoảng đệm, 10–11 = f-block. */
  var ROW_OF = { 'La': 10, 'Ac': 11 };

  function buildTable() {
    var table = document.getElementById('elTable');
    if (!table) return;

    var html = '<span class="el-pt-corner" aria-hidden="true"></span>';
    for (var g = 1; g <= 18; g++) {
      html += '<span class="el-pt-group" style="grid-column:' + (g + 1) + ';grid-row:1">' + g + '</span>';
    }
    for (var p = 1; p <= 7; p++) {
      html += '<span class="el-pt-period" style="grid-column:1;grid-row:' + (p + 1) + '">' + p + '</span>';
    }
    html += '<span class="el-pt-frow" style="grid-column:1;grid-row:10">La</span>';
    html += '<span class="el-pt-frow" style="grid-column:1;grid-row:11">Ac</span>';

    // Ô dẫn 57–71 / 89–103 ở nhóm 3 chu kỳ 6, 7 — trỏ xuống hai hàng f-block.
    html += '<span class="el-pt-fref" style="grid-column:4;grid-row:7">57–71</span>';
    html += '<span class="el-pt-fref" style="grid-column:4;grid-row:8">89–103</span>';

    // Chú giải màu nhóm nhét vào khoảng trống nhóm 3–12, chu kỳ 1–3 (luôn
    // rỗng vì các nguyên tố đó là kim loại chuyển tiếp, chưa xuất hiện tới
    // chu kỳ 4) — đỡ tốn thêm hàng riêng phía trên, nhường chiều cao cho bảng.
    html += '<div class="el-table-legend" style="grid-column:4/14;grid-row:2/5">' +
      Object.keys(CATS).map(function (key) {
        return '<span class="el-legend-chip"><i style="background:' + CATS[key].color + '"></i>' + CATS[key].label + '</span>';
      }).join('') +
      '</div>';

    LAYOUT.forEach(function (row) {
      var z = row[0], symbol = row[1], nameEn = row[2], mass = row[3];
      var cat = CATS[row[4]] || { color: '#8B5CF6', deep: '#6D28D9' };
      var gridRow = ROW_OF[row[6]] || (Number(row[6]) + 1);
      var style = 'grid-column:' + (row[5] + 1) + ';grid-row:' + gridRow +
        ';--el-c:' + cat.color + ';--el-d:' + cat.deep;

      var idx = idxByZ[z];
      var taught = idx !== undefined;
      var label = taught ? ELS[idx].nameVi : nameEn;

      if (!taught) {
        html += '<span class="el-pt-cell is-untaught" style="' + style + '" ' +
          'aria-label="' + symbol + ' — ' + nameEn + ', số hiệu ' + z + ' (ngoài chương trình)">' +
          '<span class="el-pt-z">' + z + '</span>' +
          '<span class="el-pt-symbol">' + symbol + '</span>' +
          '<span class="el-pt-name">' + nameEn + '</span>' +
          '<span class="el-pt-mass">' + mass + '</span>' +
          '</span>';
        return;
      }

      var el = ELS[idx];
      html += '<button type="button" class="el-pt-cell' + (el.priority === 1 ? ' is-priority' : '') + '" ' +
        'style="' + style + '" data-idx="' + idx + '" data-z="' + z + '" ' +
        'aria-label="' + symbol + ' — ' + label + ', số hiệu ' + z + '">' +
        '<span class="el-pt-z">' + z + '</span>' +
        '<span class="el-pt-symbol">' + symbol + '</span>' +
        '<span class="el-pt-name">' + label + '</span>' +
        '<span class="el-pt-mass">' + mass + '</span>' +
        masteryBar(z) +
        '</button>';
    });

    table.innerHTML = html;
    table.querySelectorAll('button.el-pt-cell').forEach(function (cell) {
      cell.addEventListener('click', function () { tapCell(cell); });
    });
  }

  /* Chạm ô = mở thẻ chi tiết luôn. Trước đây chạm lần 1 chỉ để đọc tên tiếng
   * Việt, giờ bỏ giọng vi nên không còn lý do bắt HS chạm hai lần. */
  function tapCell(cell) {
    var table = document.getElementById('elTable');
    if (table) {
      table.querySelectorAll('.el-pt-cell.is-selected').forEach(function (c) { c.classList.remove('is-selected'); });
    }
    cell.classList.add('is-selected');
    sfxLocal('tap');
    openDetail(Number(cell.dataset.idx));
  }

  /* ── Toolbar: lọc ────────────────────────────────────────────── */
  function bindToolbar() {
    var select = document.getElementById('elFilterSelect');
    if (select) {
      select.addEventListener('change', function () {
        filter = select.value;
        sfxLocal('tap');
        applyFilter();
      });
    }
    var fab = document.getElementById('elMenuFab');
    if (fab) fab.addEventListener('click', function () { sfxLocal('tap'); toggleMenu(); });
    var overlay = document.getElementById('elMenuOverlay');
    if (overlay) overlay.addEventListener('click', closeMenu);
    var closeBtn = document.getElementById('elMenuClose');
    if (closeBtn) closeBtn.addEventListener('click', function () { sfxLocal('tap'); closeMenu(); });

    var dismiss = document.getElementById('elRotateDismiss');
    if (dismiss) {
      dismiss.addEventListener('click', function () {
        rotateDismissed = true;
        updateRotateGate();
      });
    }
  }

  /* ── Cổng xoay ngang ────────────────────────────────────────────
   * Bảng 18 cột không thể đọc được ở màn dọc, nên vào màn là che toàn màn hình
   * và mời HS xoay ngang. Vẫn cho bỏ qua (điện thoại khóa xoay, máy tính bảng
   * dựng đứng…) — che thì che, đừng nhốt HS lại. */
  var rotateDismissed = false;

  function isPortrait() {
    return window.matchMedia('(orientation: portrait)').matches;
  }

  function maybeShowRotateGate() {
    rotateDismissed = false; // mỗi lần vào lại màn thì nhắc lại
    updateRotateGate();
  }

  function updateRotateGate() {
    var gate = document.getElementById('elRotateGate');
    if (!gate) return;
    gate.hidden = rotateDismissed || !isPortrait();
  }

  function applyFilter() {
    // Làm mờ thay vì ẩn — ẩn đi sẽ phá vỡ hình dáng bảng tuần hoàn.
    document.querySelectorAll('button.el-pt-cell').forEach(function (cell) {
      var el = ELS[Number(cell.dataset.idx)];
      cell.classList.toggle('is-dim', !matchesFilter(el));
    });
    updateProgress();
  }

  function updateProgress() {
    var out = document.getElementById('elProgress');
    if (!out) return;
    var core = ELS.filter(function (el) { return el.priority === 1; });
    var done = core.filter(function (el) { return isLearned(el.z); }).length;
    out.textContent = 'Đã thuộc ' + done + '/' + core.length + ' nguyên tố trọng tâm';
  }

  /* ── Thẻ chi tiết lật 3D ────────────────────────────────────── */
  function openDetail(idx) {
    detailIdx = (idx + ELS.length) % ELS.length;
    var el = ELS[detailIdx];
    var cat = CATS[el.category] || { label: '', color: '#8B5CF6', deep: '#6D28D9' };
    var overlay = document.getElementById('elDetailOverlay');
    var inner = document.getElementById('elDetailInner');
    if (!overlay || !inner) return;

    var speakBtns = hasSpeech
      ? '<div class="el-speak-row">' +
          '<button type="button" class="el-speak-btn el-speak-en">🔊 ' + el.nameEn + '</button>' +
        '</div>'
      : '<p class="el-no-speech">Thiết bị không hỗ trợ đọc to 😢</p>';

    inner.innerHTML =
      '<div class="el-card" style="--el-c:' + cat.color + ';--el-d:' + cat.deep + '">' +
        '<div class="el-card-inner">' +
          '<div class="el-card-face el-card-front">' +
            '<span class="el-card-z">' + el.z + '</span>' +
            '<span class="el-card-symbol">' + el.symbol + '</span>' +
            '<span class="el-card-mass">' + el.mass + '</span>' +
            '<span class="el-card-cat-chip el-card-cat-chip-front">' + cat.label + '</span>' +
            '<span class="el-card-hint">Chạm để lật thẻ ↻</span>' +
          '</div>' +
          '<div class="el-card-face el-card-back">' +
            '<span class="el-card-back-symbol">' + el.symbol + '</span>' +
            '<h3 class="el-card-name-vi">' + el.nameVi + '</h3>' +
            '<p class="el-card-name-en">' + el.nameEn + '</p>' +
            '<div class="el-card-facts">' +
              '<span>Số hiệu: <strong>' + el.z + '</strong></span>' +
              '<span>Khối lượng: <strong>' + el.mass + '</strong></span>' +
            '</div>' +
            '<span class="el-card-cat-chip">' + cat.label + '</span>' +
          '</div>' +
        '</div>' +
      '</div>' +
      speakBtns +
      '<div class="el-detail-nav">' +
        '<button type="button" class="el-nav-btn el-nav-prev">◀ Trước</button>' +
        '<button type="button" class="el-nav-btn el-nav-close">Đóng</button>' +
        '<button type="button" class="el-nav-btn el-nav-next">Sau ▶</button>' +
      '</div>';

    overlay.hidden = false;
    sfxLocal('flip');

    var card = inner.querySelector('.el-card');
    card.addEventListener('click', function () {
      card.classList.toggle('flipped');
      sfxLocal('flip');
    });
    inner.querySelector('.el-speak-en')?.addEventListener('click', function (e) {
      e.stopPropagation();
      sfxLocal('tap');
      speakElement(el);
    });
    inner.querySelector('.el-nav-prev').addEventListener('click', function () { sfxLocal('tap'); openDetail(detailIdx - 1); });
    inner.querySelector('.el-nav-next').addEventListener('click', function () { sfxLocal('tap'); openDetail(detailIdx + 1); });
    inner.querySelector('.el-nav-close').addEventListener('click', function () { sfxLocal('pop'); closeDetail(); });
    overlay.onclick = function (e) { if (e.target === overlay) closeDetail(); };
  }

  function closeDetail() {
    var overlay = document.getElementById('elDetailOverlay');
    if (overlay) overlay.hidden = true;
    if (hasSpeech) window.speechSynthesis.cancel();
    // Ô vẫn giữ viền sáng sau khi đóng thẻ để HS không mất dấu vị trí vừa xem.
  }

  /* ── Luyện tập «Nghe & đoán» ────────────────────────────────── */
  var pr = null;

  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  function startPractice() {
    if (!hasSpeech) {
      if (typeof showCartoonToast === 'function') {
        showCartoonToast('Thiết bị không hỗ trợ đọc to nên chưa luyện tập được.', '😢');
      }
      return;
    }
    pr = {
      rounds: shuffle(ELS).slice(0, PRACTICE_ROUNDS),
      current: 0,
      score: 0,
      locked: false,
    };
    sfxLocal('countdown-go');
    showScreen('element-practice');
    renderRound();
  }

  function buildChoices(answer) {
    var sameCat = ELS.filter(function (e) { return e.category === answer.category && e.z !== answer.z; });
    var others = ELS.filter(function (e) { return e.category !== answer.category; });
    var pool = shuffle(sameCat).slice(0, 2).concat(shuffle(others));
    var distractors = [];
    for (var i = 0; i < pool.length && distractors.length < 3; i++) {
      if (pool[i].z !== answer.z && !distractors.some(function (d) { return d.z === pool[i].z; })) {
        distractors.push(pool[i]);
      }
    }
    return shuffle([answer].concat(distractors));
  }

  function renderRound() {
    var body = document.getElementById('prBody');
    var progress = document.getElementById('prProgress');
    if (!body || !pr) return;
    var answer = pr.rounds[pr.current];
    var choices = buildChoices(answer);
    pr.answer = answer;
    pr.locked = false;

    if (progress) progress.textContent = (pr.current + 1) + '/' + PRACTICE_ROUNDS + ' · ⭐ ' + pr.score;

    body.innerHTML =
      '<p class="pr-instruction">Nghe tên tiếng Anh (IUPAC) và chọn đúng nguyên tố</p>' +
      '<button type="button" class="pr-speaker" id="prSpeaker" aria-label="Nghe lại">' +
        '<svg class="icon" aria-hidden="true"><use href="#i-speaker"/></svg>' +
      '</button>' +
      '<div class="pr-choices">' +
      choices.map(function (c) {
        var cat = CATS[c.category] || { color: '#8B5CF6', deep: '#6D28D9' };
        return '<button type="button" class="pr-choice" data-z="' + c.z + '" ' +
          'style="--el-c:' + cat.color + ';--el-d:' + cat.deep + '">' +
          '<span class="pr-choice-symbol">' + c.symbol + '</span>' +
          '<span class="pr-choice-z">Z=' + c.z + '</span>' +
          '<span class="pr-choice-name" hidden>' + c.nameVi + ' — ' + c.nameEn + '</span>' +
          '</button>';
      }).join('') +
      '</div>';

    document.getElementById('prSpeaker').addEventListener('click', function () {
      sfxLocal('tap');
      speakElement(answer);
    });
    body.querySelectorAll('.pr-choice').forEach(function (btn) {
      btn.addEventListener('click', function () { pickChoice(btn); });
    });

    setTimeout(function () { speakElement(answer); }, 450);
  }

  function pickChoice(btn) {
    if (!pr || pr.locked) return;
    pr.locked = true;
    var z = Number(btn.dataset.z);
    var correct = z === pr.answer.z;
    var body = document.getElementById('prBody');

    body.querySelectorAll('.pr-choice').forEach(function (b) {
      var name = b.querySelector('.pr-choice-name');
      if (name) name.hidden = false;
      if (Number(b.dataset.z) === pr.answer.z) b.classList.add('is-answer');
    });

    if (correct) {
      pr.score += 1;
      bumpMastery(pr.answer.z);
      btn.classList.add('is-correct');
      sfxLocal('correct');
      if (window.HTDFx) HTDFx.burstAtElement(btn, { count: 16 });
    } else {
      btn.classList.add('is-wrong');
      btn.classList.add('wobble-sad');
      sfxLocal('wrong');
      if (window.HTDFx) HTDFx.shake();
    }

    setTimeout(function () {
      pr.current += 1;
      if (pr.current >= PRACTICE_ROUNDS) renderSummary();
      else renderRound();
    }, correct ? 1000 : 1700);
  }

  function renderSummary() {
    var body = document.getElementById('prBody');
    var progress = document.getElementById('prProgress');
    if (!body || !pr) return;
    var stars = pr.score >= 9 ? 3 : pr.score >= 6 ? 2 : pr.score >= 3 ? 1 : 0;
    var best = 0;
    try { best = Number(localStorage.getItem(BEST_KEY) || 0); } catch (e) {}
    var isRecord = pr.score > best;
    if (isRecord) {
      try { localStorage.setItem(BEST_KEY, String(pr.score)); } catch (e) {}
      best = pr.score;
    }
    if (progress) progress.textContent = 'Xong!';

    var starHtml = [1, 2, 3].map(function (i) {
      return '<svg class="icon pr-star' + (i <= stars ? ' lit' : '') + '" style="animation-delay:' + (i * 220) + 'ms" aria-hidden="true"><use href="#i-star"/></svg>';
    }).join('');

    body.innerHTML =
      '<div class="pr-summary">' +
        '<div class="pr-stars">' + starHtml + '</div>' +
        '<h3 class="pr-summary-title">' + (stars >= 3 ? 'Tuyệt đỉnh!' : stars === 2 ? 'Giỏi lắm!' : stars === 1 ? 'Khá đấy!' : 'Cố lên nào!') + '</h3>' +
        '<p class="pr-summary-score">Đúng <strong>' + pr.score + '/' + PRACTICE_ROUNDS + '</strong> câu' +
          (isRecord ? ' · 🏆 Kỷ lục mới!' : ' · Kỷ lục: ' + best) + '</p>' +
        '<button type="button" class="btn-primary pr-again">Chơi lại</button>' +
        '<button type="button" class="btn-secondary pr-back">Về bảng nguyên tố</button>' +
      '</div>';

    sfxLocal(stars >= 2 ? 'fanfare' : 'pop');
    if (stars >= 2 && window.HTDFx) HTDFx.sparkleRain({ count: 40 });

    body.querySelector('.pr-again').addEventListener('click', function () { startPractice(); });
    body.querySelector('.pr-back').addEventListener('click', function () { exitPractice(); });
  }

  function exitPractice() {
    if (hasSpeech) window.speechSynthesis.cancel();
    pr = null;
    refreshMastery();
    showScreen('elements');
  }

  /* Luyện tập vừa làm thay đổi mức thuộc bài → vẽ lại thanh tiến độ trên bảng. */
  function refreshMastery() {
    document.querySelectorAll('button.el-pt-cell').forEach(function (cell) {
      var bar = cell.querySelector('.el-mastery i');
      if (!bar) return;
      var z = Number(cell.dataset.z);
      bar.style.width = Math.round((masteryOf(z) / MASTERY_FULL) * 100) + '%';
    });
    applyFilter();
  }

  window.matchMedia('(orientation: portrait)').addEventListener?.('change', updateRotateGate);

  return {
    enter: enter,
    openDetail: openDetail,
    closeDetail: closeDetail,
    startPractice: startPractice,
    exitPractice: exitPractice,
    exitLandscape: exitLandscape,
  };
})();
