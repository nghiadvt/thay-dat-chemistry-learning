/* QuizModule — «📖 Ôn trắc nghiệm»
 *
 * Chế độ tự luyện không cần phòng/PIN:
 * - Chạm 1 chủ đề (tag ngân hàng câu hỏi) → vào bài luôn; số câu mỗi đề do
 *   server quyết định (giáo viên quản lý), HS không tự chọn số câu
 * - Làm bài tự do không đồng hồ: chấm ngay khi chạm, hiện giải thích, chuỗi 🔥
 * - Tổng kết 1–3 sao + vòng % + ôn lại riêng những câu sai
 * - Kỷ lục từng chủ đề lưu localStorage `htd_quiz_best`
 */
window.QuizModule = (function () {
  'use strict';

  var BEST_KEY = 'htd_quiz_best';
  var OPT_COLORS = ['sun', 'bubblegum', 'sky', 'grape', 'lime', 'cherry'];
  var LETTERS = 'ABCDEF';
  // Icon + badge/điểm/độ khó chưa có trong dữ liệu chủ đề thật (API chỉ trả
  // tên/slug/màu/số câu) — dùng mẫu xoay vòng để khớp thiết kế, chờ bổ sung
  // dữ liệu thật sau.
  var RECORD_ICONS = ['🧪', '⚛️', '🔗', '🧫', '💧', '🌡️', '🧬', '⚗️'];
  var RECORD_DIFFS = [
    { key: 'cao', label: 'CAO', icon: '🔥' },
    { key: 'trung', label: 'TRUNG BÌNH', icon: '📶' },
    { key: 'thap', label: 'THẤP', icon: '🌱' },
  ];

  function panelWrap(innerHtml) {
    return '<div class="qz-hero-spacer"></div><div class="qz-setup-panel">' + innerHtml + '</div>';
  }

  var st = null;      // trạng thái lượt chơi hiện tại
  var setup = {       // trạng thái màn chọn đề
    topics: [],
    total: 0,
    topic: null,      // slug tag, null = tất cả
    topicName: 'Tất cả chủ đề',
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
    return setup.topic || 'all';
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
    body.innerHTML = panelWrap(
      '<div class="qz-hero"><span class="qz-hero-emoji">📖</span>' +
        '<p class="qz-hero-sub">Đang chuẩn bị kho câu hỏi…</p></div>' +
      '<div class="qz-skeleton-row">' +
        '<span class="qz-skeleton"></span><span class="qz-skeleton"></span><span class="qz-skeleton"></span>' +
      '</div>'
    );
  }

  function renderSetupError(msg) {
    var body = setupBody();
    if (!body) return;
    body.innerHTML = panelWrap(
      '<div class="qz-hero"><span class="qz-hero-emoji">🙈</span>' +
        '<p class="qz-hero-sub">' + esc(msg || 'Không tải được dữ liệu.') + '</p></div>' +
      '<button type="button" class="qz-cta" id="qzRetryBtn">Thử lại</button>'
    );
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
      body.innerHTML = panelWrap(
        '<div class="qz-hero"><span class="qz-hero-emoji">🌱</span>' +
        '<p class="qz-hero-sub">Kho câu hỏi đang được thầy cô chuẩn bị.<br>Bạn quay lại sau nhé!</p></div>'
      );
      return;
    }

    var allRow =
      '<button type="button" class="qz-record" data-topic="" style="--tag-c:#8B5CF6">' +
        '<span class="qz-record-icon">🌈</span>' +
        '<span class="qz-record-main">' +
          '<span class="qz-record-title-row"><span class="qz-record-title">Tất cả chủ đề</span></span>' +
          '<span class="qz-record-meta">' + setup.total + ' câu hỏi</span>' +
        '</span>' +
        '<svg class="icon qz-record-chevron" aria-hidden="true"><use href="#i-back"/></svg>' +
      '</button>';

    // Chủ đề Pro (mẫu, xen kẽ) đẩy xuống sau các chủ đề mở khoá thường —
    // sort ổn định nên thứ tự trong từng nhóm vẫn giữ nguyên như từ API.
    var topicRecords = setup.topics.map(function (t, i) {
      var badge = i % 2 === 0 ? { cls: 'badge-pro', text: 'PRO' } : { cls: '', text: 'MỚI' };
      return {
        topic: t,
        locked: badge.cls === 'badge-pro',
        icon: RECORD_ICONS[i % RECORD_ICONS.length],
        badge: badge,
        diff: RECORD_DIFFS[i % RECORD_DIFFS.length],
        points: (t.question_count || 1) * 10,
      };
    }).sort(function (a, b) { return (a.locked ? 1 : 0) - (b.locked ? 1 : 0); });

    var topicRows = topicRecords.map(function (r) {
      var t = r.topic;
      var rightIcon = r.locked
        ? '<span class="qz-record-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2.2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg></span>'
        : '<svg class="icon qz-record-chevron" aria-hidden="true"><use href="#i-back"/></svg>';
      return '<button type="button" class="qz-record' + (r.locked ? ' is-locked' : '') + '" ' +
        'data-topic="' + esc(t.slug) + '" ' + (r.locked ? 'data-locked="1" ' : '') +
        'style="--tag-c:' + esc(t.color || '#8B5CF6') + '">' +
        '<span class="qz-record-icon">' + r.icon + '</span>' +
        '<span class="qz-record-main">' +
          '<span class="qz-record-title-row">' +
            '<span class="qz-record-title">' + esc(t.name) + '</span>' +
            '<span class="qz-record-badge ' + r.badge.cls + '">' + r.badge.text + '</span>' +
          '</span>' +
          '<span class="qz-record-meta">' +
            '<span class="qz-record-points"><svg class="icon" aria-hidden="true"><use href="#i-star"/></svg>' + r.points + ' điểm</span>' +
            '<span class="qz-record-sep">|</span>' +
            '<span class="qz-record-diff diff-' + r.diff.key + '"><span class="qz-record-diff-icon">' + r.diff.icon + '</span>' + r.diff.label + '</span>' +
          '</span>' +
        '</span>' +
        rightIcon +
        '</button>';
    }).join('');

    body.innerHTML = panelWrap(
      '<h3 class="qz-section-label">Chủ đề</h3>' +
      '<div class="qz-topic-list" id="qzTopicList">' + allRow + topicRows + '</div>'
    );

    body.querySelectorAll('.qz-record').forEach(function (chip) {
      chip.addEventListener('click', function () {
        if (chip.dataset.locked) {
          sfx('tap');
          showTopicLockToast(chip.dataset.topic);
          return;
        }
        startTopic(chip);
      });
    });
  }

  /* Chủ đề Pro bị khoá: không cho chọn, chỉ báo cho HS biết cần nâng cấp
   * (giống cách bảng tuần hoàn báo ô yêu cầu Pro). */
  function showTopicLockToast(slug) {
    var t = setup.topics.find(function (x) { return x.slug === slug; });
    var name = t ? t.name : 'Chủ đề này';
    if (typeof window.showCartoonToast === 'function') {
      window.showCartoonToast(name + ' nằm trong gói Pro. Nâng cấp để mở khoá và luyện thêm nhé!', '🔒');
    }
  }

  /* ── Chạm chủ đề → tải đề & vào bài luôn ────────────────────────
   * Số câu mỗi đề do server quyết định (giáo viên quản lý), HS không
   * còn chọn số câu nữa. */
  function startTopic(chip) {
    var list = document.getElementById('qzTopicList');
    if (list && list.classList.contains('is-busy')) return; // chặn bấm dồn dập
    sfx('countdown-go');

    setup.topic = chip.dataset.topic || null;
    setup.topicName = chip.dataset.topic
      ? (setup.topics.find(function (t) { return t.slug === chip.dataset.topic; }) || {}).name || 'Chủ đề'
      : 'Tất cả chủ đề';

    if (list) list.classList.add('is-busy');
    chip.classList.add('is-starting');
    chip.insertAdjacentHTML('beforeend', '<span class="qz-record-spinner" aria-hidden="true"></span>');

    HTDApi.practiceQuestions({ topic: setup.topic }).then(function (data) {
      var questions = (data && data.questions) || [];
      if (!questions.length) throw new Error('Chưa có câu hỏi cho chủ đề này.');
      beginQuestions(questions, 'normal');
    }).catch(function (err) {
      if (list) list.classList.remove('is-busy');
      chip.classList.remove('is-starting');
      var spinner = chip.querySelector('.qz-record-spinner');
      if (spinner) spinner.remove();
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
      attemptId: null,      // id lượt luyện phía server (null nếu chưa đăng nhập)
      answers: [],          // {position, answer_index} để nộp cho server chấm lại
      startedAt: Date.now(),
    };
    showScreen('quiz-play');
    renderQuestion();

    // Chỉ ghi nhận lượt làm đề đầy đủ; lượt «ôn lại câu sai» không tính, giống
    // như cách kỷ lục cục bộ đang làm.
    if (mode === 'normal') openAttempt(questions);
  }

  /**
   * Mở lượt luyện trên server để giáo viên xem được trong thống kê.
   * Khách chưa đăng nhập sẽ nhận 401 — nuốt lỗi để không chặn việc ôn tập.
   */
  function openAttempt(questions) {
    if (!window.HTDApi || !HTDApi.studentStartAttempt) return;

    var target = st;
    HTDApi.studentStartAttempt({
      featureKey: 'quiz',
      label: setup.topicName,
      topicSlug: setup.topic,
      questionIds: questions.map(function (q) { return q.id; }),
    }).then(function (data) {
      // Bỏ qua nếu HS đã thoát hoặc bắt đầu lượt khác trong lúc chờ mạng.
      if (st !== target || !data || !data.attempt_id) return;

      // Server bỏ những câu không còn trong ngân hàng; khi đó vị trí câu ở
      // client không còn khớp với server nên thà không ghi còn hơn chấm nhầm.
      if (data.total_questions !== questions.length) return;

      st.attemptId = data.attempt_id;
    }).catch(function () { /* chưa đăng nhập hoặc lỗi mạng: bỏ qua */ });
  }

  /** Nộp bài để server chấm lại — không tin điểm tính ở client. */
  function submitAttempt() {
    if (!st || !st.attemptId || !window.HTDApi || !HTDApi.studentFinishAttempt) return;

    var attemptId = st.attemptId;
    var payload = { answers: st.answers, durationMs: Date.now() - st.startedAt };
    st.attemptId = null; // tránh nộp hai lần khi HS bấm lại

    HTDApi.studentFinishAttempt(attemptId, payload)
      .catch(function () { /* mất mạng: lượt để dở, không hiện trong thống kê */ });
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

    // Vị trí phải khớp với thứ tự câu đã gửi lúc mở lượt (st.idx), không phải
    // thứ tự đáp án đã xáo.
    st.answers.push({ position: st.idx, answer_index: picked });

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
    submitAttempt();

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
