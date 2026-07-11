/* ElementsModule — «🔊 Đọc nguyên tố»
 *
 * - Lưới nguyên tố màu theo nhóm → chạm mở thẻ chi tiết lật 3D
 * - Nút 🔊 đọc tên tiếng Việt (giọng vi-VN) / tên IUPAC (giọng en) qua Web Speech API
 *   · Không có giọng vi → đọc chuỗi phonetic bằng giọng mặc định
 *   · Không có speechSynthesis → ẩn nút đọc
 * - Chế độ luyện tập «Nghe & đoán»: máy đọc tên IUPAC → chọn 1 trong 4 nguyên tố, 10 vòng,
 *   tổng kết 1–3 sao, best score lưu localStorage.
 */
window.ElementsModule = (function () {
  'use strict';

  var ELS = window.HTD_ELEMENTS || [];
  var CATS = window.HTD_ELEMENT_CATEGORIES || {};
  var BEST_KEY = 'htd_elements_best';
  var PRACTICE_ROUNDS = 10;

  var gridBuilt = false;
  var detailIdx = 0;

  /* ── Speech ─────────────────────────────────────────────────── */
  var hasSpeech = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  var viVoice = null;
  var enVoice = null;

  function pickVoices() {
    if (!hasSpeech) return;
    var voices = window.speechSynthesis.getVoices() || [];
    viVoice = voices.find(function (v) { return /^vi([-_]|$)/i.test(v.lang); }) || null;
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

  function speak(text, lang) {
    if (!hasSpeech || !text) return;
    window.speechSynthesis.cancel(); // Android Chrome hay kẹt queue
    var u = new SpeechSynthesisUtterance(text);
    u.rate = 0.9;
    if (lang === 'vi') {
      if (viVoice) { u.voice = viVoice; u.lang = viVoice.lang; }
      // không có giọng vi: text đã là phonetic, đọc bằng giọng mặc định
    } else {
      if (enVoice) { u.voice = enVoice; }
      u.lang = enVoice ? enVoice.lang : 'en-US';
    }
    window.speechSynthesis.speak(u);
  }

  function speakElement(el, which) {
    if (which === 'en') {
      speak(el.nameEn, 'en');
    } else {
      speak(viVoice ? el.nameVi : el.phonetic, 'vi');
    }
  }

  function sfxLocal(name) {
    if (window.HTDSound) HTDSound.play(name);
  }

  /* ── Lưới + legend ──────────────────────────────────────────── */
  function enterGrid() {
    closeDetail();
    if (gridBuilt) return;
    gridBuilt = true;

    var legend = document.getElementById('elLegend');
    if (legend) {
      legend.innerHTML = Object.keys(CATS).map(function (key) {
        return '<span class="el-legend-chip"><i style="background:' + CATS[key].color + '"></i>' + CATS[key].label + '</span>';
      }).join('');
    }

    var grid = document.getElementById('elGrid');
    if (!grid) return;
    grid.innerHTML = ELS.map(function (el, i) {
      var cat = CATS[el.category] || { color: '#8B5CF6', deep: '#6D28D9' };
      return '<button type="button" class="el-tile opt-pop" ' +
        'style="--el-c:' + cat.color + ';--el-d:' + cat.deep + ';animation-delay:' + Math.min(i * 22, 700) + 'ms" ' +
        'data-idx="' + i + '">' +
        '<span class="el-tile-z">' + el.z + '</span>' +
        '<span class="el-tile-symbol">' + el.symbol + '</span>' +
        '<span class="el-tile-name">' + el.nameVi + '</span>' +
        '</button>';
    }).join('');
    grid.querySelectorAll('.el-tile').forEach(function (tile) {
      tile.addEventListener('animationend', function () {
        tile.classList.remove('opt-pop');
        tile.style.animationDelay = '';
      }, { once: true });
      tile.addEventListener('click', function () {
        openDetail(Number(tile.dataset.idx));
      });
    });
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
          '<button type="button" class="el-speak-btn el-speak-vi">🔊 Tiếng Việt</button>' +
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
    inner.querySelector('.el-speak-vi')?.addEventListener('click', function (e) {
      e.stopPropagation();
      sfxLocal('tap');
      speakElement(el, 'vi');
    });
    inner.querySelector('.el-speak-en')?.addEventListener('click', function (e) {
      e.stopPropagation();
      sfxLocal('tap');
      speakElement(el, 'en');
    });
    inner.querySelector('.el-nav-prev').addEventListener('click', function () { sfxLocal('tap'); openDetail(detailIdx - 1); });
    inner.querySelector('.el-nav-next').addEventListener('click', function () { sfxLocal('tap'); openDetail(detailIdx + 1); });
    inner.querySelector('.el-nav-close').addEventListener('click', function () { sfxLocal('pop'); closeDetail(); });
    overlay.onclick = function (e) { if (e.target === overlay) closeDetail(); };

    // đọc luôn tên tiếng Việt khi mở thẻ (đã có cử chỉ chạm)
    setTimeout(function () { speakElement(el, 'vi'); }, 350);
  }

  function closeDetail() {
    var overlay = document.getElementById('elDetailOverlay');
    if (overlay) overlay.hidden = true;
    if (hasSpeech) window.speechSynthesis.cancel();
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
      speakElement(answer, 'en');
    });
    body.querySelectorAll('.pr-choice').forEach(function (btn) {
      btn.addEventListener('click', function () { pickChoice(btn); });
    });

    setTimeout(function () { speakElement(answer, 'en'); }, 450);
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
    showScreen('elements');
  }

  return {
    enterGrid: enterGrid,
    openDetail: openDetail,
    closeDetail: closeDetail,
    startPractice: startPractice,
    exitPractice: exitPractice,
  };
})();
