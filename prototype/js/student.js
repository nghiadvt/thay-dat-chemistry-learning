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
  lockedIn: false,
  lockedElapsedSeconds: 0,
  lockedAnswerDisplay: '',
  awaitingResult: false,
  pendingLeaderboard: null,
  pendingNewQuestion: null,
  resultPhaseTimers: [],
  lateJoinSync: false,
  students: [],
  displayPlayers: [],
  fakeScores: {},
  prevRanks: {},
  cameraStream: null,
  waitingPoll: null,
  waitingJoinedAt: 0,
  gamePoll: null,
  lastGameQuestionIndex: -1,
  backendMode: false,
  currentQuestion: null,
  serverQuestionIndex: 0,
  backendRoom: null,
  qrScanner: null,
  playMode: null,
  submitting: false,
  streak: 0,
  lastTickSec: null,
  lastDuckScore: null,
  duckSprite: null,
  duckSprites: [],
  lastWaitingCount: 0,
  finalTimers: [],
  /** PIN của phòng đang tham gia (backend) — dùng để tự vào lại khi mất mạng */
  joinedPin: null,
  questionTotal: null,
  reconnectSetup: false,
  wasDisconnected: false,
};

/* ── Game-feel helpers (âm thanh + hiệu ứng, an toàn khi thiếu engine) ── */
function sfx(name) {
  if (window.HTDSound) HTDSound.play(name);
}

function replayAnim(el, cls) {
  if (!el) return;
  el.classList.remove(cls);
  void el.offsetWidth; // restart animation
  el.classList.add(cls);
}

let cartoonToastTimer = null;
function showCartoonToast(text, emoji) {
  const toast = document.getElementById('cartoonToast');
  const textEl = document.getElementById('cartoonToastText');
  const emojiEl = document.getElementById('cartoonToastEmoji');
  if (!toast || !textEl) {
    alert(text);
    return;
  }
  textEl.textContent = text;
  if (emojiEl) emojiEl.textContent = emoji || '🚀';
  toast.hidden = false;
  replayAnim(toast, 'toast-boing');
  sfx('pop');
  clearTimeout(cartoonToastTimer);
  cartoonToastTimer = setTimeout(() => { toast.hidden = true; }, 2400);
}

function updateStreakBadge() {
  const badge = document.getElementById('streakBadge');
  const count = document.getElementById('streakCount');
  if (!badge || !count) return;
  if (state.streak >= 2) {
    count.textContent = String(state.streak);
    badge.hidden = false;
    replayAnim(badge, 'pill-bounce');
  } else {
    badge.hidden = true;
  }
}

/* tick/urgent khi còn ≤5s — gọi từ cả timer demo lẫn timer backend */
function timerTickFx(sec) {
  if (sec == null || sec === state.lastTickSec) return;
  state.lastTickSec = sec;
  if (sec <= 0 || state.awaitingResult) return;
  if (sec <= 3) sfx('urgent');
  else if (sec <= 5) sfx('tick');
}

const FAKE_QUESTIONS = HTD.FAKE_QUESTIONS;

function isBackendMode() {
  return Boolean(window.HTD_CONFIG?.useBackend);
}

function isDuckRaceMode() {
  return state.playMode === 'duck_race';
}

function updateDuckScoreDisplay(score) {
  const pill = document.getElementById('qDuckScorePill');
  const text = document.getElementById('qDuckScoreText');
  const val = score ?? state.myScore ?? 0;
  if (text) text.textContent = String(val);
  if (pill) {
    pill.hidden = !isDuckRaceMode();
    if (!pill.hidden && state.lastDuckScore !== null && val !== state.lastDuckScore) {
      replayAnim(pill, 'pill-bounce');
    }
  }
  state.lastDuckScore = val;
}

function showDuckRaceFeedback(data) {
  const el = document.getElementById('duckRaceFeedback');
  const deltaEl = document.getElementById('duckRaceFeedbackDelta');
  const totalEl = document.getElementById('duckRaceFeedbackTotal');
  const finishEl = document.getElementById('duckRaceFeedbackFinish');
  if (!el || !deltaEl || !totalEl) return;

  const correct = Boolean(data.correct);
  const delta = Number(data.score_delta ?? 0);
  deltaEl.textContent = delta > 0 ? `+${delta}` : String(delta);
  totalEl.textContent = `Tổng: ${data.total_score ?? state.myScore}`;
  el.classList.remove('correct', 'wrong', 'visible');
  el.classList.add(correct ? 'correct' : 'wrong');
  if (finishEl) {
    if (data.finish_rank) {
      finishEl.hidden = false;
      const time = data.finish_elapsed_s != null
        ? ` · ${Number(data.finish_elapsed_s).toFixed(4)}s`
        : '';
      finishEl.textContent = `🏁 Về đích #${data.finish_rank}${time}!`;
    } else {
      finishEl.hidden = true;
      finishEl.textContent = '';
    }
  }
  requestAnimationFrame(() => el.classList.add('visible'));
  // Âm thanh + hiệu ứng game
  if (data.finish_rank) {
    sfx('fanfare');
    if (window.HTDFx) HTDFx.sparkleRain({ count: 36 });
  } else if (correct) {
    sfx('correct');
    if (window.HTDFx) HTDFx.burstAtElement(el, { count: 14 });
  } else {
    sfx('wrong');
    if (window.HTDFx) HTDFx.shake();
  }
  clearTimeout(state.duckFeedbackTimer);
  state.duckFeedbackTimer = setTimeout(() => {
    el.classList.remove('visible');
  }, 900);
}

function handleDuckRaceSubmitResult(data) {
  state.awaitingResult = false;
  state.lockedIn = false;
  state.selectedAnswer = null;
  state.submitted = false;
  if (data?.total_score != null) state.myScore = Number(data.total_score);
  updateDuckScoreDisplay();
  showDuckRaceFeedback(data);
  const q = getCurrentQuestion();
  if (q?.type === 'mc') {
    document.querySelectorAll('.mc-option').forEach(b => b.classList.remove('selected'));
    const submitBtn = document.getElementById('mcSubmitBtn');
    if (submitBtn) submitBtn.disabled = true;
  }
}

function getCurrentQuestion() {
  if (isBackendMode() && state.currentQuestion) return state.currentQuestion;
  return FAKE_QUESTIONS[state.questionIndex];
}

function showScreen(id) {
  stopCamera();
  stopQrScanner();
  if (state.screen === 'question') {
    document.getElementById('qVideo')?.pause();
  }
  HTD.showScreen(id);
  const changed = state.screen && state.screen !== id;
  state.screen = id;

  // Hiệu ứng chuyển màn: pop-in + whoosh
  if (changed) sfx('whoosh');
  const sectionEl = document.querySelector(`.screen[data-screen="${id}"]`);
  if (sectionEl) {
    replayAnim(sectionEl, 'screen-pop-in');
    sectionEl.addEventListener('animationend', () => sectionEl.classList.remove('screen-pop-in'), { once: true });
  }

  // Nhạc nền chỉ chạy trong phòng chờ
  if (window.HTDSound) {
    if (id === 'waiting') HTDSound.startMusic();
    else HTDSound.stopMusic();
  }

  if (id === 'join') initJoin();
  if (id === 'profile') initProfile();
  if (id === 'account' && window.StudentAccount) StudentAccount.render();
  if (id === 'duck-pick') renderDuckPicker();
  if (id === 'waiting') {
    renderWaitingGrid();
    startWaitingPoll();
  } else {
    clearInterval(state.waitingPoll);
  }
  if (id === 'question') startQuestion();
  if (id === 'leaderboard') renderLeaderboard();
  if (id === 'final') renderFinal();
  if (id === 'elements' && window.ElementsModule) ElementsModule.enter();
  syncGamePoll();
}

// ─── Home hub ───
const HOME_FEATURE_LABELS = {
  elements: 'Đọc nguyên tố',
  balance: 'Cân bằng phương trình',
  quiz: 'Ôn trắc nghiệm',
};

function goStudentHome() {
  stopQrScanner();
  if (window.ElementsModule) ElementsModule.exitLandscape();
  window.location.href = '/';
}

function openHomeFeature(feature) {
  sfx('tap');
  // Tính năng bị giáo viên khóa hẳn: hiện thông báo, không mở.
  if (window.StudentEntitlements && !StudentEntitlements.guard(feature)) return;
  if (feature === 'play') {
    showScreen('game-select');
    return;
  }
  if (feature === 'elements' && window.ElementsModule) {
    showScreen('elements');
    return;
  }
  if (feature === 'quiz' && window.QuizModule) {
    QuizModule.open();
    return;
  }
  if (feature === 'balance' && window.BalanceModule) {
    BalanceModule.open();
    return;
  }
  const label = HOME_FEATURE_LABELS[feature] || 'Tính năng';
  showCartoonToast(`${label} — sắp ra mắt!`, '🧪');
}

// ─── Game select (trước khi vào /join) ───
function selectGame(game) {
  sfx('tap');
  if (game === 'duck_race') {
    window.location.href = '/join';
    return;
  }
  showCartoonToast('Săn Rồng Hóa Học — sắp ra mắt!', '🐉');
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
  if (tab !== 'qr') stopQrScanner();
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
  // ô vừa nhập nảy nhẹ
  const dots = el.querySelectorAll('.pin-dot.filled');
  if (dots.length) replayAnim(dots[dots.length - 1], 'pin-pop');
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
      sfx('tap');
      const key = btn.dataset.key;
      if (key === '⌫') state.pinDigits = state.pinDigits.slice(0, -1);
      else if (state.pinDigits.length < 6) state.pinDigits += key;
      renderPinDisplay();
    };
  });
}

function validatePinAndContinue(opts = {}) {
  const fromDeepLink = Boolean(opts.fromDeepLink);

  function onPinInvalid(message) {
    alert(message);
    state.pinDigits = '';
    if (fromDeepLink || state.screen !== 'join') {
      showScreen('join');
    } else {
      renderPinDisplay();
    }
  }

  if (isBackendMode()) {
    HTDApi.checkPin(state.pinDigits)
      .then(data => {
        state.backendRoom = {
          pin: state.pinDigits,
          name: data.game_name || 'PHÒNG QUIZ',
          teacher: 'Giáo viên',
          status: data.status || 'waiting',
          backendMode: true,
        };
        HTD.setRoom(state.backendRoom);
        showScreen('profile');
      })
      .catch(err => {
        onPinInvalid(err.message || 'PIN không hợp lệ.');
      });
    return;
  }
  const room = HTD.getRoom();
  if (!room || state.pinDigits !== room.pin) {
    onPinInvalid(room ? 'PIN không đúng!' : 'Chưa có phòng. Giáo viên cần tạo phòng trước.');
    return;
  }
  showScreen('profile');
}

function parsePinFromQrText(text) {
  const raw = String(text || '').trim();
  const joinMatch = raw.match(/\/join\/(\d{6})\b/i);
  if (joinMatch) return joinMatch[1];
  const pinParam = raw.match(/[?&]pin=(\d{6})\b/i);
  if (pinParam) return pinParam[1];
  const digitsOnly = raw.replace(/\D/g, '');
  if (/^\d{6}$/.test(digitsOnly)) return digitsOnly;
  const embedded = digitsOnly.match(/(\d{6})/);
  return embedded ? embedded[1] : null;
}

function resetQrScannerUi() {
  const idle = document.getElementById('qrScannerIdle');
  const reader = document.getElementById('qrReader');
  const cancel = document.getElementById('btnQrCancel');
  if (idle) idle.style.display = '';
  if (reader) {
    reader.hidden = true;
    reader.innerHTML = '';
  }
  if (cancel) cancel.hidden = true;
}

async function stopQrScanner() {
  const scanner = state.qrScanner;
  state.qrScanner = null;
  if (!scanner) {
    resetQrScannerUi();
    return;
  }
  try {
    await scanner.stop();
  } catch (_) {}
  try {
    scanner.clear();
  } catch (_) {}
  resetQrScannerUi();
}

async function startQrScanner() {
  if (!window.Html5Qrcode) {
    alert('Không tải được thư viện quét QR. Thử nhập PIN thủ công.');
    return;
  }
  // HTTP LAN: trình duyệt chặn getUserMedia — dùng camera native qua <input capture> (giống avatar).
  if (!canUseLiveCamera()) {
    openQrFileCapture();
    return;
  }
  if (state.qrScanner) return;

  document.getElementById('qrScannerIdle').style.display = 'none';
  const readerEl = document.getElementById('qrReader');
  readerEl.hidden = false;
  document.getElementById('btnQrCancel').hidden = false;

  const scanner = new Html5Qrcode('qrReader');
  state.qrScanner = scanner;
  try {
    await scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 },
      decodedText => {
        handleQrDecoded(decodedText);
      },
      () => {}
    );
  } catch (_) {
    await stopQrScanner();
    openQrFileCapture();
  }
}

function openQrFileCapture() {
  const input = document.getElementById('qrFileInput');
  if (!input) {
    alert('Không mở được camera. Nhập PIN thủ công.');
    return;
  }
  input.value = '';
  input.click();
}

function handleQrDecoded(decodedText) {
  const pin = parsePinFromQrText(decodedText);
  if (!pin) {
    alert('Mã QR không hợp lệ. Quét mã phòng do giáo viên hiển thị.');
    return;
  }
  stopQrScanner().then(() => {
    state.pinDigits = pin;
    validatePinAndContinue();
  });
}

async function onQrCaptureSelected(ev) {
  const file = ev.target?.files?.[0];
  if (!file || !window.Html5Qrcode) return;
  try {
    const scanner = new Html5Qrcode('qrReader');
    const decoded = await scanner.scanFile(file, false);
    handleQrDecoded(decoded);
  } catch (_) {
    alert('Không đọc được mã QR. Chụp lại cho rõ hoặc nhập PIN thủ công.');
  }
}

(function checkUrlPin() {
  const params = new URLSearchParams(location.search);
  let p = params.get('pin') || window.HTD_JOIN_PIN || null;
  if (!p) {
    const m = location.pathname.match(/\/join\/(\d{6})\/?$/);
    if (m) p = m[1];
  }
  if (!p) return;
  const pin = String(p).replace(/\D/g, '').slice(0, 6);
  if (pin.length !== 6) return;
  // QR / deep-link: skip Join PIN → validate → name/avatar (profile).
  state.pinDigits = pin;
  setTimeout(() => validatePinAndContinue({ fromDeepLink: true }), 100);
})();

(function initEntryScreen() {
  if (window.HTD_JOIN_PIN) {
    document.querySelector('[data-screen="home"]')?.classList.remove('active');
    return;
  }
  const entry = window.HTD_ENTRY_SCREEN || 'home';
  if (entry === 'join') {
    showScreen('join');
  } else {
    showScreen('home');
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

function pickAvatarFile() {
  const input = document.getElementById('avatarFileInput');
  if (!input) {
    alert('Không chọn được ảnh.');
    return;
  }
  input.value = '';
  // Must run synchronously in user-gesture handlers (async getUserMedia catch blocks get blocked).
  input.click();
}

function onAvatarFileSelected(ev) {
  const file = ev.target?.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = () => {
    const img = new Image();
    img.onload = () => {
      const size = 240;
      const c = document.createElement('canvas');
      c.width = size;
      c.height = size;
      const ctx = c.getContext('2d');
      const scale = Math.max(size / img.width, size / img.height);
      const w = img.width * scale;
      const h = img.height * scale;
      ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);
      state.avatarDataUrl = c.toDataURL('image/jpeg', 0.8);
      updateProfileAvatarUI();
      if (state.screen === 'waiting') {
        saveMyPlayer();
        renderWaitingGrid();
      }
    };
    img.src = reader.result;
  };
  reader.readAsDataURL(file);
}

function canUseLiveCamera() {
  return (
    window.isSecureContext === true &&
    typeof navigator !== 'undefined' &&
    !!navigator.mediaDevices &&
    typeof navigator.mediaDevices.getUserMedia === 'function'
  );
}

/**
 * Avatar photo: on HTTP LAN (typical phone Wi‑Fi), getUserMedia is blocked.
 * Use <input capture> first — opens the native camera / gallery and works without HTTPS.
 * Live in-page preview only when secure context (HTTPS / localhost).
 */
function onAvatarClick(fromWaiting) {
  if (!canUseLiveCamera()) {
    pickAvatarFile();
    return;
  }
  openLiveCamera(Boolean(fromWaiting)).catch(() => {
    // Secure context but permission denied — fall back next tick (may need 2nd tap on some browsers).
    pickAvatarFile();
  });
}

async function openLiveCamera(fromWaiting) {
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
    video.setAttribute('playsinline', '');
    video.muted = true;
    video.style.cssText =
      'position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:50;background:#000';
    document.body.appendChild(video);
    const bar = document.createElement('div');
    bar.id = 'waitingCaptureBar';
    bar.style.cssText =
      'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:51;width:90%;max-width:320px';
    bar.innerHTML =
      '<button class="btn-primary" type="button" onclick="captureWaitingPhoto()">Chụp</button>' +
      '<button class="btn-secondary" type="button" onclick="cancelWaitingCapture()">Huỷ</button>';
    document.body.appendChild(bar);
  }
  video.srcObject = stream;
  video.muted = true;
  video.setAttribute('playsinline', '');
  video.playsInline = true;
  video.style.display = 'block';
  try {
    await video.play();
  } catch {
    /* muted + playsinline usually enough */
  }
  if (!fromWaiting) {
    document.getElementById('profileEmoji').style.display = 'none';
    document.getElementById('profileImg').style.display = 'none';
    document.getElementById('captureBar').classList.add('show');
  }
}

function captureProfilePhoto() {
  const video = document.getElementById('profileVideo');
  if (!video || !state.cameraStream) return;
  const c = document.createElement('canvas');
  c.width = 240;
  c.height = 240;
  c.getContext('2d').drawImage(video, 0, 0, 240, 240);
  state.avatarDataUrl = c.toDataURL('image/jpeg', 0.8);
  stopCamera();
  updateProfileAvatarUI();
}

function cancelCapture() {
  stopCamera();
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
  const profileVideo = document.getElementById('profileVideo');
  if (profileVideo) {
    profileVideo.srcObject = null;
    profileVideo.style.display = 'none';
  }
  document.getElementById('captureBar')?.classList.remove('show');
}

function submitProfile() {
  state.studentName = document.getElementById('profileName').value.trim().slice(0, 20);
  if (!state.studentName) return;

  if (isBackendMode()) {
    const room = HTD.getRoom() || state.backendRoom;
    HTDBridge.init()
      .then(() =>
        HTDBridge.joinRoom({
          pin: room.pin,
          name: state.studentName,
          isHost: false,
          avatar: state.avatarDataUrl || null,
        })
      )
      .then(data => {
        state.playerId = state.studentName;
        state.waitingJoinedAt = Date.now();
        state.backendMode = true;
        state.joinedPin = room.pin;
        setupStudentReconnect();
        sfx('join');
        handleBackendRoomJoined(data, { allowDuckPick: true });
      })
      .catch(err => alert(err.message || 'Không vào được phòng.'));
    return;
  }

  state.playerId = HTD.genId();
  state.waitingJoinedAt = Date.now();
  saveMyPlayer();
  sfx('join');
  showScreen('waiting');
}

// ─── Mất mạng / tự động vào lại phòng ───
let netBannerHideTimer = null;

function showNetBanner(text, reconnected = false) {
  const banner = document.getElementById('netBanner');
  const textEl = document.getElementById('netBannerText');
  if (!banner) return;
  clearTimeout(netBannerHideTimer);
  if (textEl) textEl.textContent = text;
  banner.classList.toggle('reconnected', reconnected);
  banner.hidden = false;
  if (reconnected) {
    netBannerHideTimer = setTimeout(() => { banner.hidden = true; }, 2200);
  }
}

function hideNetBanner() {
  clearTimeout(netBannerHideTimer);
  const banner = document.getElementById('netBanner');
  if (banner) banner.hidden = true;
}

/**
 * Socket.io tự nối lại transport, nhưng server chỉ nhớ HS qua join_room —
 * nên sau mỗi lần reconnect phải join lại (player_token trong sessionStorage
 * chứng minh danh tính, server đồng bộ lại câu hỏi/kết quả hiện tại).
 */
function setupStudentReconnect() {
  if (state.reconnectSetup) return;
  state.reconnectSetup = true;

  HTDSocket.on('disconnect', () => {
    if (!state.joinedPin) return;
    state.wasDisconnected = true;
    showNetBanner('Mất kết nối — đang kết nối lại…');
  });

  HTDSocket.on('connect', () => {
    if (!state.joinedPin || !state.wasDisconnected) return;
    rejoinAfterReconnect();
  });
}

function rejoinAfterReconnect(attempt = 0) {
  if (!state.joinedPin || !state.studentName) return;
  showNetBanner('Có mạng lại — đang vào lại phòng…');
  HTDBridge.joinRoom({
    pin: state.joinedPin,
    name: state.studentName,
    isHost: false,
    avatar: state.avatarDataUrl || null,
  })
    .then(data => {
      state.wasDisconnected = false;
      showNetBanner('Đã kết nối lại! 🎉', true);
      handleBackendRoomJoined(data);
    })
    .catch(err => {
      const msg = err?.message || '';
      // Socket cũ chưa kịp bị server đánh dấu rời phòng — chờ chút rồi thử lại
      if (attempt < 4 && /đã được sử dụng/i.test(msg)) {
        setTimeout(() => rejoinAfterReconnect(attempt + 1), 2500);
        return;
      }
      state.joinedPin = null;
      state.wasDisconnected = false;
      hideNetBanner();
      alert(msg || 'Không vào lại được phòng.');
      showScreen('home');
    });
}

function handleBackendRoomJoined(data, opts = {}) {
  state.myScore = Number(data?.score || 0);
  if (data?.play_mode_slug) state.playMode = data.play_mode_slug;

  // Theme màn hình HS do giáo viên quyết định — áp dụng ngay khi vào phòng
  if (data?.student_theme && window.HTDTheme) {
    HTDTheme.set(data.student_theme);
  }

  if (data?.room_status === 'ended') {
    // Phòng đã kết thúc — server sẽ gửi lại game_ended để hiện màn kết quả.
    return;
  }

  if (data?.room_status === 'playing') {
    state.lateJoinSync = true;
    state.serverQuestionIndex = Number(data.question_index ?? 0);
    const room = HTD.getRoom() || state.backendRoom;
    if (room) {
      room.status = 'started';
      room.startedAt = Date.now();
      HTD.setRoom(room);
    }
    // Ack resolves before server late-join sync; stay on question if sync already arrived.
    if (!state.currentQuestion && state.screen !== 'question') {
      showScreen('waiting');
    }
    return;
  }

  if (opts.allowDuckPick && data?.play_mode_slug === 'duck_race') {
    state.duckSprite = data.duck_sprite || null;
    state.duckSprites = Array.isArray(data.duck_sprites) ? data.duck_sprites : [];
    showScreen('duck-pick');
    return;
  }

  showScreen('waiting');
}

function cancelResultPhase() {
  state.resultPhaseTimers.forEach(id => clearTimeout(id));
  state.resultPhaseTimers = [];
  clearInterval(state.lbInterval);
  state.lbInterval = null;
  document.getElementById('resultOverlay')?.classList.remove('show');
  state.pendingNewQuestion = null;
  state.pendingLeaderboard = null;
  state.awaitingResult = false;
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
  if (isBackendMode()) {
    return (state.displayPlayers || []).map(p => ({
      ...p,
      isMe: p.name === state.studentName,
    }));
  }
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
  const prevCount = state.lastWaitingCount || 0;
  document.getElementById('playersGrid').innerHTML = players
    .map((p, idx) => {
      const isMe = Boolean(p.isMe) || p.id === state.playerId || p.name === state.studentName;
      const av = p.avatarDataUrl
        ? `<img src="${p.avatarDataUrl}" alt="">`
        : p.avatarEmoji || '😀';
      const isNew = idx >= prevCount; // chỉ card mới xuất hiện mới pop
      return `<div class="player-card${isMe ? ' me' : ''}${isNew ? ' pop-in' : ''}">
      <div class="player-av">${av}</div>
      <div class="player-name">${p.name}${isMe ? ' (bạn)' : ''}</div>
    </div>`;
    })
    .join('');
  if (players.length > prevCount && prevCount > 0 && state.screen === 'waiting') {
    sfx('join');
    const cards = document.querySelectorAll('#playersGrid .player-card');
    const newest = cards[cards.length - 1];
    if (newest && window.HTDFx) HTDFx.burstAtElement(newest, { count: 10 });
  }
  state.lastWaitingCount = players.length;
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
    if (isBackendMode()) return;

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

function keyboardHasSendKey(config) {
  return Boolean(config?.rows?.some(row => row.keys?.some(k => k.type === 'send')));
}

function getFormulaSmartContext(config) {
  const defaults = EquationUI.DEFAULT_SMART_CONTEXT;
  if (!config) return defaults;
  return { ...defaults, ...(config.smart_context || {}) };
}

function syncInputSubmitButton(config) {
  const submitBtn = document.getElementById('qAnswerSubmitBtn');
  const actions = document.getElementById('qInputActions');
  if (!submitBtn || !actions) return;
  const hasDynSend = keyboardHasSendKey(config);
  submitBtn.hidden = hasDynSend;
  actions.hidden = false;
}

function buildChemKeyboard(config) {
  const kb = document.getElementById('chemKb');
  const kbWrap = document.getElementById('qKeyboard');
  if (!kb) return;

  kb.dataset.built = '';
  HTDKeyboardRuntime.clear(kb);
  kbWrap?.classList.remove('has-dyn-kb');

  if (config?.rows?.length) {
    const ok = HTDKeyboardRuntime.render(kb, config, onDynamicKeyPress);
    if (ok) {
      kb.dataset.built = 'dynamic';
      kbWrap?.classList.add('has-dyn-kb');
      syncInputSubmitButton(config);
      return;
    }
  }

  syncInputSubmitButton(null);

  if (kb.dataset.built === 'static') return;
  kb.dataset.built = 'static';
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

function onDynamicKeyPress(key) {
  if (!key) return;
  const type = key.type || 'normal';
  const value = HTDKeyboardRuntime.resolveKeyInputValue(key);

  if (type === 'send' || value === '\n') {
    submitAnswer();
    return;
  }
  if (type === 'delete' || value === '⌫') {
    onChemKey('⌫');
    return;
  }
  if (type === 'space' || value === ' ') {
    onChemKey(' ');
    return;
  }
  onChemKey(value);
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
  const q = getCurrentQuestion();
  if (!state.focusedSlotId || !q?.template) return null;
  const part = q.template.find(
    p => (p.t === 'coef' || p.t === 'blank') && p.id === state.focusedSlotId
  );
  return part?.t || null;
}

function onChemKey(val) {
  if (!state.eqController) return;
  sfx('tap');
  const q = getCurrentQuestion();
  const slotType = getFocusedSlotType();

  if (val === '⌫') {
    if (q?.inputMode === 'formula') {
      state.eqController.backspace();
    } else {
      state.focusedSlotId = state.eqController.backspace(state.focusedSlotId);
      state.eqController.render(state.focusedSlotId);
    }
    return;
  }

  if (/^\d$/.test(val) && slotType === 'coef' && state.eqController.inputDigit) {
    state.focusedSlotId = state.eqController.inputDigit(val, state.focusedSlotId);
    state.eqController.render(state.focusedSlotId);
    return;
  }

  if (q?.inputMode === 'formula') {
    state.eqController.append(val);
    return;
  }

  if (state.eqController.append) {
    state.focusedSlotId = state.eqController.append(val, state.focusedSlotId);
    state.eqController.render(state.focusedSlotId);
  }
}

function onCoefKey(key) {
  if (!state.eqController?.inputDigit) return;
  const slotType = getFocusedSlotType();
  if (slotType === 'blank') return;
  sfx('tap');
  if (key === '⌫') {
    state.focusedSlotId = state.eqController.backspace(state.focusedSlotId);
  } else {
    state.focusedSlotId = state.eqController.inputDigit(key, state.focusedSlotId);
  }
  state.eqController.render(state.focusedSlotId);
}

function toggleKeyboard(forceOpen) {
  const kb = document.getElementById('qKeyboard');
  state.keyboardOpen = true;
  kb?.classList.add('open');
  kb?.classList.remove('collapsed');
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
  if (!game.paused) timerTickFx(sec);
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
  if (isBackendMode()) return;
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
      if (sec <= 0 && !state.awaitingResult && !game.paused) {
        autoEndQuestion();
      }
    } else if (state.screen === 'leaderboard') {
      state.questionIndex = game.questionIndex;
      state.submitted = false;
      showScreen('question');
    }
  } else if (game.phase === 'leaderboard') {
    if (state.screen === 'question' && state.lockedIn && !state.awaitingResult) {
      showWaitingForResult();
    } else if (state.screen === 'leaderboard') {
      updateLbCountdownFromGame();
    }
  } else if (game.phase === 'final') {
    if (state.screen !== 'final') {
      clearInterval(state.lbInterval);
      showScreen('final');
    }
  }

  if (room.status === 'waiting' && !['waiting', 'home', 'join', 'profile'].includes(state.screen)) {
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
  const q = getCurrentQuestion();
  if (!q || state.awaitingResult) return;

  if (state.lockedIn) {
    if (isBackendMode()) showWaitingForResult();
    else finalizeDemoQuestion();
    return;
  }

  if (q.type === 'mc') finishMC(state.selectedAnswer ?? -1, true);
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
  const q = getCurrentQuestion();
  if (!q) return;

  resetQuestionAnswerState();
  state.selectedAnswer = null;
  state.lastTickSec = null;
  const screen = document.querySelector('.question-screen');
  screen.classList.toggle('mode-input', q.type === 'input');
  screen.classList.toggle('mode-mc', q.type === 'mc');

  // Intro: card rơi xuống + tiếng "vào câu"
  replayAnim(document.getElementById('qCard'), 'q-drop-in');
  sfx('countdown-go');
  updateStreakBadge();

  document.getElementById('qProgress').textContent = isBackendMode()
    ? state.questionTotal
      ? `Câu ${state.questionIndex + 1}/${state.questionTotal}`
      : `Câu ${state.questionIndex + 1}`
    : `Câu ${state.questionIndex + 1}/${FAKE_QUESTIONS.length}`;
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
    eqEl.hidden = true;
    inputZone.hidden = true;
  } else {
    eqEl.hidden = false;
    setupInputQuestion(q);
    inputZone.hidden = false;
    toggleKeyboard(true);
  }

  document.getElementById('qStarBtn').classList.remove('active');
  state.lastGameQuestionIndex = HTD.getRoom()?.game?.questionIndex ?? state.questionIndex;
  startQuestionTimer(q, () => {
    if (q.type === 'mc') finishMC(-1);
    else submitAnswer(true);
  });
  syncGamePoll();
}

function startQuestionTimer(q, onEnd) {
  if (q.noTimer || isDuckRaceMode()) {
    clearInterval(state.timerInterval);
    const timerPill = document.getElementById('qTimerPill');
    if (timerPill) timerPill.hidden = true;
    updateDuckScoreDisplay();
    return;
  }
  const timerPill = document.getElementById('qTimerPill');
  if (timerPill) timerPill.hidden = false;
  if (isBackendMode() && q.serverTime) {
    clearInterval(state.timerInterval);
    const offset = HTDSocket.getNtpOffset();
    const tick = () => {
      const now = Date.now() + offset;
      const elapsed = (now - q.serverTime) / 1000;
      const sec = Math.max(0, Math.ceil(q.timeLimit - elapsed));
      const text = document.getElementById('qTimerText');
      const pill = document.getElementById('qTimerPill');
      if (text) text.textContent = formatTimer(sec);
      if (pill) {
        pill.classList.remove('warn', 'danger', 'paused');
        if (sec <= 3) pill.classList.add('danger');
        else if (sec <= 5) pill.classList.add('warn');
      }
      timerTickFx(sec);
      return sec;
    };
    tick();
    state.timerInterval = setInterval(() => {
      const sec = tick();
      if (sec <= 0 && !state.awaitingResult) {
        clearInterval(state.timerInterval);
        onEnd();
      }
    }, 200);
    return;
  }
  startSyncedTimer(onEnd);
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
    btn.className = 'mc-option opt-pop';
    btn.style.animationDelay = `${i * 60}ms`; // đáp án pop lần lượt
    const optText = q.options[i] || '';
    btn.innerHTML =
      `<span class="mc-option-label">${label}.</span>` +
      `<span class="mc-option-text">${EquationUI.chemToHtml(optText)}</span>` +
      `<span class="mc-check" aria-hidden="true">✓</span>`;
    btn.addEventListener('animationend', () => {
      btn.classList.remove('opt-pop');
      btn.style.animationDelay = '';
    }, { once: true });
    btn.onclick = () => {
      if (state.awaitingResult || state.submitting) return;
      sfx('tap');
      wrap.querySelectorAll('.mc-option').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      state.selectedAnswer = i;
      if (isDuckRaceMode()) {
        finishMC(i, false);
        return;
      }
      const submitBtn = document.getElementById('mcSubmitBtn');
      if (submitBtn) submitBtn.disabled = false;
    };
    wrap.appendChild(btn);
  });
  container.appendChild(wrap);

  if (!isDuckRaceMode()) {
    const submitRow = document.createElement('div');
    submitRow.className = 'mc-submit-row';
    submitRow.innerHTML =
      '<button type="button" class="btn-mc-submit" id="mcSubmitBtn" disabled onclick="submitMcAnswer()">Gửi đáp án</button>';
    container.appendChild(submitRow);
  }
}

function submitMcAnswer() {
  if (state.awaitingResult || state.selectedAnswer === null) return;
  finishMC(state.selectedAnswer, false);
}

function setupInputQuestion(q) {
  buildCoefNumpad();
  const smartContext = getFormulaSmartContext(q.keyboardConfig);
  const eqEl = document.getElementById('qEqDisplay');
  const chemKb = document.getElementById('chemKb');
  const coefPad = document.getElementById('coefNumpad');
  // Chọn bàn phím theo nội dung template (hỗ trợ trộn hệ số / số nhỏ / ô điền):
  // có ô số (hệ số hoặc chỉ số) → hiện bàn phím số; có ô điền công thức → hiện bàn phím hóa học.
  const hasNumeric = (q.template || []).some(p => p.t === 'coef' || p.t === 'sub');
  const hasChem = (q.template || []).some(p => p.t === 'blank');
  const showCoef = hasNumeric;
  const showChem = hasChem;

  chemKb.style.display = showChem ? '' : 'none';
  coefPad.style.display = showCoef ? '' : 'none';
  coefPad.hidden = !showCoef;

  if (showChem) {
    buildChemKeyboard(q.keyboardConfig);
  } else {
    HTDKeyboardRuntime.clear(chemKb);
    chemKb.dataset.built = '';
    syncInputSubmitButton(null);
  }

  const onFocus = id => { state.focusedSlotId = id; };
  const onChange = () => {};

  if (q.inputMode === 'formula') {
    state.inputValues = EquationUI.createFormulaState();
    state.focusedSlotId = null;
    state.eqController = EquationUI.FormulaController({
      container: eqEl,
      values: state.inputValues,
      smartContext,
      onChange,
    });
    state.eqController.render();
    return;
  }

  state.inputValues = EquationUI.createInputState(q.template);
  state.focusedSlotId = null;

  const ctrlOpts = {
    container: eqEl,
    template: q.template,
    values: state.inputValues,
    onFocus,
    onChange,
    smartContext,
  };

  if (hasNumeric && hasChem) {
    state.eqController = EquationUI.MixedController(ctrlOpts);
  } else if (hasNumeric) {
    state.eqController = EquationUI.CoefController(ctrlOpts);
  } else {
    state.eqController = EquationUI.BlankController(ctrlOpts);
  }

  state.eqController.render(null);
  const firstSlot = q.template.find(p => p.t === 'coef' || p.t === 'sub' || p.t === 'blank');
  if (firstSlot) state.focusedSlotId = firstSlot.id;
  state.eqController.render(state.focusedSlotId);
}

const RESULT_DISPLAY_MS = 4000;
const LEADERBOARD_DISPLAY_MS = 5000;

function resetQuestionAnswerState() {
  state.submitted = false;
  state.lockedIn = false;
  state.lockedElapsedSeconds = 0;
  state.lockedAnswerDisplay = '';
  state.awaitingResult = false;
  hideLockedBanner();
}

function hideLockedBanner() {
  const banner = document.getElementById('qLockedBanner');
  if (banner) banner.hidden = true;
}

function showLockedBanner(answerDisplay, elapsedSeconds) {
  state.lockedIn = true;
  state.lockedAnswerDisplay = answerDisplay || '—';
  state.lockedElapsedSeconds = Number(elapsedSeconds || 0);

  const banner = document.getElementById('qLockedBanner');
  const answerEl = document.getElementById('qLockedAnswer');
  const timeEl = document.getElementById('qLockedTime');
  if (!banner || !answerEl || !timeEl) return;

  answerEl.textContent = state.lockedAnswerDisplay;
  timeEl.textContent = `Thời gian nộp: ${state.lockedElapsedSeconds}s`;
  banner.hidden = false;
  replayAnim(banner, 'stamp-in');
  sfx('pop');

  const submitBtn = document.getElementById('mcSubmitBtn');
  if (submitBtn) submitBtn.textContent = 'Cập nhật đáp án';
}

function formatAnswerDisplayLocal(q, answerPayload) {
  if (!q) return '—';
  if (q.type === 'mc') {
    const idx = typeof answerPayload === 'object' ? answerPayload.index : answerPayload;
    if (idx == null || idx < 0) return '—';
    const labels = ['A', 'B', 'C', 'D', 'E', 'F'];
    const label = labels[idx] || String(idx + 1);
    const text = q.options?.[idx] || '';
    return text ? `${label}. ${text}` : label;
  }
  if (answerPayload?.text) return answerPayload.text;
  return '—';
}

function lockInAnswer(answerPayload, serverData) {
  const q = getCurrentQuestion();
  const display = serverData?.answer_display
    || formatAnswerDisplayLocal(q, answerPayload);
  const elapsed = serverData?.elapsed_seconds ?? state.lockedElapsedSeconds;
  showLockedBanner(display, elapsed);
}

function showWaitingForResult() {
  state.awaitingResult = true;
  const pill = document.getElementById('qTimerText');
  if (pill) pill.textContent = 'Chờ…';
}

function clearInputAnswer() {
  if (!state.eqController) return;
  state.focusedSlotId = state.eqController.clearAll();
  const q = getCurrentQuestion();
  if (q?.inputMode !== 'formula') {
    state.eqController.render(state.focusedSlotId);
  }
}

function submitAnswer(timedOut) {
  const q = getCurrentQuestion();
  if (!q || state.awaitingResult) return;

  if (isBackendMode()) {
    const answer = HTDGameAdapter.buildSubmitPayload(q, {
      inputValues: state.inputValues,
      selectedAnswer: state.selectedAnswer,
    });
    if (isDuckRaceMode()) {
      if (state.submitting) return;
      state.submitting = true;
      HTDBridge.submitAnswer(q.id, answer)
        .then(ack => handleDuckRaceSubmitResult(ack?.data || ack))
        .catch(err => alert(err.message || 'Không nộp được đáp án.'))
        .finally(() => { state.submitting = false; });
      return;
    }
    HTDBridge.submitAnswer(q.id, answer)
      .then(data => {
        lockInAnswer(answer, data);
        if (timedOut) showWaitingForResult();
      })
      .catch(err => {
        alert(err.message || 'Không nộp được đáp án.');
      });
    return;
  }

  if (timedOut && !state.lockedIn) {
    finalizeDemoQuestion();
    return;
  }

  if (!state.lockedIn) {
    const game = HTD.getRoom()?.game;
    const sec = game ? HTD.getQuestionTimeRemaining(game) : state.timer;
    state.lockedElapsedSeconds = Math.max(1, (q.timeLimit || 20) - sec);
  }
  const answerPayload = {
    text: EquationUI.formulaSerialize(state.inputValues?.formulaTokens || [])
      || Object.values(state.inputValues?.blank || {})[0] || '',
  };
  lockInAnswer(answerPayload, {
    answer_display: formatAnswerDisplayLocal(q, answerPayload),
    elapsed_seconds: state.lockedElapsedSeconds,
  });
  if (timedOut) finalizeDemoQuestion();
}

function finishMC(ans, timedOut) {
  const q = getCurrentQuestion();
  if (!q || state.awaitingResult) return;

  if (isBackendMode()) {
    if (isDuckRaceMode()) {
      if (state.submitting) return;
      state.submitting = true;
      HTDBridge.submitAnswer(q.id, { index: ans })
        .then(ack => handleDuckRaceSubmitResult(ack?.data || ack))
        .catch(err => alert(err.message || 'Không nộp được đáp án.'))
        .finally(() => { state.submitting = false; });
      return;
    }
    HTDBridge.submitAnswer(q.id, { index: ans })
      .then(data => {
        lockInAnswer({ index: ans }, data);
        if (timedOut) showWaitingForResult();
      })
      .catch(err => {
        alert(err.message || 'Không nộp được đáp án.');
      });
    return;
  }

  if (timedOut && !state.lockedIn) {
    finalizeDemoQuestion(ans);
    return;
  }

  if (!state.lockedIn) {
    const game = HTD.getRoom()?.game;
    const sec = game ? HTD.getQuestionTimeRemaining(game) : state.timer;
    state.lockedElapsedSeconds = Math.max(1, (q.timeLimit || 20) - sec);
  }
  lockInAnswer({ index: ans }, {
    answer_display: formatAnswerDisplayLocal(q, { index: ans }),
    elapsed_seconds: state.lockedElapsedSeconds,
  });
  if (timedOut) finalizeDemoQuestion(ans);
}

function finalizeDemoQuestion(fallbackAns) {
  if (state.awaitingResult) return;
  state.awaitingResult = true;

  const q = getCurrentQuestion();
  const game = HTD.getRoom()?.game;
  const elapsed = state.lockedElapsedSeconds
    || Math.max(0, (q?.timeLimit || 20) - (game ? HTD.getQuestionTimeRemaining(game) : 0));

  let ok = false;
  let ansText = '—';
  if (q?.type === 'mc') {
    const idx = state.selectedAnswer ?? fallbackAns ?? -1;
    ok = idx === q.correct;
    ansText = q.options?.[q.correct] || '—';
    if (!state.lockedIn && (fallbackAns === -1 || idx < 0)) ok = false;
  } else {
    ok = EquationUI.checkAnswer(q, state.inputValues);
    ansText = EquationUI.formatCorrectAnswer(q);
  }

  const remaining = Math.max(0, (q?.timeLimit || 20) - elapsed);
  const pts = ok ? Math.round(1000 * (remaining / (q?.timeLimit || 20))) : 0;
  if (ok) state.myScore += pts;

  showResult({
    correct: ok,
    score_earned: pts,
    total_score: state.myScore,
    elapsed_seconds: elapsed,
    my_answer: state.lockedAnswerDisplay || formatAnswerDisplayLocal(q, { index: state.selectedAnswer }),
    correct_answer: ansText,
    question_rank_correct: ok ? 1 : null,
    fastest_correct: ok ? { name: state.studentName || 'Bạn', elapsed_seconds: elapsed } : null,
  });
}

// ─── Result / Leaderboard / Final ───
function showResult(result) {
  cancelResultPhase();
  state.awaitingResult = true;
  const ok = Boolean(result.correct);
  const pts = Number(result.score_earned || 0);
  const elapsed = Number(result.elapsed_seconds || state.lockedElapsedSeconds || 0);
  const myAnswer = result.my_answer || state.lockedAnswerDisplay || '—';
  let correctAns = result.correct_answer || '—';
  if (typeof correctAns === 'object') correctAns = JSON.stringify(correctAns);

  const ov = document.getElementById('resultOverlay');
  document.getElementById('resultCircle').className = 'result-circle ' + (ok ? 'correct' : 'wrong');
  document.getElementById('resultCircle').textContent = ok ? '✓' : '✗';
  document.getElementById('resultTitle').textContent = ok ? 'Đúng rồi!' : 'Chưa đúng!';

  const metaEl = document.getElementById('resultMeta');
  if (metaEl) {
    metaEl.innerHTML =
      `<p class="result-my-answer">Bạn chọn: <strong>${myAnswer}</strong></p>` +
      `<p class="result-elapsed">Thời gian nộp: <strong>${elapsed}s</strong></p>`;
  }

  document.getElementById('resultPoints').textContent = ok ? `+${pts} điểm` : '+0 điểm';
  document.getElementById('resultAnswer').textContent = ok ? '' : `Đáp án đúng: ${correctAns}`;

  const rankEl = document.getElementById('resultRank');
  if (rankEl) {
    const parts = [];
    if (ok && result.question_rank_correct) {
      parts.push(`Hạng đúng nhanh: #${result.question_rank_correct}`);
    }
    if (result.fastest_correct?.name) {
      const fc = result.fastest_correct;
      const fcLabel = fc.name === state.studentName ? 'Bạn' : fc.name;
      parts.push(`Nhanh nhất đúng: ${fcLabel} (${fc.elapsed_seconds}s)`);
    }
    rankEl.textContent = parts.join(' · ');
    rankEl.hidden = parts.length === 0;
  }

  if (isBackendMode() && result.total_score != null) {
    state.myScore = Number(result.total_score);
  }

  // Chuỗi đúng liên tiếp
  state.streak = ok ? state.streak + 1 : 0;
  updateStreakBadge();

  const card = ov.querySelector('.result-card');
  ov.classList.toggle('wrong-bg', !ok);
  if (card) card.classList.toggle('is-wrong', !ok);

  ov.classList.add('show');
  if (ok) {
    sfx('correct');
    if (typeof confetti === 'function') {
      // hai nguồn confetti chéo từ 2 mép dưới
      confetti({ particleCount: 50, angle: 60, spread: 60, origin: { x: 0, y: 0.85 } });
      confetti({ particleCount: 50, angle: 120, spread: 60, origin: { x: 1, y: 0.85 } });
    }
    setTimeout(() => {
      const circle = document.getElementById('resultCircle');
      if (window.HTDFx) {
        HTDFx.burstAtElement(circle, { count: 22 });
        if (pts > 0) {
          HTDFx.floatText(window.innerWidth / 2, window.innerHeight * 0.3, `+${pts}`, { size: 34 });
        }
      }
    }, 120);
  } else {
    sfx('wrong');
    if (window.HTDFx) HTDFx.shake();
    if (card) replayAnim(card, 'wobble-sad');
  }

  if (!isBackendMode()) updateScores();

  const resultTimer = setTimeout(() => {
    ov.classList.remove('show');
    if (state.pendingLeaderboard) {
      applyLeaderboardData(state.pendingLeaderboard);
      state.pendingLeaderboard = null;
    }
    renderLeaderboard();
    showScreen('leaderboard');
    startLbCountdown(LEADERBOARD_DISPLAY_MS / 1000);
    const lbTimer = setTimeout(() => {
      if (state.pendingNewQuestion) {
        cancelResultPhase();
        applyNewQuestion(state.pendingNewQuestion);
      }
    }, LEADERBOARD_DISPLAY_MS);
    state.resultPhaseTimers.push(lbTimer);
  }, RESULT_DISPLAY_MS);
  state.resultPhaseTimers = [resultTimer];
}

function applyLeaderboardData(data) {
  state.students = HTDGameAdapter.mapLeaderboard(data.top5).map((s, i) => {
    const withMe = {
      ...s,
      isMe: s.name === state.studentName,
      id: s.id || `lb-${i}`,
    };
    if (withMe.isMe && state.avatarDataUrl && !withMe.avatarDataUrl) {
      withMe.avatarDataUrl = state.avatarDataUrl;
      withMe.avatarEmoji = null;
    }
    return withMe;
  });
}

function startLbCountdown(seconds) {
  clearInterval(state.lbInterval);
  let sec = seconds;
  const el = document.getElementById('lbCountdown');
  const tick = () => {
    if (el) el.textContent = sec > 0 ? `Câu tiếp sau ${sec}s...` : '...';
    sec -= 1;
  };
  tick();
  state.lbInterval = setInterval(() => {
    tick();
    if (sec < 0) clearInterval(state.lbInterval);
  }, 1000);
}

function updateScores() {
  if (isBackendMode()) return;
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
          // vượt hạng (đi lên) → nháy vàng sau khi trượt xong
          if (diff > 2) replayAnim(el, 'lb-flash');
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

/* Registry vịt chuyển động (frame + fps) quản lý trong DB — resolve token "db:{id}"
   từ mode_config.visual.duck_sprites. Xem php-admin/app/Http/Controllers/Api/DuckSpriteController.php */
const duckSpriteRegistry = { loaded: false, loading: null, byToken: {} };

async function ensureDuckSpriteRegistry() {
  if (duckSpriteRegistry.loaded) return duckSpriteRegistry.byToken;
  if (!duckSpriteRegistry.loading) {
    duckSpriteRegistry.loading = fetch('/api/duck-sprites/public')
      .then(res => res.json())
      .then(json => {
        (json?.data || []).forEach(duck => {
          duckSpriteRegistry.byToken[`db:${duck.id}`] = {
            fps: Number(duck.fps) || 10,
            frames: (duck.frames || []).map(f => f.url),
          };
        });
      })
      .catch(() => {})
      .finally(() => {
        duckSpriteRegistry.loaded = true;
      });
  }
  await duckSpriteRegistry.loading;
  return duckSpriteRegistry.byToken;
}

function resolveDuckFrames(sprite) {
  return String(sprite || '').startsWith('db:') ? duckSpriteRegistry.byToken[sprite] || null : null;
}

function studentDuckImgUrl(sprite) {
  const base = '/htd-admin/assets/duck-race/';
  const fallback = `${base}ducks/duck-blue.gif`;
  if (!sprite) return fallback;
  const dbEntry = resolveDuckFrames(sprite);
  if (dbEntry) return dbEntry.frames[0] || fallback;
  if (String(sprite).startsWith('db:')) return fallback; // chưa load xong registry
  const path = String(sprite).replace(/^\//, '');
  return path.startsWith('htd-admin/') ? `/${path}` : `${base}${path}`;
}

async function renderDuckPicker() {
  const grid = document.getElementById('duckPickGrid');
  if (!grid) return;
  const sprites = state.duckSprites.length ? state.duckSprites : [state.duckSprite].filter(Boolean);

  if (sprites.some(s => String(s).startsWith('db:'))) {
    await ensureDuckSpriteRegistry();
  }
  if (state.screen !== 'duck-pick') return; // đã rời màn hình trong lúc chờ tải

  grid.innerHTML = sprites
    .map(
      (sprite, i) => `
        <button type="button" class="duck-pick-item" onclick="selectDuckSprite('${String(sprite).replace(/'/g, "\\'")}')">
          <img id="duckPickImg${i}" src="${studentDuckImgUrl(sprite)}" alt="">
        </button>
      `,
    )
    .join('');

  startDuckPickAnimation(sprites);
}

/* Vòng lặp rAF nhẹ, tự dừng khi rời màn hình duck-pick — cùng thuật toán với
   preview animation trong admin (php-admin/public/js/duck-sprite-manager.js). */
function startDuckPickAnimation(sprites) {
  const anims = sprites
    .map((sprite, i) => {
      const entry = resolveDuckFrames(sprite);
      if (!entry || entry.frames.length < 2) return null;
      return { img: document.getElementById(`duckPickImg${i}`), urls: entry.frames, fps: entry.fps, idx: 0, acc: 0 };
    })
    .filter(Boolean);
  if (!anims.length) return;

  let lastTs = 0;
  function tick(ts) {
    if (state.screen !== 'duck-pick') return;
    const dt = lastTs ? ts - lastTs : 0;
    lastTs = ts;
    anims.forEach(anim => {
      if (!anim.img?.isConnected) return;
      anim.acc += dt;
      const frameMs = 1000 / anim.fps;
      if (anim.acc >= frameMs) {
        anim.idx = (anim.idx + Math.floor(anim.acc / frameMs)) % anim.urls.length;
        anim.acc %= frameMs;
        anim.img.src = anim.urls[anim.idx];
      }
    });
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

function selectDuckSprite(sprite) {
  sfx('tap');
  state.duckSprite = sprite;
  if (isBackendMode()) {
    HTDBridge.selectDuck(sprite).catch(() => {});
  }
  proceedFromDuckPick();
}

function skipDuckPick() {
  sfx('tap');
  proceedFromDuckPick();
}

function proceedFromDuckPick() {
  state.waitingJoinedAt = Date.now();
  showScreen('waiting');
}

function formatStudentFinishTime(elapsedS) {
  return elapsedS != null && Number.isFinite(Number(elapsedS))
    ? `${Number(elapsedS).toFixed(4)}s`
    : '';
}

function renderFinal() {
  const rows = [...state.students].sort(
    (a, b) => Number(b.score || 0) - Number(a.score || 0) || a.name.localeCompare(b.name),
  );
  const podiumEl = document.getElementById('studentFinalPodium');
  const listEl = document.getElementById('studentFinalList');
  if (!podiumEl || !listEl) return;

  const duckRace = isDuckRaceMode();
  let slot1;
  let slot2;
  let slot3;
  if (duckRace) {
    const finishers = rows
      .filter((s) => s.finishElapsedS != null && Number.isFinite(Number(s.finishElapsedS)))
      .sort(
        (a, b) =>
          Number(a.finishElapsedS) - Number(b.finishElapsedS) ||
          String(a.name).localeCompare(String(b.name)),
      );
    slot1 = finishers[0];
    slot2 = finishers[1];
    slot3 = finishers[2];
  } else {
    slot1 = rows[0];
    slot2 = rows[1];
    slot3 = rows[2];
  }

  function renderSlot(player, slotRank) {
    if (!player) return `<div class="student-final-podium-slot slot-${slotRank}"></div>`;
    const time = formatStudentFinishTime(player.finishElapsedS);
    const visual = duckRace
      ? `<img class="student-final-podium-duck" src="${studentDuckImgUrl(player.duckSprite)}" alt="">`
      : (player.avatarDataUrl
        ? `<img class="student-final-podium-avatar" src="${player.avatarDataUrl}" alt="">`
        : `<span class="student-final-podium-avatar student-final-podium-avatar--emoji">${player.avatarEmoji || '😀'}</span>`);
    return `
      <div class="student-final-podium-slot slot-${slotRank}">
        <div class="student-final-podium-card">
          <strong class="student-final-podium-card-name">${player.name}</strong>
          <div class="student-final-podium-card-row">
            <span class="student-final-podium-card-score">${player.score} điểm</span>
            ${time ? `<span class="student-final-podium-card-time">${time}</span>` : ''}
          </div>
        </div>
        <div class="student-final-podium-figure">${visual}</div>
      </div>`;
  }

  podiumEl.innerHTML = `
    <img class="student-final-podium-bg" src="/htd-admin/assets/ket-thuc-tro-choi.png" alt="">
    ${renderSlot(slot2, 2)}
    ${renderSlot(slot1, 1)}
    ${renderSlot(slot3, 3)}
  `;

  // Nghi lễ trao giải: bậc 3 → 2 → 1 lần lượt trồi lên, rồi vương miện + pháo sao + fanfare
  state.finalTimers.forEach(id => clearTimeout(id));
  state.finalTimers = [];
  const slots = [
    [podiumEl.querySelector('.slot-3'), 0],
    [podiumEl.querySelector('.slot-2'), 500],
    [podiumEl.querySelector('.slot-1'), 1000],
  ];
  slots.forEach(([el, delay]) => {
    if (!el) return;
    state.finalTimers.push(setTimeout(() => {
      el.classList.add('podium-rise');
      sfx('pop');
    }, delay));
  });
  state.finalTimers.push(setTimeout(() => {
    const winnerCard = podiumEl.querySelector('.slot-1 .student-final-podium-card');
    if (winnerCard && !winnerCard.querySelector('.student-final-podium-crown')) {
      winnerCard.insertAdjacentHTML(
        'afterbegin',
        '<svg class="icon student-final-podium-crown crown-drop" aria-hidden="true"><use href="#i-crown"/></svg>'
      );
    }
    if (window.HTDFx) HTDFx.sparkleRain({ count: 44 });
    sfx('fanfare');
  }, 1600));

  listEl.innerHTML = rows
    .map((row, index) => {
      const time = formatStudentFinishTime(row.finishElapsedS);
      const rankLabel = duckRace && row.finishRank ? `#${row.finishRank}` : String(index + 1);
      return `
        <li class="student-final-lb-row">
          <span class="student-final-lb-rank">${rankLabel}</span>
          <span class="student-final-lb-name">${row.name}</span>
          <span class="student-final-lb-score">${row.score}</span>
          ${time ? `<span class="student-final-lb-time">${time}</span>` : ''}
        </li>`;
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
  state.streak = 0;
  state.lastDuckScore = null;
  initDisplayPlayers();
  showScreen('waiting');
}

// ─── Demo nav ───
const SCREENS = [
  'home',
  'join',
  'profile',
  'waiting',
  'question',
  'leaderboard',
  'final',
];

const demoNav = document.getElementById('demoNav');
if (demoNav) {
  demoNav.innerHTML =
    SCREENS.map(s => `<button onclick="demoGo('${s}')">${s}</button>`).join('') +
    FAKE_QUESTIONS.map((q, i) =>
      `<button onclick="demoQuestion(${i})" title="${q.id}">q${i + 1}</button>`
    ).join('');
}

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

buildCoefNumpad();
buildNumpad();
renderPinDisplay();

function applyNewQuestion(payload) {
  if (payload?.play_mode) state.playMode = payload.play_mode;

  const sameQuestion =
    state.currentQuestion &&
    payload?.question_id != null &&
    String(state.currentQuestion.id) === String(payload.question_id);

  // Server đánh số câu xuyên suốt các quiz — tin server thay vì tự đếm
  if (payload?.question_index != null) {
    state.serverQuestionIndex = Number(payload.question_index) + 1;
  } else if (!sameQuestion) {
    state.serverQuestionIndex += 1;
  }
  if (payload?.question_count != null) {
    state.questionTotal = Number(payload.question_count) || null;
  }
  state.questionIndex = Math.max(0, state.serverQuestionIndex - 1);
  state.currentQuestion = HTDGameAdapter.mapNewQuestion(payload, state.questionIndex);
  state.pendingNewQuestion = null;

  if (sameQuestion && state.lockedIn && state.screen === 'question') {
    // Reconnect giữa câu: server gửi lại đúng câu đang chờ — giữ nguyên đáp án đã nộp
    return;
  }

  resetQuestionAnswerState();
  document.querySelector('.question-screen')?.classList.toggle('duck-race-mode', isDuckRaceMode());
  updateDuckScoreDisplay();
  showScreen('question');
}

function setupStudentBackendBridge() {
  if (!isBackendMode()) return;

  HTDBridge.on('gameStarted', (data) => {
    if (data?.play_mode) state.playMode = data.play_mode;
    clearInterval(state.waitingPoll);
    if (!state.lateJoinSync) {
      state.questionIndex = 0;
      state.serverQuestionIndex = 0;
      state.myScore = 0;
    }
    const room = HTD.getRoom();
    if (room) {
      room.status = 'started';
      room.startedAt = Date.now();
      if (data?.play_mode) room.playModeSlug = data.play_mode;
      HTD.setRoom(room);
    }
    document.querySelector('.question-screen')?.classList.toggle('duck-race-mode', isDuckRaceMode());
    updateDuckScoreDisplay();
  });

  HTDBridge.on('newQuestion', payload => {
    cancelResultPhase();
    state.lateJoinSync = false;
    applyNewQuestion(payload);
  });

  HTDBridge.on('questionResult', result => {
    showResult(result);
  });

  HTDBridge.on('playersUpdate', data => {
    state.displayPlayers = HTDGameAdapter.mapPlayersUpdate(data.players).map(p => {
      // Keep own photo locally if server omitted (shouldn't) or while reconnecting.
      if (p.name === state.studentName && state.avatarDataUrl && !p.avatarDataUrl) {
        return { ...p, avatarDataUrl: state.avatarDataUrl, avatarEmoji: null };
      }
      if (p.name === state.studentName && p.avatarDataUrl) {
        state.avatarDataUrl = p.avatarDataUrl;
      }
      return p;
    });
    if (state.screen === 'waiting') renderWaitingGrid();
  });

  HTDBridge.on('leaderboardUpdate', data => {
    state.pendingLeaderboard = data;
    if (state.screen === 'leaderboard') {
      applyLeaderboardData(data);
      renderLeaderboard();
    }
  });

  HTDBridge.on('raceUpdate', data => {
    if (!isDuckRaceMode() || !data?.players) return;
    const me = data.players.find(p => p.name === state.studentName);
    if (me && me.score != null) {
      state.myScore = Number(me.score);
      updateDuckScoreDisplay();
    }
  });

  HTDBridge.on('gameEnded', data => {
    if (data.final_leaderboard) {
      state.students = data.final_leaderboard.map((row, i) => ({
        id: `final-${i}`,
        name: row.name,
        score: Number(row.score || 0),
        avatarDataUrl: row.avatar || null,
        avatarEmoji: row.avatar ? null : '😀',
        duckSprite: row.duck_sprite || null,
        finishRank: row.finish_rank ?? null,
        finishElapsedS: row.finish_elapsed_s ?? null,
        isMe: row.name === state.studentName,
      }));
    }
    showScreen('final');
  });

  HTDBridge.on('roomClosed', (data) => {
    state.joinedPin = null;
    state.wasDisconnected = false;
    hideNetBanner();
    alert(data?.message || 'Phòng đã được giáo viên kết thúc.');
    HTD.setRoom(null);
    HTD.setPlayers([]);
    if (typeof HTDSocket !== 'undefined' && HTDSocket.getSocket) {
      HTDSocket.getSocket()?.disconnect();
    }
    showScreen('home');
  });

  HTDBridge.on('roomError', data => {
    alert(data.message || 'Lỗi phòng.');
  });

  HTDBridge.on('themeUpdate', data => {
    if (data?.theme && window.HTDTheme) HTDTheme.set(data.theme);
  });
}

setupStudentBackendBridge();

document.getElementById('qStarBtn')?.addEventListener('click', function () {
  sfx('tap');
  this.classList.toggle('active');
  this.textContent = this.classList.contains('active') ? '★' : '☆';
  if (this.classList.contains('active') && window.HTDFx) {
    HTDFx.burstAtElement(this, { count: 8, color: '#FFC93C' });
  }
});
