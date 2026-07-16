/* QuizModule — «📖 Ôn trắc nghiệm»
 *
 * Chế độ tự luyện không cần phòng/PIN:
 * - Chọn chủ đề (tag ngân hàng câu hỏi) + số câu → tải đề ngẫu nhiên qua API công khai
 * - Làm bài tự do không đồng hồ: chấm ngay khi chạm, hiện giải thích, chuỗi 🔥
 * - Tổng kết 1–3 sao + vòng % + ôn lại riêng những câu sai
 * - Kỷ lục từng (chủ đề × số câu) lưu localStorage `htd_quiz_best`
 */
window.QuizModule = (function () {
  'use strict';

  var BEST_KEY = 'htd_quiz_best';
  var COUNTS = [10, 15, 20];
  var OPT_COLORS = ['sun', 'bubblegum', 'sky', 'grape', 'lime', 'cherry'];
  var LETTERS = 'ABCDEF';

  var st = null;      // trạng thái lượt chơi hiện tại
  var setup = {       // trạng thái màn chọn đề
    topics: [],
    total: 0,
    topic: null,      // slug tag, null = tất cả
    topicName: 'Tất cả chủ đề',
    count: 10,
    loading: false,
  };

  /* ── Helpers ────────────────────────────────────────────────── */
  function sfx(name) {
    if (window.HTDSound) HTDSound.play(name);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Đáp án dạng text thuần: escape rồi subscript chỉ số hoá học (số đứng ngay sau chữ/ngoặc)
  function fmtChem(s) {
    return esc(s)
      .replace(/-&gt;/g, '→')
      .replace(/([A-Za-z\)\]])(\d+)/g, '$1<sub>$2</sub>');
  }

  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  function bestMap() {
    try { return JSON.parse(localStorage.getItem(BEST_KEY) || '{}') || {}; } catch (e) { return {}; }
  }

  function bestKeyOf() {
    return (setup.topic || 'all') + '|' + setup.count;
  }

  function getBest() {
    return bestMap()[bestKeyOf()] || null;
  }

  function saveBest(correct, total) {
    var map = bestMap();
    var key = bestKeyOf();
    var pct = Math.round((correct / total) * 100);
    var isRecord = !map[key] || pct > map[key].pct;
    if (isRecord) {
      map[key] = { correct: correct, total: total, pct: pct };
      try { localStorage.setItem(BEST_KEY, JSON.stringify(map)); } catch (e) {}
    }
    return isRecord;
  }

  /* ── Mở module từ Home ──────────────────────────────────────── */
  function open() {
    showScreen('quiz-setup');
    renderSetupLoading();
    loadTopics();
  }

  function loadTopics() {
    if (!window.HTDApi) { renderSetupError('Thiếu kết nối máy chủ.'); return; }
    HTDApi.practiceTopics().then(function (data) {
      setup.topics = (data && data.topics) || [];
      setup.total = (data && data.total) || 0;
      // chủ đề đã chọn trước đó không còn tồn tại → về «Tất cả»
      if (setup.topic && !setup.topics.some(function (t) { return t.slug === setup.topic; })) {
        setup.topic = null;
        setup.topicName = 'Tất cả chủ đề';
      }
      renderSetup();
    }).catch(function (err) {
      renderSetupError(err && err.message);
    });
  }

  /* ── Màn 1: chọn đề ─────────────────────────────────────────── */
  function setupBody() {
    return document.getElementById('qzSetupBody');
  }

  function renderSetupLoading() {
    var body = setupBody();
    if (!body) return;
    body.innerHTML =
      '<div class="qz-hero"><span class="qz-hero-emoji">📖</span>' +
        '<p class="qz-hero-sub">Đang chuẩn bị kho câu hỏi…</p></div>' +
      '<div class="qz-skeleton-row">' +
        '<span class="qz-skeleton"></span><span class="qz-skeleton"></span><span class="qz-skeleton"></span>' +
      '</div>';
  }

  function renderSetupError(msg) {
    var body = setupBody();
    if (!body) return;
    body.innerHTML =
      '<div class="qz-hero"><span class="qz-hero-emoji">🙈</span>' +
        '<p class="qz-hero-sub">' + esc(msg || 'Không tải được dữ liệu.') + '</p></div>' +
      '<button type="button" class="qz-cta" id="qzRetryBtn">Thử lại</button>';
    document.getElementById('qzRetryBtn').addEventListener('click', function () {
      sfx('tap');
      renderSetupLoading();
      loadTopics();
    });
  }

  function renderSetup() {
    var body = setupBody();
    if (!body) return;

    if (!setup.total) {
      body.innerHTML =
        '<div class="qz-hero"><span class="qz-hero-emoji">🌱</span>' +
        '<p class="qz-hero-sub">Kho câu hỏi đang được thầy cô chuẩn bị.<br>Bạn quay lại sau nhé!</p></div>';
      return;
    }

    var chipsHtml =
      '<button type="button" class="qz-topic-chip' + (setup.topic === null ? ' selected' : '') + '" data-topic="">' +
        '<span class="qz-topic-name">🌈 Tất cả chủ đề</span>' +
        '<span class="qz-topic-count">' + setup.total + ' câu</span>' +
      '</button>' +
      setup.topics.map(function (t) {
        var sel = setup.topic === t.slug ? ' selected' : '';
        return '<button type="button" class="qz-topic-chip' + sel + '" data-topic="' + esc(t.slug) + '" ' +
          'style="--tag-c:' + esc(t.color || '#8B5CF6') + '">' +
          '<span class="qz-topic-dot"></span>' +
          '<span class="qz-topic-name">' + esc(t.name) + '</span>' +
          '<span class="qz-topic-count">' + t.question_count + ' câu</span>' +
          '</button>';
      }).join('');

    var countHtml = COUNTS.map(function (n) {
      return '<button type="button" class="qz-count-btn' + (setup.count === n ? ' selected' : '') + '" data-count="' + n + '">' +
        n + '<small>câu</small></button>';
    }).join('');

    body.innerHTML =
      '<div class="qz-hero">' +
        '<span class="qz-hero-emoji">🧠</span>' +
        '<p class="qz-hero-sub">Chọn chủ đề và luyện mỗi ngày để lên trình nhé!</p>' +
      '</div>' +
      '<h3 class="qz-section-label">Chủ đề</h3>' +
      '<div class="qz-topic-list">' + chipsHtml + '</div>' +
      '<h3 class="qz-section-label">Số câu hỏi</h3>' +
      '<div class="qz-count-row">' + countHtml + '</div>' +
      '<div class="qz-best-line" id="qzBestLine"></div>' +
      '<button type="button" class="qz-cta" id="qzStartBtn">Bắt đầu ôn ▸</button>';

    body.querySelectorAll('.qz-topic-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        sfx('tap');
        setup.topic = chip.dataset.topic || null;
        setup.topicName = chip.dataset.topic
          ? (setup.topics.find(function (t) { return t.slug === chip.dataset.topic; }) || {}).name || 'Chủ đề'
          : 'Tất cả chủ đề';
        body.querySelectorAll('.qz-topic-chip').forEach(function (c) { c.classList.remove('selected'); });
        chip.classList.add('selected');
        updateBestLine();
      });
    });

    body.querySelectorAll('.qz-count-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        sfx('tap');
        setup.count = Number(btn.dataset.count);
        body.querySelectorAll('.qz-count-btn').forEach(function (b) { b.classList.remove('selected'); });
        btn.classList.add('selected');
        updateBestLine();
      });
    });

    document.getElementById('qzStartBtn').addEventListener('click', startRun);
    updateBestLine();
  }

  function updateBestLine() {
    var line = document.getElementById('qzBestLine');
    if (!line) return;
    var best = getBest();
    line.innerHTML = best
      ? '🏆 Kỷ lục của bạn: <strong>' + best.correct + '/' + best.total + '</strong> (' + best.pct + '%)'
      : '✨ Lượt đầu tiên với lựa chọn này — cố lên!';
  }

  /* ── Tải đề & bắt đầu ───────────────────────────────────────── */
  function startRun() {
    var btn = document.getElementById('qzStartBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Đang tải đề…'; }
    sfx('countdown-go');

    HTDApi.practiceQuestions({ topic: setup.topic, count: setup.count }).then(function (data) {
      var questions = (data && data.questions) || [];
      if (!questions.length) throw new Error('Chưa có câu hỏi cho chủ đề này.');
      beginQuestions(questions, 'normal');
    }).catch(function (err) {
      if (btn) { btn.disabled = false; btn.textContent = 'Bắt đầu ôn ▸'; }
      if (typeof showCartoonToast === 'function') {
        showCartoonToast((err && err.message) || 'Không tải được đề, thử lại nhé!', '😢');
      }
    });
  }

  function beginQuestions(questions, mode) {
    st = {
      mode: mode, // 'normal' | 'retry'
      questions: questions,
      idx: 0,
      correctCount: 0,
      streak: 0,
      bestStreak: 0,
      wrong: [],   // câu trả lời sai (kèm lựa chọn của HS) để review + ôn lại
      locked: false,
    };
    showScreen('quiz-play');
    renderQuestion();
  }

  /* ── Màn 2: làm bài ─────────────────────────────────────────── */
  function playBody() {
    return document.getElementById('qzPlayBody');
  }

  function renderQuestion() {
    var body = playBody();
    if (!body || !st) return;
    var q = st.questions[st.idx];
    st.locked = false;
    // xáo thứ tự đáp án để không học vẹt vị trí
    st.order = shuffle(q.options.map(function (_, i) { return i; }));

    var progressPct = Math.round((st.idx / st.questions.length) * 100);

    var optionsHtml = st.order.map(function (optIdx, pos) {
      return '<button type="button" class="qz-option opt-pop qz-opt-' + OPT_COLORS[pos % OPT_COLORS.length] + '" ' +
        'data-opt="' + optIdx + '" style="animation-delay:' + (pos * 70) + 'ms">' +
        '<span class="qz-option-label">' + LETTERS[pos] + '</span>' +
        '<span class="qz-option-text">' + fmtChem(q.options[optIdx]) + '</span>' +
        '</button>';
    }).join('');

    body.innerHTML =
      '<div class="qz-play-top">' +
        '<button type="button" class="qz-exit" id="qzExitBtn" aria-label="Thoát">✕</button>' +
        '<span class="qz-progress-text">Câu ' + (st.idx + 1) + '/' + st.questions.length +
          (st.mode === 'retry' ? ' · Ôn lại' : '') + '</span>' +
        '<span class="qz-streak' + (st.streak >= 2 ? ' hot' : '') + '" id="qzStreak">🔥 ' + st.streak + '</span>' +
        '<span class="qz-score">⭐ ' + st.correctCount + '</span>' +
      '</div>' +
      '<div class="qz-progress-track"><div class="qz-progress-fill" style="width:' + progressPct + '%"></div></div>' +
      '<div class="qz-q-scroll">' +
        '<div class="qz-q-card">' +
          '<div class="qz-q-content">' + (q.content || '') + '</div>' +
        '</div>' +
        '<div class="qz-options">' + optionsHtml + '</div>' +
        '<div class="qz-feedback" id="qzFeedback" hidden></div>' +
      '</div>';

    document.getElementById('qzExitBtn').addEventListener('click', confirmExit);
    body.querySelectorAll('.qz-option').forEach(function (btn) {
      btn.addEventListener('animationend', function () {
        btn.classList.remove('opt-pop');
        btn.style.animationDelay = '';
      }, { once: true });
      btn.addEventListener('click', function () { pick(btn, q); });
    });
  }

  function pick(btn, q) {
    if (!st || st.locked) return;
    st.locked = true;
    var picked = Number(btn.dataset.opt);
    var correct = picked === q.correct_index;
    var body = playBody();

    body.querySelectorAll('.qz-option').forEach(function (b) {
      b.disabled = true;
      var idx = Number(b.dataset.opt);
      if (idx === q.correct_index) b.classList.add('is-answer');
      else if (idx !== picked) b.classList.add('is-dim');
    });

    if (correct) {
      st.correctCount += 1;
      st.streak += 1;
      st.bestStreak = Math.max(st.bestStreak, st.streak);
      btn.classList.add('is-correct');
      sfx('correct');
      if (window.HTDFx) HTDFx.burstAtElement(btn, { count: 18 });
      if (st.streak > 0 && st.streak % 5 === 0 && window.HTDFx) HTDFx.sparkleRain({ count: 25 });
    } else {
      st.streak = 0;
      btn.classList.add('is-wrong', 'wobble-sad');
      sfx('wrong');
      if (window.HTDFx) HTDFx.shake();
      st.wrong.push({ question: q, picked: picked });
    }

    var streakEl = document.getElementById('qzStreak');
    if (streakEl) {
      streakEl.textContent = '🔥 ' + st.streak;
      streakEl.classList.toggle('hot', st.streak >= 2);
    }

    showFeedback(q, correct);
  }

  function showFeedback(q, correct) {
    var box = document.getElementById('qzFeedback');
    if (!box) return;
    var isLast = st.idx >= st.questions.length - 1;
    var correctPos = st.order.indexOf(q.correct_index);
    var title = correct
      ? shuffle(['Chính xác! 🎉', 'Tuyệt vời! 🌟', 'Quá đỉnh! 🚀', 'Giỏi lắm! 👏'])[0]
      : 'Chưa đúng — đáp án là <strong>' + LETTERS[correctPos] + '</strong>';

    box.className = 'qz-feedback ' + (correct ? 'good' : 'bad');
    box.innerHTML =
      '<p class="qz-feedback-title">' + title + '</p>' +
      (q.explanation ? '<div class="qz-feedback-explain"><span class="qz-bulb">💡</span><div>' + q.explanation + '</div></div>' : '') +
      '<button type="button" class="qz-next-btn" id="qzNextBtn">' +
        (isLast ? 'Xem kết quả 🏁' : 'Câu tiếp theo ▸') + '</button>';
    box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    document.getElementById('qzNextBtn').addEventListener('click', function () {
      sfx('tap');
      st.idx += 1;
      if (st.idx >= st.questions.length) renderResult();
      else renderQuestion();
    });
  }

  /* ── Thoát giữa chừng ───────────────────────────────────────── */
  function confirmExit() {
    var body = playBody();
    if (!body || body.querySelector('.qz-confirm-overlay')) return;
    sfx('pop');
    var overlay = document.createElement('div');
    overlay.className = 'qz-confirm-overlay';
    overlay.innerHTML =
      '<div class="qz-confirm-card">' +
        '<span class="qz-confirm-emoji">🤔</span>' +
        '<p>Thoát lượt ôn này?<br><small>Tiến độ hiện tại sẽ không được lưu.</small></p>' +
        '<div class="qz-confirm-btns">' +
          '<button type="button" class="qz-confirm-stay">Ở lại làm tiếp</button>' +
          '<button type="button" class="qz-confirm-leave">Thoát</button>' +
        '</div>' +
      '</div>';
    body.appendChild(overlay);
    overlay.querySelector('.qz-confirm-stay').addEventListener('click', function () {
      sfx('tap');
      overlay.remove();
    });
    overlay.querySelector('.qz-confirm-leave').addEventListener('click', function () {
      sfx('tap');
      st = null;
      showScreen('quiz-setup');
      renderSetup();
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });
  }

  /* ── Màn 3: kết quả ─────────────────────────────────────────── */
  function renderResult() {
    var body = document.getElementById('qzResultBody');
    if (!body || !st) return;
    showScreen('quiz-result');

    var total = st.questions.length;
    var correct = st.correctCount;
    var pct = Math.round((correct / total) * 100);
    var stars = pct >= 90 ? 3 : pct >= 70 ? 2 : pct >= 40 ? 1 : 0;
    // kỷ lục chỉ tính lượt làm đề đầy đủ, không tính lượt «ôn lại câu sai»
    var isRecord = st.mode === 'normal' ? saveBest(correct, total) : false;

    var starHtml = [1, 2, 3].map(function (i) {
      return '<svg class="icon qz-star' + (i <= stars ? ' lit' : '') + '" style="animation-delay:' + (i * 250) + 'ms" aria-hidden="true"><use href="#i-star"/></svg>';
    }).join('');

    var title = stars >= 3 ? 'Xuất sắc!' : stars === 2 ? 'Giỏi lắm!' : stars === 1 ? 'Khá đấy!' : 'Cố lên nào!';

    // vòng tròn tiến độ SVG: chu vi 2πr với r=52
    var circ = Math.round(2 * Math.PI * 52);
    var dash = Math.round(circ * pct / 100);

    var wrongReview = st.wrong.map(function (w, i) {
      var q = w.question;
      return '<details class="qz-review-item">' +
        '<summary><span class="qz-review-num">' + (i + 1) + '</span><div class="qz-review-q">' + (q.content || '') + '</div></summary>' +
        '<div class="qz-review-detail">' +
          '<p class="qz-review-row bad">✗ Bạn chọn: ' + fmtChem(q.options[w.picked] != null ? q.options[w.picked] : '—') + '</p>' +
          '<p class="qz-review-row good">✓ Đáp án đúng: ' + fmtChem(q.options[q.correct_index]) + '</p>' +
          (q.explanation ? '<div class="qz-review-explain">💡 ' + q.explanation + '</div>' : '') +
        '</div>' +
        '</details>';
    }).join('');

    body.innerHTML =
      '<div class="qz-result-hero">' +
        '<div class="qz-stars">' + starHtml + '</div>' +
        '<h2 class="qz-result-title">' + title + '</h2>' +
        '<div class="qz-ring-wrap">' +
          '<svg class="qz-ring" viewBox="0 0 120 120">' +
            '<circle class="qz-ring-bg" cx="60" cy="60" r="52"/>' +
            '<circle class="qz-ring-val" cx="60" cy="60" r="52" stroke-dasharray="' + dash + ' ' + circ + '"/>' +
          '</svg>' +
          '<div class="qz-ring-text"><strong>' + correct + '/' + total + '</strong><span>' + pct + '%</span></div>' +
        '</div>' +
        '<p class="qz-result-meta">' +
          esc(setup.topicName) + (st.mode === 'retry' ? ' · Ôn lại câu sai' : '') +
          ' · Chuỗi dài nhất 🔥 ' + st.bestStreak +
          (isRecord ? '<br>🏆 <strong>Kỷ lục mới!</strong>' : '') +
        '</p>' +
      '</div>' +
      '<div class="qz-result-actions">' +
        (st.wrong.length ? '<button type="button" class="qz-cta qz-cta-retry" id="qzRetryWrongBtn">Ôn lại ' + st.wrong.length + ' câu sai 💪</button>' : '') +
        '<button type="button" class="qz-cta" id="qzNewRunBtn">Làm đề mới ▸</button>' +
        '<button type="button" class="qz-home-btn" id="qzGoHomeBtn">Về trang chủ</button>' +
      '</div>' +
      (st.wrong.length
        ? '<h3 class="qz-section-label qz-review-label">Xem lại câu sai</h3><div class="qz-review-list">' + wrongReview + '</div>'
        : '<p class="qz-perfect-note">Không sai câu nào — quá tuyệt vời! 💯</p>');

    sfx(stars >= 2 ? 'fanfare' : 'pop');
    if (stars >= 2 && window.HTDFx) HTDFx.sparkleRain({ count: 45 });

    var retryBtn = document.getElementById('qzRetryWrongBtn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        sfx('countdown-go');
        beginQuestions(shuffle(st.wrong.map(function (w) { return w.question; })), 'retry');
      });
    }
    document.getElementById('qzNewRunBtn').addEventListener('click', function () {
      sfx('tap');
      st = null;
      showScreen('quiz-setup');
      renderSetup();
    });
    document.getElementById('qzGoHomeBtn').addEventListener('click', function () {
      sfx('tap');
      goStudentHome();
    });
  }

  return {
    open: open,
    exitToSetup: function () { showScreen('quiz-setup'); renderSetup(); },
  };
})();
