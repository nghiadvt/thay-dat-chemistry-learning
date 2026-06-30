/** Teacher app — website desktop, đồng bộ localStorage với student app */
const teacherState = {
  poll: null,
  dashboardUiReady: false,
};
const LS_SIDEBAR_COLLAPSED = 'htd_teacher_sidebar_collapsed';
const LS_MAIN_SPLIT = 'htd_teacher_main_split';

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

  const statusPill = document.getElementById('teacherStatusPill');
  const started = room.status === 'started';
  statusPill.textContent = started ? 'Đang chơi' : 'Đang chờ';
  statusPill.classList.toggle('started', started);

  const banner = document.getElementById('teacherStartedBanner');
  if (banner) banner.style.display = started ? 'block' : 'none';

  renderTeacherList();
}

function syncModalPinDigits() {
  const src = document.getElementById('teacherPinDigits');
  const dst = document.getElementById('teacherPinDigitsModal');
  if (!src || !dst) return;
  dst.innerHTML = src.innerHTML;
}

function renderTeacherList() {
  const room = HTD.getRoom();
  const realPlayers = HTD.getPlayers();
  const players = HTD.buildDisplayPlayers(realPlayers, null, 50);

  document.getElementById('teacherCount').textContent = `${players.length} học sinh`;

  const btnStart = document.getElementById('btnStartGame');
  const started = room?.status === 'started';
  btnStart.disabled = started;
  btnStart.textContent = started ? 'Đã bắt đầu' : 'Bắt đầu trò chơi';

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
  teacherState.poll = setInterval(() => {
    if (document.querySelector('[data-screen="dashboard"]')?.classList.contains('active')) {
      renderDashboard();
    }
  }, 1500);
}

function teacherStartGame() {
  const room = HTD.getRoom();
  if (!room || room.status === 'started') return;
  room.status = 'started';
  room.startedAt = Date.now();
  HTD.setRoom(room);
  renderDashboard();
}

function resetTeacherRoom() {
  if (!confirm('Tạo phòng mới? Danh sách học sinh sẽ bị xóa.')) return;
  localStorage.removeItem(HTD.LS_ROOM);
  localStorage.removeItem(HTD.LS_PLAYERS);
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
}

function initTeacherDashboardUi() {
  if (teacherState.dashboardUiReady) return;
  teacherState.dashboardUiReady = true;

  document.getElementById('teacherQrEnlargeBtn')?.addEventListener('click', openTeacherQrModal);
  document.getElementById('teacherQrModalBackdrop')?.addEventListener('click', closeTeacherQrModal);
  document.getElementById('teacherQrModalClose')?.addEventListener('click', closeTeacherQrModal);

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
  }

  resizer.addEventListener('mousedown', onMouseDown);
  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
}

function applyMainSplit(grid, leftRatio) {
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

window.openTeacherQrModal = openTeacherQrModal;
window.closeTeacherQrModal = closeTeacherQrModal;
window.toggleTeacherSidebar = toggleTeacherSidebar;
window.resetTeacherRoom = resetTeacherRoom;
window.teacherStartGame = teacherStartGame;
window.createTeacherRoom = createTeacherRoom;
window.demoTeacherGo = demoTeacherGo;

// Demo nav
const TEACHER_SCREENS = ['setup', 'dashboard'];
document.getElementById('demoNav').innerHTML = TEACHER_SCREENS.map(
  s => `<button onclick="demoTeacherGo('${s}')">${s}</button>`
).join('');

function demoTeacherGo(s) {
  if (s === 'dashboard' && !HTD.getRoom()) createTeacherRoom();
  showTeacherScreen(s);
}

initTeacherApp();
