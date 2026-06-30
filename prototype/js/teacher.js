/** Teacher app — website desktop, đồng bộ localStorage với student app */
const teacherState = {
  poll: null,
};

function showTeacherScreen(id) {
  document.querySelectorAll('.teacher-screen').forEach(s => s.classList.remove('active'));
  document.querySelector(`[data-screen="${id}"]`)?.classList.add('active');

  if (id === 'dashboard') {
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

function renderDashboard() {
  const room = HTD.getRoom();
  if (!room) return;

  document.getElementById('teacherRoomName').textContent = room.name;
  document.getElementById('teacherPinDisplay').textContent = room.pin;

  const statusPill = document.getElementById('teacherStatusPill');
  const started = room.status === 'started';
  statusPill.textContent = started ? 'Đang chơi' : 'Đang chờ';
  statusPill.classList.toggle('started', started);

  const banner = document.getElementById('teacherStartedBanner');
  if (banner) banner.style.display = started ? 'block' : 'none';

  renderTeacherList();
}

function renderTeacherList() {
  const room = HTD.getRoom();
  const players = HTD.getPlayers();

  document.getElementById('teacherCount').textContent = `${players.length} học sinh`;

  const btnStart = document.getElementById('btnStartGame');
  btnStart.disabled = players.length < 1 || room?.status === 'started';
  btnStart.textContent = room?.status === 'started' ? 'Đã bắt đầu' : 'Bắt đầu trò chơi';

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
  if (!room || HTD.getPlayers().length < 1) return;
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
