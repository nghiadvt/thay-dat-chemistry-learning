/** Student app logic */
const state = {
  pinDigits: '',
  joinTab: 'pin',
  playerId: null,
  studentName: '',
  avatarDataUrl: null,
  avatarEmoji: '😀',
  questionIndex: 0,
  myScore: 0,
  timer: 0,
  timerInterval: null,
  lbInterval: null,
  selectedAnswer: null,
  inputValues: null,
  eqController: null,
  focusedSlotId: null,
  keyboardOpen: true,
  submitted: false,
  students: [],
  displayPlayers: [],
  fakeScores: {},
  prevRanks: {},
  cameraStream: null,
  waitingPoll: null,
  waitingJoinedAt: 0,
  gamePoll: null,
  lastGameQuestionIndex: -1,
};

const FAKE_QUESTIONS = HTD.FAKE_QUESTIONS;

function showScreen(id) {
  stopCamera();
  if (state.screen === 'question') {
    document.getElementById('qVideo')?.pause();
  }
  HTD.showScreen(id);
  state.screen = id;

  if (id === 'join') initJoin();
  if (id === 'profile') initProfile();
  if (id === 'waiting') {
    renderWaitingGrid();
    startWaitingPoll();
  } else {
    clearInterval(state.waitingPoll);
  }
  if (id === 'question') startQuestion();
  if (id === 'leaderboard') renderLeaderboard();
  if (id === 'final') renderFinal();
  syncGamePoll();
}

// ─── Join PIN / QR ───
function initJoin() {
  state.pinDigits = '';
  renderPinDisplay();
  buildNumpad();
  switchJoinTab('pin');
}

function switchJoinTab(tab) {
  state.joinTab = tab;
  document.getElementById('tabPin').classList.toggle('active', tab === 'pin');
  document.getElementById('tabQr').classList.toggle('active', tab === 'qr');
  document.getElementById('joinPinContent').style.display = tab === 'pin' ? 'flex' : 'none';
  document.getElementById('joinQrContent').style.display = tab === 'qr' ? 'flex' : 'none';
  document.getElementById('numpadPin').style.display = tab === 'pin' ? 'grid' : 'none';
}

function renderPinDisplay() {
  const el = document.getElementById('pinDisplay');
  el.innerHTML = Array.from({ length: 6 }, (_, i) => {
    const filled = i < state.pinDigits.length;
    const isCursor = i === state.pinDigits.length && state.pinDigits.length < 6;
    let cls = 'pin-dot';
    if (filled) cls += ' filled';
    else if (isCursor) cls += ' cursor';
    return `<div class="${cls}">${state.pinDigits[i] || ''}</div>`;
  }).join('');
  const btn = document.getElementById('btnJoinSubmit');
  if (btn) btn.disabled = state.pinDigits.length !== 6;
}

function submitPinManual() {
  if (state.pinDigits.length === 6) validatePinAndContinue();
}

function buildNumpad() {
  const pad = document.getElementById('numpadPin');
  if (pad.dataset.built) return;
  pad.dataset.built = '1';
  const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'];
  pad.innerHTML = keys.map(k => {
    if (k === '') return '<div></div>';
    const cls = k === '⌫' ? 'numpad-key del' : 'numpad-key';
    return `<button class="${cls}" data-key="${k}">${k}</button>`;
  }).join('');
  pad.querySelectorAll('.numpad-key').forEach(btn => {
    btn.onclick = () => {
      const key = btn.dataset.key;
      if (key === '⌫') state.pinDigits = state.pinDigits.slice(0, -1);
      else if (state.pinDigits.length < 6) state.pinDigits += key;
      renderPinDisplay();
    };
  });
}

function validatePinAndContinue() {
  const room = HTD.getRoom();
  if (!room || state.pinDigits !== room.pin) {
    alert(room ? 'PIN không đúng!' : 'Chưa có phòng. Giáo viên cần tạo phòng trước.');
    state.pinDigits = '';
    renderPinDisplay();
    return;
  }
  showScreen('profile');
}

function simulateQrScan() {
  const room = HTD.getRoom();
  if (!room) {
    alert('Chưa có phòng. Giáo viên mở teacher.html để tạo phòng trước.');
    return;
  }
  state.pinDigits = room.pin;
  validatePinAndContinue();
}

(function checkUrlPin() {
  const p = new URLSearchParams(location.search).get('pin');
  if (p) {
    state.pinDigits = p.replace(/\D/g, '').slice(0, 6);
    setTimeout(() => {
      showScreen('join');
      if (state.pinDigits.length === 6) validatePinAndContinue();
    }, 100);
  }
})();

// ─── Profile ───
function initProfile() {
  document.getElementById('profileName').value = state.studentName;
  onProfileNameInput();
  updateProfileAvatarUI();
}

function onProfileNameInput() {
  const len = document.getElementById('profileName').value.trim().length;
  document.getElementById('profileCharCount').textContent = len;
  document.getElementById('btnProfileJoin').disabled = len === 0;
}

function updateProfileAvatarUI() {
  const emoji = document.getElementById('profileEmoji');
  const img = document.getElementById('profileImg');
  const video = document.getElementById('profileVideo');
  emoji.style.display = 'none';
  img.style.display = 'none';
  video.style.display = 'none';
  if (state.avatarDataUrl) {
    img.src = state.avatarDataUrl;
    img.style.display = 'block';
  } else {
    emoji.textContent = state.avatarEmoji;
    emoji.style.display = 'block';
  }
}

async function onAvatarClick(fromWaiting) {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user' },
      audio: false,
    });
    state.cameraStream = stream;
    const vidId = fromWaiting ? 'waitingVideo' : 'profileVideo';
    let video = document.getElementById(vidId);
    if (!video && fromWaiting) {
      video = document.createElement('video');
      video.id = 'waitingVideo';
      video.autoplay = true;
      video.playsInline = true;
      video.style.cssText =
        'position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:50;background:#000';
      document.body.appendChild(video);
      const bar = document.createElement('div');
      bar.id = 'waitingCaptureBar';
      bar.style.cssText =
        'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:51;width:90%;max-width:320px';
      bar.innerHTML =
        '<button class="btn-primary" onclick="captureWaitingPhoto()">Chụp</button><button class="btn-secondary" onclick="cancelWaitingCapture()">Huỷ</button>';
      document.body.appendChild(bar);
    }
    video.srcObject = stream;
    video.style.display = 'block';
    if (!fromWaiting) {
      document.getElementById('profileEmoji').style.display = 'none';
      document.getElementById('profileImg').style.display = 'none';
      document.getElementById('captureBar').classList.add('show');
    }
  } catch {
    alert('Không truy cập được camera.');
  }
}

function captureProfilePhoto() {
  const video = document.getElementById('profileVideo');
  const c = document.createElement('canvas');
  c.width = 240;
  c.height = 240;
  c.getContext('2d').drawImage(video, 0, 0, 240, 240);
  state.avatarDataUrl = c.toDataURL('image/jpeg', 0.8);
  stopCamera();
  document.getElementById('captureBar').classList.remove('show');
  updateProfileAvatarUI();
}

function cancelCapture() {
  stopCamera();
  document.getElementById('captureBar').classList.remove('show');
  updateProfileAvatarUI();
}

function captureWaitingPhoto() {
  const video = document.getElementById('waitingVideo');
  const c = document.createElement('canvas');
  c.width = 240;
  c.height = 240;
  c.getContext('2d').drawImage(video, 0, 0, 240, 240);
  state.avatarDataUrl = c.toDataURL('image/jpeg', 0.8);
  cancelWaitingCapture();
  saveMyPlayer();
  renderWaitingGrid();
}

function cancelWaitingCapture() {
  stopCamera();
  document.getElementById('waitingVideo')?.remove();
  document.getElementById('waitingCaptureBar')?.remove();
}

function stopCamera() {
  state.cameraStream?.getTracks().forEach(t => t.stop());
  state.cameraStream = null;
}

function submitProfile() {
  state.studentName = document.getElementById('profileName').value.trim().slice(0, 20);
  if (!state.studentName) return;
  state.playerId = HTD.genId();
  state.waitingJoinedAt = Date.now();
  saveMyPlayer();
  showScreen('waiting');
}

function saveMyPlayer() {
  const players = HTD.getPlayers().filter(p => p.id !== state.playerId);
  players.push({
    id: state.playerId,
    name: state.studentName,
    avatarDataUrl: state.avatarDataUrl,
    avatarEmoji: state.avatarEmoji,
    joinedAt: Date.now(),
  });
  HTD.setPlayers(players);
}

function saveEditName() {
  const n = document.getElementById('editNameInput').value.trim();
  if (!n) {
    alert('Tên không được để trống.');
    document.getElementById('editNameInput').value = state.studentName;
    return;
  }
  state.studentName = n.slice(0, 20);
  saveMyPlayer();
  renderWaitingGrid();
}

function deleteMyPhoto() {
  state.avatarDataUrl = null;
  saveMyPlayer();
  renderWaitingGrid();
}

// ─── Simulated players (50 HS) ───
function initDisplayPlayers() {
  const real = HTD.getPlayers();
  state.displayPlayers = HTD.buildDisplayPlayers(real, state.playerId, 50);
  state.displayPlayers.forEach(p => {
    if (p.isFake && state.fakeScores[p.id] === undefined) {
      state.fakeScores[p.id] = Math.floor(Math.random() * 300);
    }
  });
}

function getPlayersForWaiting() {
  initDisplayPlayers();
  return state.displayPlayers.map(p => ({
    ...p,
    isMe: p.id === state.playerId,
  }));
}

// ─── Waiting ───
function renderWaitingGrid() {
  const room = HTD.getRoom();
  if (room) {
    document.getElementById('waitingRoomName').textContent = room.name;
    document.getElementById('waitingTeacher').textContent = 'GV: ' + room.teacher;
    document.getElementById('waitingPinDisplay').textContent = room.pin;
  }
  const players = getPlayersForWaiting();
  document.getElementById('playersGrid').innerHTML = players
    .map(p => {
      const isMe = p.id === state.playerId;
      const av = p.avatarDataUrl
        ? `<img src="${p.avatarDataUrl}">`
        : p.avatarEmoji || '😀';
      return `<div class="player-card${isMe ? ' me' : ''}">
      <div class="player-av">${av}</div>
      <div class="player-name">${p.name}${isMe ? ' (bạn)' : ''}</div>
    </div>`;
    })
    .join('');
  const editInput = document.getElementById('editNameInput');
  if (document.activeElement !== editInput) {
    editInput.value = state.studentName;
  }
}

function startWaitingPoll() {
  clearInterval(state.waitingPoll);
  if (!state.waitingJoinedAt) state.waitingJoinedAt = Date.now();
  let dots = 0;
  state.waitingPoll = setInterval(() => {
    dots = (dots + 1) % 4;
    document.getElementById('waitingDots').textContent = '.'.repeat(dots);
    renderWaitingGrid();
    const room = HTD.getRoom();
    const gameStarted =
      room?.status === 'started' &&
      room.startedAt &&
      room.startedAt > state.waitingJoinedAt;
    if (gameStarted) {
      clearInterval(state.waitingPoll);
      state.questionIndex = 0;
      state.myScore = 0;
      state.lastGameQuestionIndex = -1;
      buildStudentsFromStorage();
      const game = room.game;
      if (game) state.questionIndex = game.questionIndex;
      showScreen('question');
    }
  }, 1000);
}

function buildStudentsFromStorage() {
  initDisplayPlayers();
  state.students = state.displayPlayers.map(p => ({
    id: p.id,
    name: p.name,
    score: p.id === state.playerId ? state.myScore : (state.fakeScores[p.id] || 0),
    avatarDataUrl: p.avatarDataUrl,
    avatarEmoji: p.avatarEmoji,
    isMe: p.id === state.playerId,
    isFake: !!p.isFake,
  }));
  state.students.sort((a, b) => b.score - a.score);
}

// ─── Chem / Coef keyboards ───
const CHEM_KB_ROWS = [
  ['H', 'O', 'C', 'N', 'Na', 'Cl'],
  ['K', 'Ca', 'Mg', 'Al', 'Fe', 'Cu'],
  ['Zn', 'Ag', 'Ba', 'P', 'S', 'del'],
  ['1', '2', '3', '4', '5', '6'],
  ['7', '8', '9', '0', '(', ')'],
];

function buildChemKeyboard() {
  const kb = document.getElementById('chemKb');
  if (kb.dataset.built) return;
  kb.dataset.built = '1';
  kb.innerHTML = CHEM_KB_ROWS.map(row =>
    `<div class="chem-kb-row">${row.map(k => {
      if (k === 'del') return '<button type="button" class="chem-key del" data-action="back">⌫</button>';
      const w2 = k.length > 1 ? ' w2' : '';
      return `<button type="button" class="chem-key${w2}" data-val="${k}">${k}</button>`;
    }).join('')}</div>`
  ).join('');
  kb.querySelectorAll('.chem-key').forEach(key => {
    key.onclick = () => onChemKey(key.dataset.action === 'back' ? '⌫' : key.dataset.val);
  });
}

function buildCoefNumpad() {
  const pad = document.getElementById('coefNumpad');
  if (pad.dataset.built) return;
  pad.dataset.built = '1';
  const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'];
  pad.innerHTML = keys.map(k => {
    if (k === '') return '<div></div>';
    const cls = k === '⌫' ? 'coef-key del' : 'coef-key';
    return `<button type="button" class="${cls}" data-key="${k}">${k}</button>`;
  }).join('');
  pad.querySelectorAll('.coef-key').forEach(btn => {
    btn.onclick = () => onCoefKey(btn.dataset.key);
  });
}

function getFocusedSlotType() {
  const q = FAKE_QUESTIONS[state.questionIndex];
  if (!state.focusedSlotId || !q?.template) return null;
  const part = q.template.find(
    p => (p.t === 'coef' || p.t === 'blank') && p.id === state.focusedSlotId
  );
  return part?.t || null;
}

function onChemKey(val) {
  if (!state.eqController) return;
  const slotType = getFocusedSlotType();
  if (val === '⌫') {
    state.focusedSlotId = state.eqController.backspace(state.focusedSlotId);
  } else if (/^\d$/.test(val) && slotType === 'coef' && state.eqController.inputDigit) {
    state.focusedSlotId = state.eqController.inputDigit(val, state.focusedSlotId);
  } else if (state.eqController.append) {
    state.focusedSlotId = state.eqController.append(val, state.focusedSlotId);
  }
  state.eqController.render(state.focusedSlotId);
}

function onCoefKey(key) {
  if (!state.eqController?.inputDigit) return;
  const slotType = getFocusedSlotType();
  if (slotType === 'blank') return;
  if (key === '⌫') {
    state.focusedSlotId = state.eqController.backspace(state.focusedSlotId);
  } else {
    state.focusedSlotId = state.eqController.inputDigit(key, state.focusedSlotId);
  }
  state.eqController.render(state.focusedSlotId);
}

function toggleKeyboard(forceOpen) {
  const kb = document.getElementById('qKeyboard');
  const fab = document.getElementById('kbOpenFab');
  const toggleBtn = document.getElementById('kbToggleBtn');
  const open = forceOpen === true ? true : forceOpen === false ? false : !state.keyboardOpen;
  state.keyboardOpen = open;
  kb.classList.toggle('open', open);
  kb.classList.toggle('collapsed', !open);
  fab.hidden = open;
  toggleBtn.hidden = !open;
}

// ─── Timer (đồng bộ với giáo viên qua localStorage) ───
function formatTimer(sec) {
  return HTD.formatTimer(sec);
}

function updateSyncedTimerUI() {
  const room = HTD.getRoom();
  const game = room?.game;
  const text = document.getElementById('qTimerText');
  const pill = document.getElementById('qTimerPill');
  if (!text || !pill || !game || game.phase !== 'question') return;

  const sec = HTD.getQuestionTimeRemaining(game);
  text.textContent = formatTimer(sec);
  pill.classList.remove('warn', 'danger', 'paused');
  if (game.paused) pill.classList.add('paused');
  else if (sec <= 3) pill.classList.add('danger');
  else if (sec <= 5) pill.classList.add('warn');
  return sec;
}

function startSyncedTimer(onEnd) {
  clearInterval(state.timerInterval);
  updateSyncedTimerUI();
  state.timerInterval = setInterval(() => {
    const sec = updateSyncedTimerUI();
    if (sec !== undefined && sec <= 0 && !HTD.getRoom()?.game?.paused) {
      clearInterval(state.timerInterval);
      onEnd();
    }
  }, 200);
}

function syncGamePoll() {
  clearInterval(state.gamePoll);
  const room = HTD.getRoom();
  if (!room || room.status !== 'started' || !room.game) return;

  state.gamePoll = setInterval(syncGameState, 400);
}

function syncGameState() {
  const room = HTD.getRoom();
  const game = room?.game;
  if (!room || room.status !== 'started' || !game) {
    clearInterval(state.gamePoll);
    return;
  }

  if (game.phase === 'question') {
    if (game.questionIndex !== state.lastGameQuestionIndex) {
      state.lastGameQuestionIndex = game.questionIndex;
      if (game.questionIndex !== state.questionIndex) {
        state.questionIndex = game.questionIndex;
        state.submitted = false;
        showScreen('question');
        return;
      }
    }
    if (state.screen === 'question') {
      updateSyncedTimerUI();
      const sec = HTD.getQuestionTimeRemaining(game);
      if (sec <= 0 && !state.submitted && !game.paused) {
        autoEndQuestion();
      }
    } else if (state.screen === 'leaderboard') {
      state.questionIndex = game.questionIndex;
      state.submitted = false;
      showScreen('question');
    }
  } else if (game.phase === 'leaderboard') {
    if (state.screen === 'question' && !state.submitted) {
      autoEndQuestion();
    } else if (state.screen === 'leaderboard') {
      updateLbCountdownFromGame();
    }
  } else if (game.phase === 'final') {
    if (state.screen !== 'final') {
      clearInterval(state.lbInterval);
      showScreen('final');
    }
  }

  if (room.status === 'waiting' && !['waiting', 'welcome', 'join', 'profile'].includes(state.screen)) {
    clearInterval(state.gamePoll);
    state.questionIndex = 0;
    state.myScore = 0;
    state.fakeScores = {};
    state.lastGameQuestionIndex = -1;
    state.waitingJoinedAt = Date.now();
    showScreen('waiting');
  }
}

function autoEndQuestion() {
  const q = FAKE_QUESTIONS[state.questionIndex];
  if (!q) return;
  if (q.type === 'mc') finishMC(-1);
  else submitAnswer(true);
}

function updateLbCountdownFromGame() {
  const game = HTD.getRoom()?.game;
  const el = document.getElementById('lbCountdown');
  if (!el || !game || game.phase !== 'leaderboard') return;
  const sec = HTD.getPhaseTimeRemaining(game);
  el.textContent = sec > 0 ? `Câu tiếp sau ${sec}s...` : '...';
}

// ─── Question (unified) ───
function startQuestion() {
  const q = FAKE_QUESTIONS[state.questionIndex];
  if (!q) return;

  state.submitted = false;
  state.selectedAnswer = null;
  const screen = document.querySelector('.question-screen');
  screen.classList.toggle('mode-input', q.type === 'input');
  screen.classList.toggle('mode-mc', q.type === 'mc');

  document.getElementById('qProgress').textContent =
    `Câu ${state.questionIndex + 1}/${FAKE_QUESTIONS.length}`;
  document.getElementById('qId').textContent = `ID: ${q.id || '—'}`;

  const badge = document.getElementById('qTypeBadge');
  if (q.type === 'mc') {
    badge.textContent = 'Dạng: Trắc nghiệm';
  } else {
    const sub = HTD.INPUT_MODE_LABELS[q.inputMode] || 'Tự luận';
    badge.textContent = `Dạng: Tự luận — ${sub}`;
  }

  document.getElementById('qPrompt').textContent = q.prompt || '';
  renderQuestionMedia(q);

  const answerEl = document.getElementById('qAnswer');
  const inputZone = document.getElementById('qInputZone');
  const eqEl = document.getElementById('qEqDisplay');
  answerEl.innerHTML = '';
  eqEl.hidden = true;
  inputZone.hidden = q.type !== 'input';

  if (q.type === 'mc') {
    renderMcOptions(q, answerEl);
    toggleKeyboard(false);
    document.getElementById('kbOpenFab').hidden = true;
  } else {
    eqEl.hidden = false;
    setupInputQuestion(q);
    toggleKeyboard(true);
  }

  document.getElementById('qStarBtn').classList.remove('active');
  state.lastGameQuestionIndex = HTD.getRoom()?.game?.questionIndex ?? state.questionIndex;
  startSyncedTimer(() => {
    if (q.type === 'mc') finishMC(-1);
    else submitAnswer(true);
  });
  syncGamePoll();
}

function renderQuestionMedia(q) {
  const img = document.getElementById('qImage');
  const vidWrap = document.getElementById('qVideoWrap');
  const vid = document.getElementById('qVideo');
  img.hidden = true;
  vidWrap.hidden = true;
  vid.pause();
  vid.removeAttribute('src');

  if (q.media === 'image' && q.mediaUrl) {
    img.src = q.mediaUrl;
    img.hidden = false;
  } else if (q.media === 'video' && q.mediaUrl) {
    vid.src = q.mediaUrl;
    if (q.mediaPoster) vid.poster = q.mediaPoster;
    vidWrap.hidden = false;
  }
}

function renderMcOptions(q, container) {
  const wrap = document.createElement('div');
  wrap.className = 'mc-options';
  ['A', 'B', 'C', 'D'].forEach((label, i) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mc-option';
    const optText = q.options[i] || '';
    btn.innerHTML =
      `<span class="mc-option-label">${label}.</span>` +
      `<span class="mc-option-text">${EquationUI.chemToHtml(optText)}</span>` +
      `<span class="mc-check" aria-hidden="true">✓</span>`;
    btn.onclick = () => {
      if (state.submitted) return;
      wrap.querySelectorAll('.mc-option').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      state.selectedAnswer = i;
      const submitBtn = document.getElementById('mcSubmitBtn');
      if (submitBtn) submitBtn.disabled = false;
    };
    wrap.appendChild(btn);
  });
  container.appendChild(wrap);

  const submitRow = document.createElement('div');
  submitRow.className = 'mc-submit-row';
  submitRow.innerHTML =
    '<button type="button" class="btn-mc-submit" id="mcSubmitBtn" disabled onclick="submitMcAnswer()">Gửi đáp án</button>';
  container.appendChild(submitRow);
}

function submitMcAnswer() {
  if (state.submitted || state.selectedAnswer === null) return;
  clearInterval(state.timerInterval);
  finishMC(state.selectedAnswer);
}

function setupInputQuestion(q) {
  buildChemKeyboard();
  buildCoefNumpad();
  state.inputValues = EquationUI.createInputState(q.template);
  state.focusedSlotId = null;

  const eqEl = document.getElementById('qEqDisplay');
  const chemKb = document.getElementById('chemKb');
  const coefPad = document.getElementById('coefNumpad');
  const showCoef = q.inputMode === 'balance' || q.inputMode === 'blank_balance';
  const showChem = q.inputMode !== 'balance';

  chemKb.style.display = showChem ? '' : 'none';
  coefPad.style.display = showCoef ? '' : 'none';

  const onFocus = id => { state.focusedSlotId = id; };
  const onChange = () => {};

  const ctrlOpts = { container: eqEl, template: q.template, values: state.inputValues, onFocus, onChange };

  if (q.inputMode === 'balance') {
    state.eqController = EquationUI.CoefController(ctrlOpts);
  } else if (q.inputMode === 'blank_balance') {
    state.eqController = EquationUI.MixedController(ctrlOpts);
  } else {
    state.eqController = EquationUI.BlankController(ctrlOpts);
  }

  state.eqController.render(null);
  const firstSlot = q.template.find(p => p.t === 'coef' || p.t === 'blank');
  if (firstSlot) state.focusedSlotId = firstSlot.id;
  state.eqController.render(state.focusedSlotId);
}

function clearInputAnswer() {
  if (!state.eqController) return;
  state.focusedSlotId = state.eqController.clearAll();
  state.eqController.render(state.focusedSlotId);
}

function submitAnswer(timedOut) {
  if (state.submitted) return;
  state.submitted = true;
  clearInterval(state.timerInterval);
  const q = FAKE_QUESTIONS[state.questionIndex];
  const game = HTD.getRoom()?.game;
  const remaining = game ? HTD.getQuestionTimeRemaining(game) : state.timer;
  showScreen('submit');
  setTimeout(() => {
    const ok = EquationUI.checkAnswer(q, state.inputValues);
    const pts = ok ? Math.round(1000 * (remaining / q.timeLimit)) : 0;
    if (ok) state.myScore += pts;
    const ans = EquationUI.formatCorrectAnswer(q);
    showResult(ok, pts, ans);
  }, timedOut ? 0 : 1200);
}

function finishMC(ans) {
  if (state.submitted) return;
  state.submitted = true;
  clearInterval(state.timerInterval);
  const q = FAKE_QUESTIONS[state.questionIndex];
  const game = HTD.getRoom()?.game;
  const remaining = game ? HTD.getQuestionTimeRemaining(game) : state.timer;
  const ok = ans === q.correct;
  const pts = ok ? Math.round(1000 * (remaining / q.timeLimit)) : 0;
  if (ok) state.myScore += pts;
  const correctText = q.options[q.correct] || '—';
  showResult(ok, pts, correctText);
}

// ─── Result / Leaderboard / Final ───
function showResult(ok, pts, ans) {
  const ov = document.getElementById('resultOverlay');
  document.getElementById('resultCircle').className = 'result-circle ' + (ok ? 'correct' : 'wrong');
  document.getElementById('resultCircle').textContent = ok ? '✓' : '✗';
  document.getElementById('resultTitle').textContent = ok ? 'Đúng rồi!' : 'Chưa đúng!';
  document.getElementById('resultPoints').textContent = ok ? `+${pts} điểm` : '+0 điểm';
  document.getElementById('resultAnswer').textContent = ok ? '' : `Đáp án: ${ans}`;
  ov.classList.add('show');
  if (ok && typeof confetti === 'function') {
    confetti({ particleCount: 60, spread: 55, origin: { y: 0.6 } });
  }
  updateScores();
  setTimeout(() => {
    ov.classList.remove('show');
    showScreen('leaderboard');
  }, 2200);
}

function updateScores() {
  state.prevRanks = {};
  state.students.forEach((s, i) => {
    state.prevRanks[s.id] = i;
  });
  state.students.forEach(s => {
    if (s.isMe) {
      s.score = state.myScore;
    } else {
      const delta = Math.floor(Math.random() * 200) + 50;
      s.score = (s.score || 0) + delta;
      if (s.isFake) state.fakeScores[s.id] = s.score;
    }
  });
  state.students.sort((a, b) => b.score - a.score);
}

function renderLbItem(s, rank) {
  const prev = state.prevRanks[s.id];
  const myIdx = state.students.findIndex(x => x.id === s.id);
  const delta =
    prev !== undefined && prev !== myIdx
      ? `<span class="lb-delta ${prev > myIdx ? 'up' : 'down'}">${prev > myIdx ? '↑' : '↓'}</span>`
      : '';
  const av = s.avatarDataUrl
    ? `<img src="${s.avatarDataUrl}">`
    : s.avatarEmoji || '😀';
  return `<div class="lb-item${s.isMe ? ' me' : ''}" data-id="${s.id}">
    <div class="lb-rank r${Math.min(rank, 4)}">${rank}</div>
    <div class="lb-avatar">${av}</div>
    <span class="lb-name">${s.name}</span>
    <span class="lb-score">${s.score} ${delta}</span>
  </div>`;
}

function renderLeaderboard() {
  const topList = document.getElementById('lbList');
  const moreList = document.getElementById('lbMore');
  const pinned = document.getElementById('lbPinned');
  const oldTops = new Map();
  document.querySelectorAll('.lb-item').forEach(el => {
    oldTops.set(el.dataset.id, el.getBoundingClientRect().top);
  });

  const sorted = state.students;
  const myIdx = sorted.findIndex(s => s.isMe);
  const meInTop10 = myIdx >= 0 && myIdx < 10;

  topList.innerHTML = sorted.slice(0, 10).map((s, i) => renderLbItem(s, i + 1)).join('');

  const rest = sorted.slice(10).filter(s => !(s.isMe && !meInTop10));
  moreList.innerHTML = rest.length
    ? '<p class="lb-more-label">Kéo xuống xem thêm thứ hạng</p>' +
      rest.map(s => renderLbItem(s, sorted.findIndex(x => x.id === s.id) + 1)).join('')
    : '';

  if (!meInTop10 && myIdx >= 0) {
    pinned.hidden = false;
    pinned.innerHTML =
      `<p class="lb-pinned-label">Vị trí của bạn — hạng ${myIdx + 1}</p>` +
      renderLbItem(sorted[myIdx], myIdx + 1);
  } else {
    pinned.hidden = true;
    pinned.innerHTML = '';
  }

  requestAnimationFrame(() => {
    document.querySelectorAll('.lb-item').forEach(el => {
      const old = oldTops.get(el.dataset.id);
      if (old !== undefined) {
        const diff = old - el.getBoundingClientRect().top;
        if (Math.abs(diff) > 2) {
          el.style.transform = `translateY(${diff}px)`;
          el.style.transition = 'none';
          requestAnimationFrame(() => {
            el.style.transition = 'transform 0.5s ease';
            el.style.transform = '';
          });
        }
      }
    });
  });
  clearInterval(state.lbInterval);
  updateLbCountdownFromGame();
  state.lbInterval = setInterval(() => {
    updateLbCountdownFromGame();
    const game = HTD.getRoom()?.game;
    if (game?.phase === 'question' && game.questionIndex > state.questionIndex) {
      clearInterval(state.lbInterval);
      state.questionIndex = game.questionIndex;
      state.submitted = false;
      showScreen('question');
    } else if (game?.phase === 'final') {
      clearInterval(state.lbInterval);
      showScreen('final');
    }
  }, 400);
}

function nextQuestion() {
  state.questionIndex++;
  if (state.questionIndex >= FAKE_QUESTIONS.length) {
    showScreen('final');
    return;
  }
  showScreen('question');
}

function renderFinal() {
  const top = state.students.slice(0, 3);
  const order = [top[1], top[0], top[2]].filter(Boolean);
  document.getElementById('podium').innerHTML = order
    .map((s, i) => {
      const cls = ['p2', 'p1', 'p3'][i];
      return `<div class="podium-item ${cls}"><div class="podium-bar">${
        s.avatarDataUrl
          ? `<img src="${s.avatarDataUrl}" style="width:40px;height:40px;border-radius:50%;object-fit:cover">`
          : s.avatarEmoji || '😀'
      }</div>
      <div class="podium-name">${s.name}</div><div class="podium-score">${s.score}đ</div></div>`;
    })
    .join('');
}

function restartGame() {
  const room = HTD.getRoom();
  if (room) {
    HTD.resetGame(room);
    HTD.setRoom(room);
  }
  clearInterval(state.gamePoll);
  clearInterval(state.timerInterval);
  clearInterval(state.lbInterval);
  state.questionIndex = 0;
  state.myScore = 0;
  state.fakeScores = {};
  state.lastGameQuestionIndex = -1;
  state.waitingJoinedAt = Date.now();
  initDisplayPlayers();
  showScreen('waiting');
}

// ─── Demo nav ───
const SCREENS = [
  'welcome',
  'join',
  'profile',
  'waiting',
  'question',
  'leaderboard',
  'final',
];

document.getElementById('demoNav').innerHTML =
  SCREENS.map(s => `<button onclick="demoGo('${s}')">${s}</button>`).join('') +
  FAKE_QUESTIONS.map((q, i) =>
    `<button onclick="demoQuestion(${i})" title="${q.id}">q${i + 1}</button>`
  ).join('');

function demoQuestion(i) {
  buildStudentsFromStorage();
  state.questionIndex = i;
  state.submitted = false;
  startQuestion();
  showScreen('question');
}

function demoGo(s) {
  if (s === 'profile') {
    if (!HTD.getRoom()) {
      alert('Tạo phòng tại teacher.html trước.');
      return;
    }
    state.pinDigits = HTD.getRoom().pin;
  }
  if (s === 'waiting') {
    if (!state.playerId) {
      state.playerId = HTD.genId();
      state.studentName = 'Demo HS';
      saveMyPlayer();
    }
    state.waitingJoinedAt = Date.now();
    initDisplayPlayers();
    renderWaitingGrid();
    startWaitingPoll();
  }
  if (s === 'question') {
    buildStudentsFromStorage();
    if (state.questionIndex === undefined || state.questionIndex < 0) state.questionIndex = 0;
    startQuestion();
  }
  if (s === 'leaderboard') {
    buildStudentsFromStorage();
    renderLeaderboard();
  }
  if (s === 'final') {
    buildStudentsFromStorage();
    renderFinal();
  }
  showScreen(s);
}

buildChemKeyboard();
buildCoefNumpad();
buildNumpad();
renderPinDisplay();

document.getElementById('qStarBtn')?.addEventListener('click', function () {
  this.classList.toggle('active');
  this.textContent = this.classList.contains('active') ? '★' : '☆';
});
