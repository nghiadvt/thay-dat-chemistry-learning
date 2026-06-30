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
};
const LS_SIDEBAR_COLLAPSED = 'htd_teacher_sidebar_collapsed';
const LS_MAIN_SPLIT = 'htd_teacher_main_split';
const FAKE_QUESTIONS = HTD.FAKE_QUESTIONS;

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
  const room = HTD.getRoom();
  if (room && room.status !== 'ended') {
    showTeacherScreen('dashboard');
  } else {
    showTeacherScreen('setup');
  }
}

function createTeacherRoom() {
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

function renderDashboard() {
  const room = HTD.getRoom();
  if (!room) return;

  document.getElementById('teacherRoomName').textContent = room.name;
  const teacherEl = document.getElementById('teacherTeacherName');
  if (teacherEl) teacherEl.textContent = 'Giáo viên: ' + (room.teacher || 'Thầy Đạt');
  renderTeacherPinDigits(room.pin);

  const started = room.status === 'started';
  document.getElementById('teacherRoomCard')?.classList.toggle('game-active', started);
  document.getElementById('teacherRoomHeader')?.classList.toggle('compact', started);
  document.getElementById('teacherRoomInfo')?.classList.toggle('compact', started);

  const statusPill = document.getElementById('teacherStatusPill');
  statusPill.textContent = started
    ? room.game?.phase === 'final'
      ? 'Kết thúc'
      : room.game?.paused
        ? 'Tạm dừng'
        : 'Đang chơi'
    : 'Đang chờ';
  statusPill.classList.toggle('started', started);

  const waitingView = document.getElementById('teacherWaitingView');
  const gameView = document.getElementById('teacherGameView');
  const mainGrid = document.querySelector('.teacher-main-grid');
  const playersPanel = document.getElementById('teacherPlayersPanel');
  const btnEndRoom = document.getElementById('btnEndRoom');
  const panelTitle = document.getElementById('teacherPanelTitle');
  const playerGrid = document.getElementById('teacherList');
  const scoreList = document.getElementById('teacherScoreList');

  if (waitingView) waitingView.hidden = started;
  if (gameView) gameView.hidden = !started;
  mainGrid?.classList.toggle('game-active', started);
  mainGrid?.classList.toggle('scores-collapsed', started && !teacherState.scorePanelOpen);
  playersPanel?.classList.toggle('game-active', started);
  document.getElementById('teacherMainResizer')?.classList.toggle('hidden', started && !teacherState.scorePanelOpen);
  updateTeacherScorePanelBtn();
  applyTeacherQZoom();
  if (btnEndRoom) btnEndRoom.hidden = started;
  if (panelTitle) panelTitle.textContent = started ? 'Bảng điểm' : 'Danh sách học sinh';
  if (playerGrid) playerGrid.hidden = started;
  if (scoreList) scoreList.hidden = !started || !teacherState.scorePanelOpen;
  if (playersPanel) playersPanel.hidden = started && !teacherState.scorePanelOpen;

  if (started && room.game) {
    renderTeacherGame(room);
  }

  renderTeacherList();
}

function renderTeacherTimeline(game) {
  const el = document.getElementById('teacherTimeline');
  if (!el) return;
  el.innerHTML = FAKE_QUESTIONS.map((q, i) => {
    let cls = 'teacher-timeline-item';
    if (i < game.questionIndex) cls += ' done';
    else if (i === game.questionIndex && game.phase !== 'final') cls += ' active';
    else if (game.phase === 'final') cls += ' done';
    const label = i + 1;
    return `<div class="${cls}" title="Câu ${label} · ${q.id}"><span>${label}</span></div>`;
  }).join('');
}

function renderTeacherQuestion(room) {
  const game = room.game;
  const q = FAKE_QUESTIONS[game.questionIndex];
  if (!q) return;

  document.getElementById('teacherQuestionPhase').hidden = false;
  document.getElementById('teacherFinalPhase').hidden = true;

  const typeEl = document.getElementById('teacherQType');
  if (q.type === 'mc') {
    typeEl.textContent = 'Trắc nghiệm';
  } else {
    const sub = HTD.INPUT_MODE_LABELS[q.inputMode] || 'Tự luận';
    typeEl.textContent = `Tự luận · ${sub}`;
  }

  document.getElementById('teacherQPrompt').textContent = q.prompt || '';

  const img = document.getElementById('teacherQImage');
  const vidWrap = document.getElementById('teacherQVideoWrap');
  const vid = document.getElementById('teacherQVideo');
  img.hidden = true;
  vidWrap.hidden = true;
  vid.pause();
  vid.removeAttribute('src');

  if (q.media === 'image' && q.mediaUrl) {
    img.src = q.mediaUrl;
    img.hidden = false;
    img.onload = () => scheduleFitTeacherQuestionCard();
  } else if (q.media === 'video' && q.mediaUrl) {
    vid.src = q.mediaUrl;
    if (q.mediaPoster) vid.poster = q.mediaPoster;
    vidWrap.hidden = false;
    vid.onloadedmetadata = () => scheduleFitTeacherQuestionCard();
  }

  const answersEl = document.getElementById('teacherQAnswers');
  const eqEl = document.getElementById('teacherQEq');
  answersEl.innerHTML = '';
  eqEl.hidden = true;

  if (q.type === 'mc') {
    answersEl.innerHTML = `<div class="teacher-mc-options">${['A', 'B', 'C', 'D']
      .map((label, i) => {
        const optText = q.options[i] || '';
        return `<div class="teacher-mc-option">
          <span class="teacher-mc-label">${label}.</span>
          <span class="teacher-mc-text">${EquationUI.chemToHtml(optText)}</span>
        </div>`;
      })
      .join('')}</div>`;
  } else if (q.template) {
    eqEl.hidden = false;
    const values = EquationUI.createInputState(q.template);
    eqEl.innerHTML = EquationUI.renderEquation(q.template, values, null);
  }

  updateTeacherTimer(room);
  applyTeacherQZoom();
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

function renderTeacherFinal() {
  document.getElementById('teacherQuestionPhase').hidden = true;
  document.getElementById('teacherFinalPhase').hidden = false;
  document.getElementById('teacherTimerText').textContent = '—';
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
}

function teacherGameTick() {
  const room = HTD.getRoom();
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
    }
    const remaining = HTD.getPhaseTimeRemaining(game);
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
}

function syncModalPinDigits() {
  const src = document.getElementById('teacherPinDigits');
  const dst = document.getElementById('teacherPinDigitsModal');
  if (!src || !dst) return;
  dst.innerHTML = src.innerHTML;
}

function getTeacherDisplayPlayers() {
  const realPlayers = HTD.getPlayers();
  return HTD.buildDisplayPlayers(realPlayers, null, 50);
}

function initTeacherFakeScores(players) {
  players.forEach(p => {
    if (p.isFake && teacherState.fakeScores[p.id] === undefined) {
      teacherState.fakeScores[p.id] = Math.floor(Math.random() * 300);
    }
  });
}

function getTeacherPlayerScore(p) {
  if (p.isFake) return teacherState.fakeScores[p.id] || 0;
  return teacherState.fakeScores[p.id] || 0;
}

function bumpTeacherScoresOnLeaderboard() {
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
  const players = getTeacherDisplayPlayers();
  const started = room?.status === 'started';

  document.getElementById('teacherCount').textContent = `${players.length} học sinh`;

  const btnStart = document.getElementById('btnStartGame');
  btnStart.disabled = started;
  btnStart.textContent = started ? 'Đã bắt đầu' : 'Bắt đầu trò chơi';

  if (started) {
    renderTeacherScoreList();
    return;
  }

  document.getElementById('teacherList').innerHTML =
    players.length === 0
      ? '<p class="teacher-empty">Chưa có học sinh nào tham gia.<br>Chiếu mã QR hoặc đọc PIN cho lớp.</p>'
      : players
          .map(
            p => `
      <div class="teacher-player">
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
  room.status = 'started';
  room.startedAt = Date.now();
  HTD.initGame(room);
  HTD.setRoom(room);
  teacherState.lastRenderedPhase = null;
  teacherState.lastRenderedIndex = -1;
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.lastScoreTick = -1;
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
  const room = HTD.getRoom();
  if (!room) return;
  HTD.startFinalPhase(room);
  HTD.setRoom(room);
  closeTeacherActionMenu();
  renderDashboard();
}

function teacherPlayAgain() {
  if (!confirm('Chơi lại từ đầu? Học sinh sẽ quay về phòng chờ.')) return;
  const room = HTD.getRoom();
  if (!room) return;
  HTD.resetGame(room);
  HTD.setRoom(room);
  teacherState.lastRenderedPhase = null;
  teacherState.lastRenderedIndex = -1;
  teacherState.fakeScores = {};
  teacherState.prevRanks = {};
  teacherState.lastScoreTick = -1;
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
  const btn = document.getElementById('teacherToggleScoresBtn');
  if (!btn) return;
  const started = HTD.getRoom()?.status === 'started';
  btn.hidden = !started;
  btn.textContent = teacherState.scorePanelOpen ? 'Ẩn bảng điểm' : 'Hiện bảng điểm';
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
  const started = HTD.getRoom()?.status === 'started';
  if (started && !teacherState.scorePanelOpen) {
    grid.style.gridTemplateColumns = '1fr';
    return;
  }
  if (started) {
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
window.teacherTogglePause = teacherTogglePause;
window.teacherEndGame = teacherEndGame;
window.teacherPlayAgain = teacherPlayAgain;
window.createTeacherRoom = createTeacherRoom;
window.demoTeacherGo = demoTeacherGo;

const TEACHER_SCREENS = ['setup', 'dashboard'];
document.getElementById('demoNav').innerHTML = TEACHER_SCREENS.map(
  s => `<button onclick="demoTeacherGo('${s}')">${s}</button>`
).join('');

function demoTeacherGo(s) {
  if (s === 'dashboard' && !HTD.getRoom()) createTeacherRoom();
  showTeacherScreen(s);
}

initTeacherApp();
