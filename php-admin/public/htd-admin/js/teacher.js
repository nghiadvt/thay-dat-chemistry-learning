/** Teacher app — website desktop, đồng bộ localStorage với student app */
const LS_TEACHER_Q_ZOOM = 'htd_teacher_q_zoom';
const LS_SCORE_PANEL_OPEN = 'htd_teacher_score_panel';
const TEACHER_ZOOM_MIN = 0.85;
const TEACHER_ZOOM_MAX = 1.5;
const TEACHER_ZOOM_STEP = 0.1;

const teacherState = {
  poll: null,
  gameTick: null,
  dashboardUiReady: false,
  actionMenuOpen: false,
  lastRenderedPhase: null,
  lastRenderedIndex: -1,
  fakeScores: {},
  prevRanks: {},
  lastScoreTick: -1,
  qZoom: 1,
  scorePanelOpen: true,
  fitTimer: null,
  fitObserver: null,
  presentationOpen: false,
  presentationAvailable: !!document.fullscreenEnabled,
  leaderboardAnimSecond: null,
  currentQuestion: null,
  serverQuestionIndex: 0,
  playerScores: {},
  finalLeaderboard: [],
  presentationTick: null,
};
const LS_SIDEBAR_COLLAPSED = 'htd_teacher_sidebar_collapsed';
const LS_MAIN_SPLIT = 'htd_teacher_main_split';
const FAKE_QUESTIONS = HTD.FAKE_QUESTIONS;

function isBackendMode() {
  return Boolean(window.HTD_CONFIG?.useBackend);
}

function getTeacherCurrentQuestion() {
  if (isBackendMode() && teacherState.currentQuestion) return teacherState.currentQuestion;
  const room = HTD.getRoom();
  if (room?.game) return FAKE_QUESTIONS[room.game.questionIndex];
  return FAKE_QUESTIONS[0];
}

function ensureTeacherGameState(room) {
  if (!room.game) {
    room.game = {
      phase: 'waiting',
      questionIndex: 0,
      paused: false,
      phaseStartedAt: Date.now(),
      phaseDuration: 5,
      questionStartedAt: Date.now(),
      questionDuration: 30,
    };
  }
  return room.game;
}

function loadTeacherGamePicker() {
  const wrap = document.getElementById('teacherSetupGame');
  const select = document.getElementById('teacherGameSelect');
  if (!wrap || !select || !isBackendMode()) return;

  wrap.hidden = false;
  HTDApi.listGames()
    .then(games => {
      if (!games.length) {
        select.innerHTML = '<option value="">Chưa có game — tạo trong Admin</option>';
        return;
      }
      select.innerHTML =
        '<option value="">— Chọn game —</option>' +
        games
          .map(g => {
            const count = g.quizzes_count ?? 0;
            return `<option value="${g.id}">${g.name} (${count} quiz)</option>`;
          })
          .join('');
      const urlGameId = new URLSearchParams(location.search).get('game_id');
      if (urlGameId) select.value = urlGameId;
    })
    .catch(() => {
      select.innerHTML = '<option value="">Không tải được danh sách game</option>';
    });
}

function showTeacherScreen(id) {
  document.querySelectorAll('.teacher-screen').forEach(s => s.classList.remove('active'));
  document.querySelector(`[data-screen="${id}"]`)?.classList.add('active');

  if (id === 'dashboard') {
    initTeacherSidebar();
    initTeacherDashboardUi();
    renderDashboard();
    startTeacherPoll();
  } else {
    clearInterval(teacherState.poll);
    clearInterval(teacherState.gameTick);
  }
}

function initTeacherApp() {
  const params = new URLSearchParams(location.search);
  if (params.get('embedded') === 'admin') {
    document.body.classList.add('admin-embedded');
  }

  const pinFromUrl = (params.get('pin') || '').replace(/\D/g, '').slice(0, 6);

  if (pinFromUrl.length === 6 && isBackendMode()) {
    joinExistingRoomFromAdmin(pinFromUrl, params);
    return;
  }

  const room = HTD.getRoom();
  if (room && room.status !== 'ended') {
    showTeacherScreen('dashboard');
  } else {
    showTeacherScreen('setup');
    loadTeacherGamePicker();
  }
}

function joinExistingRoomFromAdmin(pin, params) {
  const gameId = parseInt(params.get('game_id'), 10) || null;
  const sessionId = parseInt(params.get('session_id'), 10) || null;
  const boot = window.ADMIN_BOOT?.session || {};

  HTDBridge.init()
    .then(() => HTDApi.checkPin(pin))
    .then(data => {
      const roomData = data.data || data;
      HTD.setRoom({
        pin,
        name: boot.roomName || roomData.quiz_name || roomData.game_name || 'PHÒNG QUIZ',
        roomName: boot.roomName || null,
        quizName: boot.quizName || roomData.quiz_name || null,
        gameName: boot.gameName || roomData.game_name || null,
        joinUrl: boot.joinUrl || null,
        qrUrl: boot.qrUrl || null,
        teacher: boot.hostName || 'Giáo viên',
        status: boot.status === 'playing' ? 'started' : boot.status === 'ended' ? 'ended' : 'waiting',
        backendMode: true,
        sessionId: sessionId || roomData.session_id,
        gameId: gameId || roomData.game_id,
        createdAt: Date.now(),
      });
      HTD.setPlayers([]);
      return HTDBridge.joinRoom({
        pin,
        name: boot.hostName || 'Host',
        isHost: true,
      });
    })
    .then(() => showTeacherScreen('dashboard'))
    .catch(err => {
      const msg = err.message || 'Không vào được phòng.';
      if (/Unauthenticated|401|419|đăng nhập/i.test(msg)) {
        location.href = HTDApi.loginUrl(location.pathname + location.search);
        return;
      }
      alert(msg);
      showTeacherScreen('setup');
    });
}

function createTeacherRoom() {
  if (isBackendMode()) {
    const select = document.getElementById('teacherGameSelect');
    const gameId = parseInt(select?.value, 10);
    if (!gameId) {
      alert('Chọn game trước khi tạo phòng (hoặc tạo phòng từ Admin).');
      return;
    }
    HTDBridge.init()
      .then(() => HTDApi.createGameSession(gameId))
      .then(data => {
        const session = data.session || data;
        const pin = data.pin || session.pin;
        HTD.setRoom({
          pin,
          name: session.game?.name || 'PHÒNG QUIZ',
          teacher: session.host?.name || 'Giáo viên',
          status: 'waiting',
          backendMode: true,
          sessionId: session.id,
          gameId,
          createdAt: Date.now(),
        });
        HTD.setPlayers([]);
        return HTDBridge.joinRoom({
          pin,
          name: session.host?.name || 'Host',
          isHost: true,
        });
      })
      .then(() => showTeacherScreen('dashboard'))
      .catch(err => {
        const msg = err.message || 'Không tạo được phòng.';
        if (/Unauthenticated|401|419|đăng nhập/i.test(msg)) {
          location.href = HTDApi.loginUrl('/app/teacher.html');
          return;
        }
        alert(msg);
      });
    return;
  }

  const pin = HTD.genPin();
  HTD.setRoom({
    pin,
    name: 'HÓA HỌC 10A1',
    teacher: 'Thầy Đạt',
    status: 'waiting',
    createdAt: Date.now(),
  });
  HTD.setPlayers([]);
  showTeacherScreen('dashboard');
}

function renderTeacherPinDigits(pin) {
  const el = document.getElementById('teacherPinDigits');
  if (!el || !pin) return;
  el.innerHTML = pin
    .split('')
    .map(d => `<span class="teacher-pin-digit">${d}</span>`)
    .join('');
}

function isTeacherRoomInGame(room) {
  return room?.status === 'started' || room?.status === 'ended';
}

function isTeacherRoomEnded(room) {
  return room?.status === 'ended' || room?.game?.phase === 'final';
}

function renderDashboard() {
  const room = HTD.getRoom();
  if (!room) return;

  const displayName = room.roomName || room.name || 'Phòng chơi';
  document.getElementById('teacherRoomName').textContent = displayName;

  const quizEl = document.getElementById('teacherQuizName');
  const gameEl = document.getElementById('teacherGameName');
  if (quizEl) {
    const quizName = room.quizName || '';
    quizEl.textContent = quizName ? `Quiz: ${quizName}` : '';
    quizEl.hidden = !quizName;
  }
  if (gameEl) {
    const gameName = room.gameName || '';
    gameEl.textContent = gameName ? `Game: ${gameName}` : '';
    gameEl.hidden = !gameName;
  }

  const teacherEl = document.getElementById('teacherTeacherName');
  if (teacherEl) {
    teacherEl.textContent = room.teacher ? `Giáo viên: ${room.teacher}` : '';
    teacherEl.hidden = !room.teacher;
  }

  renderTeacherPinDigits(room.pin);
  updateTeacherJoinQr(room);
  wireTeacherRoomQuickActions(room);

  const inGame = isTeacherRoomInGame(room);
  const isEnded = isTeacherRoomEnded(room);

  document.getElementById('teacherRoomCard')?.classList.toggle('game-active', inGame);
  document.getElementById('teacherRoomCard')?.classList.toggle('game-ended', isEnded);
  document.getElementById('teacherRoomHeader')?.classList.toggle('compact', inGame);
  document.getElementById('teacherRoomInfo')?.classList.toggle('compact', inGame);

  const statusPill = document.getElementById('teacherStatusPill');
  statusPill.textContent = isEnded
    ? 'Kết thúc'
    : inGame
      ? room.game?.paused
        ? 'Tạm dừng'
        : 'Đang chơi'
      : 'Đang chờ';
  statusPill.classList.toggle('started', inGame && !isEnded);
  statusPill.classList.toggle('ended', isEnded);

  const waitingView = document.getElementById('teacherWaitingView');
  const gameView = document.getElementById('teacherGameView');
  const mainGrid = document.querySelector('.teacher-main-grid');
  const playersPanel = document.getElementById('teacherPlayersPanel');
  const btnEndRoom = document.getElementById('btnEndRoom');
  const panelTitle = document.getElementById('teacherPanelTitle');
  const playerGrid = document.getElementById('teacherList');
  const scoreList = document.getElementById('teacherScoreList');
  const gameToolbar = document.querySelector('.teacher-game-toolbar');

  if (waitingView) waitingView.hidden = inGame;
  if (gameView) gameView.hidden = !inGame;
  if (gameToolbar) gameToolbar.hidden = isEnded;
  mainGrid?.classList.toggle('game-active', inGame);
  mainGrid?.classList.toggle('game-ended', isEnded);
  mainGrid?.classList.toggle('scores-collapsed', inGame && !isEnded && !teacherState.scorePanelOpen);
  playersPanel?.classList.toggle('game-active', inGame);
  document.getElementById('teacherMainResizer')?.classList.toggle('hidden', inGame && !isEnded && !teacherState.scorePanelOpen);
  updateTeacherScorePanelBtn();
  applyTeacherQZoom();
  if (btnEndRoom) btnEndRoom.hidden = inGame && !isEnded;
  if (panelTitle) {
    panelTitle.textContent = isEnded ? 'Kết quả cuối' : inGame ? 'Bảng điểm' : 'Danh sách học sinh';
  }
  if (playerGrid) playerGrid.hidden = inGame;
  if (scoreList) scoreList.hidden = !inGame || (!isEnded && !teacherState.scorePanelOpen);
  if (playersPanel) playersPanel.hidden = inGame && !isEnded && !teacherState.scorePanelOpen;

  if (inGame && room.game) {
    renderTeacherGame(room);
  }

  renderTeacherList();
}

function renderTeacherTimeline(game) {
  const el = document.getElementById('teacherTimeline');
  if (!el) return;

  if (isBackendMode() && HTD.getRoom()?.backendMode) {
    const count = Math.max(game.questionIndex + 1, teacherState.serverQuestionIndex || 1);
    el.innerHTML = Array.from({ length: count }, (_, i) => {
      let cls = 'teacher-timeline-item';
      if (i < game.questionIndex) cls += ' done';
      else if (i === game.questionIndex && game.phase !== 'final') cls += ' active';
      else if (game.phase === 'final') cls += ' done';
      return `<div class="${cls}" title="Câu ${i + 1}"><span>${i + 1}</span></div>`;
    }).join('');
    return;
  }

  el.innerHTML = FAKE_QUESTIONS.map((q, i) => {
    let cls = 'teacher-timeline-item';
    if (i < game.questionIndex) cls += ' done';
    else if (i === game.questionIndex && game.phase !== 'final') cls += ' active';
    else if (game.phase === 'final') cls += ' done';
    const label = i + 1;
    return `<div class="${cls}" title="Câu ${label} · ${q.id}"><span>${label}</span></div>`;
  }).join('');
}

function teacherOptionLetter(index) {
  return String.fromCharCode(65 + index);
}

function escapeTeacherHtml(text) {
  const el = document.createElement('div');
  el.textContent = text ?? '';
  return el.innerHTML;
}

function renderTeacherQuestionContent(q) {
  const contentEl = document.getElementById('teacherQContent');
  const promptEl = document.getElementById('teacherQPrompt');
  const img = document.getElementById('teacherQImage');
  const vidWrap = document.getElementById('teacherQVideoWrap');
  const vid = document.getElementById('teacherQVideo');

  if (contentEl && q.contentHtml) {
    contentEl.innerHTML = q.contentHtml;
    contentEl.hidden = false;
    if (promptEl) {
      promptEl.hidden = true;
      promptEl.textContent = '';
    }
    if (img) img.hidden = true;
    if (vidWrap) vidWrap.hidden = true;
    if (vid) {
      vid.pause();
      vid.removeAttribute('src');
      vid.removeAttribute('poster');
    }
    contentEl.querySelectorAll('img').forEach(el => {
      el.addEventListener('load', () => scheduleFitTeacherQuestionCard(), { once: true });
    });
    return;
  }

  if (contentEl) {
    contentEl.innerHTML = '';
    contentEl.hidden = true;
  }
  if (promptEl) {
    promptEl.hidden = false;
    promptEl.textContent = q.prompt || '';
  }

  if (img) img.hidden = true;
  if (vidWrap) vidWrap.hidden = true;
  if (vid) {
    vid.pause();
    vid.removeAttribute('src');
    vid.removeAttribute('poster');
  }

  if (q.media === 'image' && q.mediaUrl && img) {
    img.src = q.mediaUrl;
    img.hidden = false;
    img.onload = () => scheduleFitTeacherQuestionCard();
  } else if (q.media === 'video' && q.mediaUrl && vid && vidWrap) {
    vid.src = q.mediaUrl;
    if (q.mediaPoster) vid.poster = q.mediaPoster;
    vidWrap.hidden = false;
    vid.onloadedmetadata = () => scheduleFitTeacherQuestionCard();
  }
}

function renderTeacherMcOptions(q, containerId) {
  const answersEl = document.getElementById(containerId);
  if (!answersEl) return;

  const options = Array.isArray(q.options) ? q.options : [];
  if (!options.length) {
    answersEl.innerHTML = '<p class="teacher-q-empty">Chưa có đáp án.</p>';
    return;
  }

  const correctIdx = q.correctIndex ?? q.correct ?? null;
  answersEl.innerHTML = `<div class="teacher-mc-options">${options
    .map((optText, i) => {
      const isCorrect = correctIdx === i;
      return `<div class="teacher-mc-option${isCorrect ? ' correct' : ''}">
          <span class="teacher-mc-label">${teacherOptionLetter(i)}.</span>
          <span class="teacher-mc-text">${EquationUI.chemToHtml(optText)}</span>
          ${isCorrect ? '<span class="teacher-mc-check" aria-label="Đáp án đúng">✓</span>' : ''}
        </div>`;
    })
    .join('')}</div>`;
}

function renderTeacherStructuredPreview(q, eqElId) {
  const eqEl = document.getElementById(eqElId);
  if (!eqEl) return false;

  if (q.type === 'input' && q.inputMode === 'essay') {
    eqEl.hidden = false;
    eqEl.innerHTML =
      '<div class="teacher-essay-preview">' +
      '<textarea class="teacher-essay-preview-input" readonly placeholder="Học sinh nhập câu trả lời tại đây…"></textarea>' +
      '</div>';
    return true;
  }

  if (q.type === 'input' && q.inputMode === 'formula') {
    eqEl.hidden = false;
    eqEl.innerHTML =
      '<div class="teacher-formula-preview">' +
      '<p class="teacher-formula-preview-hint">Học sinh nhập công thức bằng bàn phím hóa học</p>' +
      (q.correctAnswerNormalized
        ? `<p class="teacher-formula-preview-answer">Đáp án: <strong>${EquationUI.chemToHtml(q.correctAnswerNormalized)}</strong></p>`
        : '') +
      '</div>';
    return true;
  }

  if (q.template && Array.isArray(q.template) && q.template.length) {
    eqEl.hidden = false;
    const values = EquationUI.createInputState(q.template);
    eqEl.innerHTML = EquationUI.renderEquation(q.template, values, null);
    return true;
  }

  eqEl.hidden = true;
  eqEl.innerHTML = '';
  return false;
}

function renderTeacherBarem(q) {
  const baremEl = document.getElementById('teacherQBarem');
  if (!baremEl) return;

  const parts = [];

  if (q.type === 'mc' && q.correctIndex != null && Array.isArray(q.options)) {
    const letter = teacherOptionLetter(q.correctIndex);
    const text = q.options[q.correctIndex] || '';
    parts.push(
      `<div class="teacher-barem-row">
        <span class="teacher-barem-label">Đáp án đúng</span>
        <span class="teacher-barem-value">${letter}${text ? ` — ${EquationUI.chemToHtml(text)}` : ''}</span>
      </div>`
    );
  } else if (q.correctAnswerNormalized) {
    parts.push(
      `<div class="teacher-barem-row">
        <span class="teacher-barem-label">Đáp án mẫu</span>
        <span class="teacher-barem-value">${EquationUI.chemToHtml(q.correctAnswerNormalized)}</span>
      </div>`
    );
  }

  if (q.correctAnswer && typeof q.correctAnswer === 'object') {
    parts.push(
      `<div class="teacher-barem-row">
        <span class="teacher-barem-label">Barem</span>
        <code class="teacher-barem-code">${escapeTeacherHtml(JSON.stringify(q.correctAnswer))}</code>
      </div>`
    );
  }

  if (q.explanation) {
    parts.push(`<div class="teacher-barem-explanation">${q.explanation}</div>`);
  }

  baremEl.innerHTML = parts.join('');
  baremEl.hidden = parts.length === 0;
}

function renderTeacherQuestionMeta(room, q) {
  const progressEl = document.getElementById('teacherQProgress');
  const typeEl = document.getElementById('teacherQType');
  const game = room?.game;

  if (progressEl && game) {
    progressEl.textContent = isBackendMode() && room?.backendMode
      ? `Câu ${game.questionIndex + 1}`
      : `Câu ${game.questionIndex + 1}/${FAKE_QUESTIONS.length}`;
  }

  if (typeEl) {
    if (q.type === 'mc') {
      typeEl.textContent = 'Trắc nghiệm';
    } else {
      const sub = HTD.INPUT_MODE_LABELS[q.inputMode] || 'Tự luận';
      typeEl.textContent = `Tự luận · ${sub}`;
    }
  }
}

function renderTeacherQuestion(room) {
  const q = getTeacherCurrentQuestion();
  if (!q) return;

  document.getElementById('teacherQuestionPhase').hidden = false;
  document.getElementById('teacherFinalPhase').hidden = true;

  renderTeacherQuestionMeta(room, q);
  renderTeacherQuestionContent(q);

  const answersEl = document.getElementById('teacherQAnswers');
  const eqEl = document.getElementById('teacherQEq');
  if (answersEl) answersEl.innerHTML = '';
  if (eqEl) {
    eqEl.hidden = true;
    eqEl.innerHTML = '';
  }

  if (q.type === 'mc') {
    renderTeacherMcOptions(q, 'teacherQAnswers');
  } else {
    renderTeacherStructuredPreview(q, 'teacherQEq');
  }

  renderTeacherBarem(q);
  updateTeacherTimer(room);
  applyTeacherQZoom();
  scheduleFitTeacherQuestionCard();
}

function updateTeacherTimer(room) {
  const game = room.game;
  const pill = document.getElementById('teacherTimerPill');
  const text = document.getElementById('teacherTimerText');
  if (!pill || !text || !game) return;

  let sec = 0;
  if (game.phase === 'question') {
    sec = HTD.getQuestionTimeRemaining(game);
  } else if (game.phase === 'leaderboard') {
    sec = HTD.getPhaseTimeRemaining(game);
  }

  text.textContent = HTD.formatTimer(sec);
  pill.classList.remove('warn', 'danger', 'paused');
  if (game.paused) pill.classList.add('paused');
  else if (game.phase === 'question') {
    if (sec <= 3) pill.classList.add('danger');
    else if (sec <= 5) pill.classList.add('warn');
  }

  const pauseBtn = document.getElementById('teacherPauseBtn');
  if (pauseBtn) {
    pauseBtn.textContent = game.paused ? 'Tiếp tục' : 'Tạm dừng';
    pauseBtn.disabled = game.phase !== 'question';
  }
}

function renderTeacherIntermission(room) {
  document.getElementById('teacherQuestionPhase').hidden = false;
  document.getElementById('teacherFinalPhase').hidden = true;
  updateTeacherTimer(room);
  renderTeacherScoreList();
}

function renderTeacherPresentationQuestion(room) {
  const game = room?.game;
  const q = getTeacherCurrentQuestion();
  if (!q) return;

  const progressEl = document.getElementById('teacherPresentationProgress');
  const promptEl = document.getElementById('teacherPresentationPrompt');
  const contentEl = document.getElementById('teacherPresentationContent');
  const answersEl = document.getElementById('teacherPresentationAnswers');
  const eqEl = document.getElementById('teacherPresentationEq');
  const imgEl = document.getElementById('teacherPresentationImage');
  const vidWrap = document.getElementById('teacherPresentationVideoWrap');
  const vid = document.getElementById('teacherPresentationVideo');
  if (!progressEl || !answersEl || !eqEl) return;

  progressEl.textContent = isBackendMode() && room?.backendMode
    ? `Câu ${game.questionIndex + 1}`
    : `Câu ${game.questionIndex + 1}/${FAKE_QUESTIONS.length}`;

  if (contentEl && q.contentHtml) {
    contentEl.innerHTML = q.contentHtml;
    contentEl.hidden = false;
    if (promptEl) {
      promptEl.hidden = true;
      promptEl.textContent = '';
    }
  } else if (promptEl) {
    promptEl.hidden = false;
    promptEl.textContent = q.prompt || '';
    if (contentEl) {
      contentEl.innerHTML = '';
      contentEl.hidden = true;
    }
  }

  answersEl.innerHTML = '';
  eqEl.hidden = true;
  eqEl.innerHTML = '';

  if (imgEl) imgEl.hidden = true;
  if (vidWrap) vidWrap.hidden = true;
  if (vid) {
    vid.pause();
    vid.removeAttribute('src');
    vid.removeAttribute('poster');
  }

  if (!q.contentHtml) {
    if (q.media === 'image' && q.mediaUrl && imgEl) {
      imgEl.src = q.mediaUrl;
      imgEl.hidden = false;
    } else if (q.media === 'video' && q.mediaUrl && vid && vidWrap) {
      vid.src = q.mediaUrl;
      if (q.mediaPoster) vid.poster = q.mediaPoster;
      vidWrap.hidden = false;
    }
  }

  if (q.type === 'mc') {
    const options = Array.isArray(q.options) ? q.options : [];
    const correctIdx = q.correctIndex ?? q.correct ?? null;
    answersEl.innerHTML = `<div class="teacher-presentation-mc">${options
      .map((optText, i) => {
        const isCorrect = correctIdx === i;
        return `<div class="teacher-presentation-mc-item${isCorrect ? ' correct' : ''}">
          <span class="teacher-presentation-mc-label">${teacherOptionLetter(i)}.</span>
          <span>${EquationUI.chemToHtml(optText)}</span>
          ${isCorrect ? '<span class="teacher-mc-check">✓</span>' : ''}
        </div>`;
      })
      .join('')}</div>`;
  } else {
    renderTeacherStructuredPreview(q, 'teacherPresentationEq');
  }
}

function renderTeacherPresentationLeaderboard(room) {
  const listEl = document.getElementById('teacherPresentationScoreList');
  if (!listEl) return;
  const players = getTeacherDisplayPlayers();
  initTeacherFakeScores(players);

  const sorted = players
    .map(p => ({ ...p, score: getTeacherPlayerScore(p) }))
    .sort((a, b) => b.score - a.score);

  if (sorted.length === 0) {
    listEl.innerHTML = '<p class="teacher-score-empty">Chưa có học sinh</p>';
    return;
  }

  listEl.innerHTML = sorted.map((p, i) => {
    const av = p.avatarDataUrl ? `<img src="${p.avatarDataUrl}" alt="">` : p.avatarEmoji || '😀';
    const prev = teacherState.prevRanks[p.id];
    let stateCls = '';
    let deltaHtml = '';
    if (prev !== undefined && prev !== i) {
      const up = prev > i;
      stateCls = up ? ' up' : ' down';
      deltaHtml = `<span class="teacher-presentation-delta ${up ? 'up' : 'down'}">${up ? '↑' : '↓'}${Math.abs(prev - i)}</span>`;
    } else if (prev !== undefined) {
      deltaHtml = '<span class="teacher-presentation-delta flat">—</span>';
    }

    return `<div class="teacher-presentation-score-item${stateCls}">
      <span class="teacher-presentation-rank">${i + 1}</span>
      <div class="teacher-presentation-av">${av}</div>
      <div class="teacher-presentation-name">${p.name}</div>
      <div class="teacher-presentation-pts">${p.score}${deltaHtml}</div>
    </div>`;
  }).join('');
}

function updateTeacherPresentationCountdown(room) {
  const game = room?.game;
  if (!game) return;

  if (game.phase === 'leaderboard') {
    const sec = HTD.getPhaseTimeRemaining(game);
    const total = Math.max(1, Math.round(HTD.LEADERBOARD_MS / 1000));
    const ratio = Math.max(0, Math.min(1, sec / total));

    const textEl = document.getElementById('teacherPresentationCountdownText');
    const nextEl = document.getElementById('teacherPresentationNext');
    const ring = document.getElementById('teacherPresentationRing');
    if (!textEl || !nextEl || !ring) return;

    textEl.textContent = String(sec);
    nextEl.textContent = `Vào câu tiếp theo sau ${sec} giây`;
    updateTeacherPresentationRing(ring, ratio);
  }

  if (game.phase === 'question') {
    const sec = HTD.getQuestionTimeRemaining(game);
    const q = getTeacherCurrentQuestion();
    const total = Math.max(1, q?.timeLimit || 1);
    const ratio = Math.max(0, Math.min(1, sec / total));
    const qTextEl = document.getElementById('teacherPresentationQuestionCountdownText');
    const qRing = document.getElementById('teacherPresentationQuestionRing');
    if (!qTextEl || !qRing) return;
    qTextEl.textContent = String(sec);
    updateTeacherPresentationRing(qRing, ratio);
  }
}

function updateTeacherPresentationRing(ring, ratio) {
  const radius = 52;
  const circumference = 2 * Math.PI * radius;
  ring.style.strokeDasharray = `${circumference}`;
  ring.style.strokeDashoffset = `${circumference * (1 - ratio)}`;
}

function animateTeacherLeaderboardScores(sec) {
  if (teacherState.leaderboardAnimSecond === sec || sec <= 0) return;
  teacherState.leaderboardAnimSecond = sec;
  const players = getTeacherDisplayPlayers();
  if (!players.length) return;

  teacherState.prevRanks = {};
  const before = players
    .map(p => ({ ...p, score: getTeacherPlayerScore(p) }))
    .sort((a, b) => b.score - a.score);
  before.forEach((p, i) => {
    teacherState.prevRanks[p.id] = i;
  });

  players.forEach(p => {
    const delta = Math.floor(Math.random() * 65) + 8;
    teacherState.fakeScores[p.id] = getTeacherPlayerScore(p) + delta;
  });
}

function startPresentationTick() {
  clearInterval(teacherState.presentationTick);
  teacherState.presentationTick = setInterval(() => {
    if (!teacherState.presentationOpen) return;
    const room = HTD.getRoom();
    if (!room?.game) return;
    updateTeacherPresentationCountdown(room);
    if (room.game.phase === 'question') updateTeacherTimer(room);
  }, 200);
}

function stopPresentationTick() {
  clearInterval(teacherState.presentationTick);
  teacherState.presentationTick = null;
}

function syncTeacherPresentationFromRoom() {
  if (!teacherState.presentationOpen) return;
  renderTeacherPresentation(HTD.getRoom());
}

function renderTeacherPresentation(room) {
  const wrap = document.getElementById('teacherPresentation');
  const questionScreen = document.getElementById('teacherPresentationQuestion');
  const lbScreen = document.getElementById('teacherPresentationLeaderboard');
  if (!wrap || !questionScreen || !lbScreen) return;
  if (!teacherState.presentationOpen) {
    wrap.hidden = true;
    return;
  }

  wrap.hidden = false;
  if (!room?.game || room.game.phase === 'final' || room.status === 'ended') {
    teacherState.presentationOpen = false;
    stopPresentationTick();
    wrap.hidden = true;
    if (document.fullscreenElement === wrap && document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
    }
    questionScreen.hidden = true;
    lbScreen.hidden = true;
    return;
  }

  if (room.game.phase === 'question') {
    questionScreen.hidden = false;
    lbScreen.hidden = true;
    renderTeacherPresentationQuestion(room);
    updateTeacherPresentationCountdown(room);
  } else if (room.game.phase === 'leaderboard') {
    questionScreen.hidden = true;
    lbScreen.hidden = false;
    renderTeacherPresentationLeaderboard(room);
    updateTeacherPresentationCountdown(room);
  }
}

function toggleTeacherPresentation() {
  const wrap = document.getElementById('teacherPresentation');
  const room = HTD.getRoom();
  if (!wrap || room?.status !== 'started' || !teacherState.presentationAvailable) return;

  const targetOpen = !teacherState.presentationOpen;
  teacherState.presentationOpen = targetOpen;
  renderTeacherPresentation(room);
  closeTeacherActionMenu();

  if (targetOpen) {
    if (document.fullscreenElement !== wrap && wrap.requestFullscreen) {
      wrap.requestFullscreen().catch(() => {});
    }
    startPresentationTick();
  } else {
    stopPresentationTick();
    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
    }
  }
}

function syncTeacherPresentationFullscreenState() {
  const wrap = document.getElementById('teacherPresentation');
  if (!wrap) return;
  if (teacherState.presentationOpen && document.fullscreenElement !== wrap) {
    teacherState.presentationOpen = false;
    renderTeacherPresentation(HTD.getRoom());
  }
}

function renderTeacherFinal() {
  document.getElementById('teacherQuestionPhase').hidden = true;
  document.getElementById('teacherFinalPhase').hidden = false;
  const timerText = document.getElementById('teacherTimerText');
  if (timerText) timerText.textContent = '—';

  const rows = teacherState.finalLeaderboard || [];
  const lbEl = document.getElementById('teacherFinalLeaderboard');
  const room = HTD.getRoom();

  if (lbEl) {
    if (!rows.length) {
      lbEl.innerHTML =
        '<div class="teacher-final-empty">' +
        '<p class="teacher-final-empty-title">Trò chơi đã kết thúc</p>' +
        '<p class="teacher-final-hint">Chưa có học sinh nào ghi điểm trong phòng này.</p>' +
        '</div>';
    } else {
      const top3 = rows.slice(0, 3);
      const podiumMedals = ['🥇', '🥈', '🥉'];
      lbEl.innerHTML =
        '<div class="teacher-final-summary">' +
        `<p class="teacher-final-stat">${rows.length} học sinh · ${teacherState.serverQuestionIndex || room?.game?.questionIndex + 1 || '—'} câu đã chơi</p>` +
        (top3.length
          ? `<div class="teacher-final-podium">${top3
              .map(
                (r, i) =>
                  `<div class="teacher-final-podium-item rank-${i + 1}">
                    <span class="teacher-final-podium-medal">${podiumMedals[i] || i + 1}</span>
                    <strong class="teacher-final-podium-name">${escapeTeacherHtml(r.name)}</strong>
                    <span class="teacher-final-podium-score">${Number(r.score || 0)} đ</span>
                  </div>`
              )
              .join('')}</div>`
          : '') +
        `<table class="teacher-final-table">
          <thead><tr><th>Hạng</th><th>Học sinh</th><th>Điểm</th></tr></thead>
          <tbody>${rows
            .map(
              r => `<tr>
                <td>${r.rank ?? '—'}</td>
                <td>${escapeTeacherHtml(r.name)}</td>
                <td><strong>${Number(r.score || 0)}</strong></td>
              </tr>`
            )
            .join('')}</tbody>
        </table>` +
        '</div>';
    }
  }

  const exportBtn = document.getElementById('btnExportCsv');
  if (exportBtn) exportBtn.hidden = !room?.sessionId;

  renderTeacherScoreList();
}

function renderTeacherGame(room) {
  const game = room.game;
  if (!game) return;

  renderTeacherTimeline(game);

  const needsFullRender =
    game.phase !== teacherState.lastRenderedPhase ||
    (game.phase === 'question' && game.questionIndex !== teacherState.lastRenderedIndex);

  if (game.phase === 'question') {
    if (needsFullRender) renderTeacherQuestion(room);
    else updateTeacherTimer(room);
  } else if (game.phase === 'leaderboard') {
    if (needsFullRender) renderTeacherIntermission(room);
    else {
      updateTeacherTimer(room);
      renderTeacherScoreList();
    }
  } else if (game.phase === 'final') {
    if (needsFullRender) renderTeacherFinal();
  }

  teacherState.lastRenderedPhase = game.phase;
  teacherState.lastRenderedIndex = game.questionIndex;
  renderTeacherPresentation(room);
}

function teacherGameTick() {
  const room = HTD.getRoom();
  if (isBackendMode() && room?.backendMode) return;
  if (!room?.game || room.status !== 'started') return;

  const game = room.game;
  let changed = false;

  if (game.phase === 'question' && !game.paused) {
    const remaining = HTD.getQuestionTimeRemaining(game);
    if (remaining <= 0) {
      HTD.startLeaderboardPhase(room);
      changed = true;
    }
  } else if (game.phase === 'leaderboard') {
    if (teacherState.lastScoreTick !== game.questionIndex) {
      bumpTeacherScoresOnLeaderboard();
      teacherState.lastScoreTick = game.questionIndex;
      teacherState.leaderboardAnimSecond = null;
    }
    const remaining = HTD.getPhaseTimeRemaining(game);
    animateTeacherLeaderboardScores(remaining);
    if (remaining <= 0) {
      const next = game.questionIndex + 1;
      if (next >= FAKE_QUESTIONS.length) {
        HTD.startFinalPhase(room);
      } else {
        HTD.startQuestionPhase(room, next);
      }
      changed = true;
    }
  }

  if (changed) HTD.setRoom(room);
  const room2 = HTD.getRoom();
  if (room2?.game?.phase === 'question') {
    updateTeacherTimer(room2);
    renderTeacherTimeline(room2.game);
  } else if (room2?.game?.phase === 'leaderboard') {
    updateTeacherTimer(room2);
    renderTeacherTimeline(room2.game);
    renderTeacherScoreList();
  } else if (changed) {
    renderTeacherGame(room2);
  }
  renderTeacherPresentation(room2);
}

function syncModalPinDigits() {
  const src = document.getElementById('teacherPinDigits');
  const dst = document.getElementById('teacherPinDigitsModal');
  if (!src || !dst) return;
  dst.innerHTML = src.innerHTML;
}

function updateTeacherJoinQr(room) {
  const qrUrl =
    room?.qrUrl ||
    window.ADMIN_BOOT?.session?.qrUrl ||
    (room?.joinUrl
      ? 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=' +
        encodeURIComponent(room.joinUrl)
      : null);
  if (!qrUrl) return;
  document.querySelectorAll('.teacher-qr-presentation .qr-mock-img, .teacher-qr-modal-frame .qr-mock-img').forEach(img => {
    img.src = qrUrl;
    img.alt = 'QR tham gia phòng ' + (room?.pin || '');
  });
}

function wireTeacherRoomQuickActions(room) {
  const copyPinBtn = document.getElementById('teacherCopyPinBtn');
  const copyLinkBtn = document.getElementById('teacherCopyLinkBtn');
  if (copyPinBtn && !copyPinBtn.dataset.wired) {
    copyPinBtn.dataset.wired = '1';
    copyPinBtn.addEventListener('click', () => {
      if (room?.pin) navigator.clipboard.writeText(room.pin);
    });
  }
  if (copyLinkBtn && !copyLinkBtn.dataset.wired) {
    copyLinkBtn.dataset.wired = '1';
    copyLinkBtn.addEventListener('click', () => {
      if (room?.joinUrl) navigator.clipboard.writeText(room.joinUrl);
    });
  }
}

function isTeacherBackendRoom() {
  return isBackendMode() && (HTD.getRoom()?.backendMode || Boolean(window.ADMIN_BOOT?.session));
}

function getTeacherRealPlayers() {
  const realPlayers = HTD.getPlayers();
  if (isBackendMode()) {
    return realPlayers.filter(p => !p.isFake);
  }
  return HTD.buildDisplayPlayers(realPlayers, null, 50);
}

function getTeacherWaitingPlayers() {
  const players = getTeacherRealPlayers();
  if (isBackendMode()) {
    return players.filter(p => p.connected !== false);
  }
  return players;
}

function getTeacherDisplayPlayers() {
  return getTeacherRealPlayers();
}

function initTeacherFakeScores(players) {
  if (isBackendMode()) return;
  players.forEach(p => {
    if (p.isFake && teacherState.fakeScores[p.id] === undefined) {
      teacherState.fakeScores[p.id] = Math.floor(Math.random() * 300);
    }
  });
}

function getTeacherPlayerScore(p) {
  if (isBackendMode() && HTD.getRoom()?.backendMode) {
    return teacherState.playerScores[p.name] ?? 0;
  }
  if (p.isFake) return teacherState.fakeScores[p.id] || 0;
  return teacherState.fakeScores[p.id] || 0;
}

function bumpTeacherScoresOnLeaderboard() {
  if (isBackendMode()) return;
  const players = getTeacherDisplayPlayers();
  teacherState.prevRanks = {};
  const sorted = players
    .map(p => ({ ...p, score: getTeacherPlayerScore(p) }))
    .sort((a, b) => b.score - a.score);
  sorted.forEach((p, i) => {
    teacherState.prevRanks[p.id] = i;
  });

  players.forEach(p => {
    const delta = Math.floor(Math.random() * 200) + 50;
    teacherState.fakeScores[p.id] = getTeacherPlayerScore(p) + delta;
  });
}

function renderTeacherScoreList() {
  const el = document.getElementById('teacherScoreList');
  if (!el) return;

  const room = HTD.getRoom();
  const isEnded = isTeacherRoomEnded(room);

  if (isEnded && teacherState.finalLeaderboard?.length) {
    el.innerHTML = teacherState.finalLeaderboard
      .map((row, i) => {
        const rank = row.rank ?? i + 1;
        const rankCls = rank <= 3 ? ` r${rank}` : '';
        return `<div class="teacher-score-item">
          <span class="teacher-score-rank${rankCls}">${rank}</span>
          <div class="teacher-score-av">😀</div>
          <div class="teacher-score-info">
            <div class="teacher-score-name">${escapeTeacherHtml(row.name)}</div>
          </div>
          <div class="teacher-score-pts">
            <span class="teacher-score-value">${Number(row.score || 0)}</span>
          </div>
        </div>`;
      })
      .join('');
    return;
  }

  const players = getTeacherDisplayPlayers();
  initTeacherFakeScores(players);

  if (players.length === 0) {
    el.innerHTML = '<p class="teacher-score-empty">Chưa có học sinh</p>';
    return;
  }

  const sorted = players
    .map(p => ({ ...p, score: getTeacherPlayerScore(p) }))
    .sort((a, b) => b.score - a.score);

  el.innerHTML = sorted
    .map((p, i) => {
      const rank = i + 1;
      const prev = teacherState.prevRanks[p.id];
      let deltaHtml = '';
      if (prev !== undefined && prev !== i) {
        const up = prev > i;
        deltaHtml = `<span class="teacher-score-delta ${up ? 'up' : 'down'}">${up ? '↑' : '↓'}${Math.abs(prev - i)}</span>`;
      } else if (prev !== undefined) {
        deltaHtml = '<span class="teacher-score-delta flat">—</span>';
      }
      const av = p.avatarDataUrl
        ? `<img src="${p.avatarDataUrl}" alt="">`
        : p.avatarEmoji || '😀';
      const rankCls = rank <= 3 ? ` r${rank}` : '';
      return `<div class="teacher-score-item">
        <span class="teacher-score-rank${rankCls}">${rank}</span>
        <div class="teacher-score-av">${av}</div>
        <div class="teacher-score-info">
          <div class="teacher-score-name">${p.name}</div>
        </div>
        <div class="teacher-score-pts">
          <span class="teacher-score-value">${p.score}</span>
          ${deltaHtml}
        </div>
      </div>`;
    })
    .join('');
}

function renderTeacherList() {
  const room = HTD.getRoom();
  const inGame = isTeacherRoomInGame(room);
  const isEnded = isTeacherRoomEnded(room);
  const players = inGame ? getTeacherDisplayPlayers() : getTeacherWaitingPlayers();

  document.getElementById('teacherCount').textContent = `${players.length} học sinh`;

  const btnStart = document.getElementById('btnStartGame');
  btnStart.disabled = inGame;
  btnStart.textContent = isEnded ? 'Đã kết thúc' : inGame ? 'Đã bắt đầu' : 'Bắt đầu trò chơi';

  const btnNext = document.getElementById('btnNextQuestion');
  if (btnNext) {
    btnNext.hidden = !(isBackendMode() && inGame && !isEnded);
  }
  const submitEl = document.getElementById('teacherSubmitCount');
  if (submitEl) submitEl.hidden = !(isBackendMode() && inGame && !isEnded);

  if (inGame) {
    renderTeacherScoreList();
    return;
  }

  document.getElementById('teacherList').innerHTML =
    players.length === 0
      ? '<p class="teacher-empty">Chưa có học sinh nào tham gia.<br>Chiếu mã QR hoặc đọc PIN cho lớp.</p>'
      : players
          .map(
            p => `
      <div class="teacher-player${p.connected === false ? ' is-offline' : ''}">
        <div class="teacher-player-av">${p.avatarDataUrl ? `<img src="${p.avatarDataUrl}">` : p.avatarEmoji || '😀'}</div>
        <span class="teacher-player-name">${p.name}</span>
      </div>`
          )
          .join('');
}

function startTeacherPoll() {
  clearInterval(teacherState.poll);
  clearInterval(teacherState.gameTick);
  teacherState.poll = setInterval(() => {
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderDashboard();
    }
  }, 1500);
  teacherState.gameTick = setInterval(() => {
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      teacherGameTick();
    }
  }, 400);
}

function teacherStartGame() {
  const room = HTD.getRoom();
  if (!room || room.status === 'started') return;

  if (isBackendMode()) {
    HTDBridge.hostStartGame()
      .then(() => {
        room.status = 'started';
        room.startedAt = Date.now();
        room.backendMode = true;
        HTD.setRoom(room);
        teacherState.lastRenderedPhase = null;
        teacherState.lastRenderedIndex = -1;
        closeTeacherActionMenu();
        renderDashboard();
      })
      .catch(err => alert(err.message || 'Không bắt đầu được game.'));
    return;
  }

  room.status = 'started';
  room.startedAt = Date.now();
  HTD.initGame(room);
  HTD.setRoom(room);
  teacherState.lastRenderedPhase = null;
  teacherState.lastRenderedIndex = -1;
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.lastScoreTick = -1;
  teacherState.presentationOpen = false;
  teacherState.leaderboardAnimSecond = null;
  closeTeacherActionMenu();
  renderDashboard();
}

function teacherTogglePause() {
  const room = HTD.getRoom();
  if (!room?.game) return;
  if (room.game.paused) HTD.resumeGame(room);
  else HTD.pauseGame(room);
  HTD.setRoom(room);
  closeTeacherActionMenu();
  renderDashboard();
}

function teacherEndGame() {
  if (!confirm('Kết thúc trò chơi ngay?')) return;

  if (isBackendMode()) {
    HTDBridge.hostEndGame().catch(err => alert(err.message || 'Không kết thúc được game.'));
    return;
  }

  const room = HTD.getRoom();
  if (!room) return;
  HTD.startFinalPhase(room);
  HTD.setRoom(room);
  closeTeacherActionMenu();
  renderDashboard();
}

function resetTeacherSessionLocalState() {
  const room = HTD.getRoom();
  if (!room) return;
  room.status = 'waiting';
  room.game = null;
  delete room.startedAt;
  HTD.setRoom(room);
  HTD.setPlayers([]);
  teacherState.finalLeaderboard = [];
  teacherState.currentQuestion = null;
  teacherState.serverQuestionIndex = 0;
  teacherState.playerScores = {};
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.lastRenderedPhase = null;
  teacherState.lastRenderedIndex = -1;
  teacherState.presentationOpen = false;
  teacherState.leaderboardAnimSecond = null;
  teacherState.scorePanelOpen = true;
}

function teacherPlayAgain() {
  if (!confirm('Chơi lại từ đầu với cùng PIN? Kết quả lần trước vẫn lưu trong báo cáo.')) return;
  const room = HTD.getRoom();
  if (!room) return;

  if (isBackendMode() && room.sessionId) {
    HTDApi.resetSession(room.sessionId)
      .then(() => {
        resetTeacherSessionLocalState();
        closeTeacherActionMenu();
        return HTDBridge.joinRoom({
          pin: room.pin,
          name: room.teacher || 'Host',
          isHost: true,
        });
      })
      .then(() => renderDashboard())
      .catch(err => alert(err.message || 'Không reset được phòng.'));
    return;
  }

  HTD.resetGame(room);
  HTD.setRoom(room);
  teacherState.lastRenderedPhase = null;
  teacherState.lastRenderedIndex = -1;
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.lastScoreTick = -1;
  teacherState.presentationOpen = false;
  teacherState.leaderboardAnimSecond = null;
  closeTeacherActionMenu();
  renderDashboard();
}

function resetTeacherRoom() {
  if (!confirm('Tạo phòng mới? Danh sách học sinh sẽ bị xóa.')) return;
  closeTeacherActionMenu();
  localStorage.removeItem(HTD.LS_ROOM);
  localStorage.removeItem(HTD.LS_PLAYERS);
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.presentationOpen = false;
  teacherState.leaderboardAnimSecond = null;
  const presentation = document.getElementById('teacherPresentation');
  if (presentation) presentation.hidden = true;
  if (document.fullscreenElement && document.exitFullscreen) {
    document.exitFullscreen().catch(() => {});
  }
  showTeacherScreen('setup');
}

function setTeacherSidebarCollapsed(collapsed) {
  const sidebar = document.getElementById('teacherSidebar');
  const layout = document.querySelector('.teacher-layout');
  if (!sidebar) return;
  sidebar.classList.toggle('collapsed', collapsed);
  layout?.classList.toggle('sidebar-collapsed', collapsed);
  localStorage.setItem(LS_SIDEBAR_COLLAPSED, collapsed ? '1' : '0');
}

function initTeacherSidebar() {
  setTeacherSidebarCollapsed(localStorage.getItem(LS_SIDEBAR_COLLAPSED) === '1');
}

function toggleTeacherSidebar() {
  const sidebar = document.getElementById('teacherSidebar');
  if (!sidebar) return;
  setTeacherSidebarCollapsed(!sidebar.classList.contains('collapsed'));
  scheduleFitTeacherQuestionCard();
}

function toggleTeacherActionMenu() {
  const menu = document.getElementById('teacherActionMenu');
  const btn = document.getElementById('teacherActionBtn');
  if (!menu || !btn) return;
  teacherState.actionMenuOpen = !teacherState.actionMenuOpen;
  menu.hidden = !teacherState.actionMenuOpen;
  btn.setAttribute('aria-expanded', teacherState.actionMenuOpen ? 'true' : 'false');
}

function closeTeacherActionMenu() {
  const menu = document.getElementById('teacherActionMenu');
  const btn = document.getElementById('teacherActionBtn');
  teacherState.actionMenuOpen = false;
  if (menu) menu.hidden = true;
  if (btn) btn.setAttribute('aria-expanded', 'false');
}

function initTeacherQZoom() {
  const saved = parseFloat(localStorage.getItem(LS_TEACHER_Q_ZOOM));
  teacherState.qZoom = Number.isFinite(saved)
    ? Math.min(TEACHER_ZOOM_MAX, Math.max(TEACHER_ZOOM_MIN, saved))
    : 1;
  teacherState.scorePanelOpen = localStorage.getItem(LS_SCORE_PANEL_OPEN) !== '0';
}

function applyTeacherQZoom() {
  const card = document.getElementById('teacherQCard');
  if (card) card.style.setProperty('--q-zoom', teacherState.qZoom);
  const presentationInner = document.querySelector('.teacher-presentation-inner');
  if (presentationInner) presentationInner.style.setProperty('--presentation-zoom', teacherState.qZoom);
  scheduleFitTeacherQuestionCard();
}

function scheduleFitTeacherQuestionCard() {
  clearTimeout(teacherState.fitTimer);
  teacherState.fitTimer = setTimeout(fitTeacherQuestionCard, 50);
}

function fitTeacherQuestionCard() {
  const fit = document.getElementById('teacherQCardFit');
  const inner = document.getElementById('teacherQCardInner');
  if (!fit || !inner || inner.offsetParent === null) return;

  inner.style.transform = 'translateX(-50%) scale(1)';

  requestAnimationFrame(() => {
    const maxW = fit.clientWidth;
    const maxH = fit.clientHeight;
    const naturalW = inner.offsetWidth;
    const naturalH = inner.scrollHeight;
    if (!maxW || !maxH || !naturalW || !naturalH) return;

    const scale = Math.min(1, maxW / naturalW, maxH / naturalH);
    inner.style.transform = `translateX(-50%) scale(${scale})`;
  });
}

function initTeacherQuestionFitObserver() {
  if (teacherState.fitObserver) return;
  const card = document.getElementById('teacherQCard');
  if (!card || typeof ResizeObserver === 'undefined') return;
  teacherState.fitObserver = new ResizeObserver(() => scheduleFitTeacherQuestionCard());
  teacherState.fitObserver.observe(card);
  const phase = document.getElementById('teacherQuestionPhase');
  if (phase) teacherState.fitObserver.observe(phase);
  window.addEventListener('resize', scheduleFitTeacherQuestionCard);
}

function teacherZoomIn() {
  teacherState.qZoom = Math.min(TEACHER_ZOOM_MAX, +(teacherState.qZoom + TEACHER_ZOOM_STEP).toFixed(2));
  localStorage.setItem(LS_TEACHER_Q_ZOOM, teacherState.qZoom);
  applyTeacherQZoom();
}

function teacherZoomOut() {
  teacherState.qZoom = Math.max(TEACHER_ZOOM_MIN, +(teacherState.qZoom - TEACHER_ZOOM_STEP).toFixed(2));
  localStorage.setItem(LS_TEACHER_Q_ZOOM, teacherState.qZoom);
  applyTeacherQZoom();
}

function toggleTeacherScorePanel() {
  teacherState.scorePanelOpen = !teacherState.scorePanelOpen;
  localStorage.setItem(LS_SCORE_PANEL_OPEN, teacherState.scorePanelOpen ? '1' : '0');
  renderDashboard();
  scheduleFitTeacherQuestionCard();
}

function updateTeacherScorePanelBtn() {
  const started = HTD.getRoom()?.status === 'started';

  const presentMenuBtn = document.getElementById('teacherPresentationMenuBtn');
  if (presentMenuBtn) {
    presentMenuBtn.hidden = !started || !teacherState.presentationAvailable;
    presentMenuBtn.textContent = teacherState.presentationOpen ? 'Tắt trình chiếu' : 'Bật trình chiếu';
  }
  const scoreMenuBtn = document.getElementById('teacherToggleScoresMenuBtn');
  if (scoreMenuBtn) {
    scoreMenuBtn.hidden = !started;
    scoreMenuBtn.textContent = teacherState.scorePanelOpen ? 'Ẩn bảng điểm' : 'Hiện bảng điểm';
  }
}

function initTeacherDashboardUi() {
  if (teacherState.dashboardUiReady) return;
  teacherState.dashboardUiReady = true;
  initTeacherQZoom();

  document.getElementById('teacherQrEnlargeBtn')?.addEventListener('click', openTeacherQrModal);
  document.getElementById('teacherQrModalBackdrop')?.addEventListener('click', closeTeacherQrModal);
  document.getElementById('teacherQrModalClose')?.addEventListener('click', closeTeacherQrModal);
  document.getElementById('teacherActionBtn')?.addEventListener('click', e => {
    e.stopPropagation();
    toggleTeacherActionMenu();
  });
  document.addEventListener('click', () => closeTeacherActionMenu());
  document.addEventListener('fullscreenchange', syncTeacherPresentationFullscreenState);

  initTeacherQuestionFitObserver();
  initTeacherMainResizer();
}

function initTeacherMainResizer() {
  const grid = document.querySelector('.teacher-main-grid');
  const resizer = document.getElementById('teacherMainResizer');
  if (!grid || !resizer || resizer.dataset.bound === '1') return;
  resizer.dataset.bound = '1';

  const saved = parseFloat(localStorage.getItem(LS_MAIN_SPLIT));
  if (!Number.isNaN(saved)) applyMainSplit(grid, saved);

  let dragging = false;

  function onMouseDown(e) {
    dragging = true;
    document.body.classList.add('teacher-resizing');
    e.preventDefault();
  }

  function onMouseMove(e) {
    if (!dragging) return;
    const rect = grid.getBoundingClientRect();
    let ratio = (e.clientX - rect.left) / rect.width;
    ratio = Math.min(0.72, Math.max(0.28, ratio));
    applyMainSplit(grid, ratio);
  }

  function onMouseUp() {
    if (!dragging) return;
    dragging = false;
    document.body.classList.remove('teacher-resizing');
    const match = grid.style.gridTemplateColumns.match(/^([\d.]+)fr/);
    if (match) localStorage.setItem(LS_MAIN_SPLIT, match[1]);
    scheduleFitTeacherQuestionCard();
  }

  resizer.addEventListener('mousedown', onMouseDown);
  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
}

function applyMainSplit(grid, leftRatio) {
  const room = HTD.getRoom();
  const inGame = isTeacherRoomInGame(room);
  const isEnded = isTeacherRoomEnded(room);
  if (inGame && !isEnded && !teacherState.scorePanelOpen) {
    grid.style.gridTemplateColumns = '1fr';
    return;
  }
  if (inGame) {
    grid.style.gridTemplateColumns = 'minmax(0, 1fr) 20px minmax(220px, 280px)';
    return;
  }
  const rightRatio = 1 - leftRatio;
  grid.style.gridTemplateColumns = `${leftRatio}fr 20px ${rightRatio}fr`;
}

function openTeacherQrModal() {
  const modal = document.getElementById('teacherQrModal');
  if (!modal) return;
  syncModalPinDigits();
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
}

function closeTeacherQrModal() {
  const modal = document.getElementById('teacherQrModal');
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
}

window.teacherZoomIn = teacherZoomIn;
window.teacherZoomOut = teacherZoomOut;
window.toggleTeacherScorePanel = toggleTeacherScorePanel;
window.openTeacherQrModal = openTeacherQrModal;
window.closeTeacherQrModal = closeTeacherQrModal;
window.toggleTeacherSidebar = toggleTeacherSidebar;
window.resetTeacherRoom = resetTeacherRoom;
window.teacherStartGame = teacherStartGame;
window.teacherNextQuestion = teacherNextQuestion;
window.teacherTogglePause = teacherTogglePause;
window.teacherEndGame = teacherEndGame;
window.teacherPlayAgain = teacherPlayAgain;
window.toggleTeacherPresentation = toggleTeacherPresentation;
window.createTeacherRoom = createTeacherRoom;
window.teacherExportCsv = teacherExportCsv;
window.demoTeacherGo = demoTeacherGo;

const TEACHER_SCREENS = ['setup', 'dashboard'];
const demoNav = document.getElementById('demoNav');
if (demoNav) {
  demoNav.innerHTML = TEACHER_SCREENS.map(
    s => `<button onclick="demoTeacherGo('${s}')">${s}</button>`
  ).join('');
}

function demoTeacherGo(s) {
  if (s === 'dashboard' && !HTD.getRoom()) createTeacherRoom();
  showTeacherScreen(s);
}

function teacherNextQuestion() {
  if (!isBackendMode()) return;
  HTDBridge.hostNextQuestion().catch(err => alert(err.message || 'Không chuyển câu được.'));
}

function teacherExportCsv() {
  const sessionId = HTD.getRoom()?.sessionId;
  if (!sessionId) {
    alert('Không tìm thấy session. Đăng nhập admin trước khi tải CSV.');
    return;
  }
  HTDApi.exportSessionCsv(sessionId);
}

function setupTeacherBackendBridge() {
  if (!isBackendMode()) return;

  HTDBridge.on('gameStarted', () => {
    const room = HTD.getRoom();
    if (!room) return;
    room.status = 'started';
    room.backendMode = true;
    teacherState.serverQuestionIndex = 0;
    teacherState.currentQuestion = null;
    const game = ensureTeacherGameState(room);
    game.phase = 'question';
    game.questionIndex = 0;
    room.startedAt = Date.now();
    HTD.setRoom(room);
    teacherState.lastRenderedPhase = null;
    teacherState.lastRenderedIndex = -1;
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderDashboard();
    }
    syncTeacherPresentationFromRoom();
  });

  HTDBridge.on('newQuestion', payload => {
    const room = HTD.getRoom();
    if (!room) return;
    teacherState.serverQuestionIndex += 1;
    teacherState.currentQuestion = HTDGameAdapter.mapNewQuestion(
      payload,
      teacherState.serverQuestionIndex - 1
    );
    const game = ensureTeacherGameState(room);
    game.phase = 'question';
    game.questionIndex = teacherState.serverQuestionIndex - 1;
    const startedAt = Number(payload.server_time || Date.now());
    const limit = Number(payload.time_limit || 30);
    game.questionStartedAt = startedAt;
    game.questionDuration = limit;
    game.timerEndsAt = startedAt + limit * 1000;
    game.phaseEndsAt = null;
    game.paused = false;
    room.backendMode = true;
    room.status = 'started';
    HTD.setRoom(room);
    teacherState.lastRenderedPhase = null;
    teacherState.lastRenderedIndex = -1;
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderDashboard();
    }
    syncTeacherPresentationFromRoom();
  });

  HTDBridge.on('playersUpdate', data => {
    const players = HTDGameAdapter.mapPlayersUpdate(data.players);
    players.forEach(p => {
      teacherState.playerScores[p.name] = p.score;
    });
    HTD.setPlayers(
      players.map(p => ({
        id: p.id,
        name: p.name,
        avatarEmoji: p.avatarEmoji,
        connected: p.connected !== false,
        joinedAt: Date.now(),
      }))
    );
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderTeacherList();
      if (HTD.getRoom()?.status === 'started') renderTeacherScoreList();
    }
  });

  HTDBridge.on('leaderboardUpdate', data => {
    (data.top5 || []).forEach(row => {
      teacherState.playerScores[row.name] = Number(row.score || 0);
    });
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderTeacherScoreList();
    }
    syncTeacherPresentationFromRoom();
  });

  HTDBridge.on('submitCountUpdate', data => {
    const el = document.getElementById('teacherSubmitCount');
    if (el) el.textContent = `${data.submitted}/${data.total} đã nộp`;
  });

  HTDBridge.on('gameEnded', data => {
    const room = HTD.getRoom();
    if (!room) return;
    room.status = 'ended';
    const game = ensureTeacherGameState(room);
    game.phase = 'final';
    teacherState.finalLeaderboard = data.final_leaderboard || [];
    (teacherState.finalLeaderboard || []).forEach(row => {
      teacherState.playerScores[row.name] = Number(row.score || 0);
    });
    HTD.setRoom(room);
    teacherState.lastRenderedPhase = null;
    teacherState.lastRenderedIndex = -1;
    teacherState.scorePanelOpen = true;
    stopPresentationTick();
    if (teacherState.presentationOpen) {
      teacherState.presentationOpen = false;
      const wrap = document.getElementById('teacherPresentation');
      if (wrap) wrap.hidden = true;
    }
    renderDashboard();
  });
}

setupTeacherBackendBridge();
// init via admin-session-init.js when embedded in Laravel admin
