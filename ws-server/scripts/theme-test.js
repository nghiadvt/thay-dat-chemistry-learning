/**
 * Test theme do giáo viên điều khiển — chạy: node scripts/theme-test.js
 * Kiểm tra:
 *  1. Host đổi theme → mọi HS trong phòng nhận theme_update.
 *  2. HS join sau khi đổi → room_joined mang student_theme đã chọn.
 *  3. HS (không phải host) gọi host_set_theme → bị từ chối.
 *  4. Theme không hợp lệ → bị từ chối.
 */
const { io } = require('socket.io-client');

const WS_URL = process.env.WS_URL || 'http://localhost:38581';
const PHP_URL = process.env.PHP_URL || 'http://localhost:38480';

function waitForEvent(socket, event, timeoutMs = 8000) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`Timeout waiting for ${event}`)), timeoutMs);
    socket.once(event, (data) => {
      clearTimeout(timer);
      resolve(data);
    });
  });
}

function emitAck(socket, event, payload = {}) {
  return new Promise((resolve, reject) => {
    socket.emit(event, payload, (res) => {
      if (res && res.success === false) reject(new Error(res.error || `${event} failed`));
      else resolve(res);
    });
  });
}

function connectSocket() {
  const socket = io(WS_URL, { transports: ['websocket'] });
  return waitForEvent(socket, 'connect').then(() => socket);
}

async function createSessionPin() {
  const loginPage = await fetch(`${PHP_URL}/admin/login`);
  const html = await loginPage.text();
  const token = (html.match(/name="_token" value="([^"]+)"/) || [])[1];
  if (!token) throw new Error('CSRF token not found');

  const pageCookies = loginPage.headers.getSetCookie
    ? loginPage.headers.getSetCookie().map((c) => c.split(';')[0])
    : [];

  const loginRes = await fetch(`${PHP_URL}/admin/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Cookie: pageCookies.join('; '),
    },
    body: `email=teacher@hoadat.local&password=password123&_token=${token}`,
    redirect: 'manual',
  });
  const setCookie = loginRes.headers.getSetCookie ? loginRes.headers.getSetCookie() : [];
  const cookie = setCookie.map((c) => c.split(';')[0]).join('; ');
  const xsrfMatch = setCookie.find((c) => c.startsWith('XSRF-TOKEN='));
  const xsrf = xsrfMatch
    ? decodeURIComponent(xsrfMatch.split(';')[0].replace('XSRF-TOKEN=', ''))
    : '';

  const sessionRes = await fetch(`${PHP_URL}/api/game-sessions`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Cookie: cookie,
      'X-XSRF-TOKEN': xsrf,
    },
    body: JSON.stringify({ game_id: 1 }),
  });
  const json = await sessionRes.json();
  if (!json.success) throw new Error(json.error || 'create session failed');
  return json.data.pin;
}

function assert(cond, message) {
  if (!cond) throw new Error(`ASSERT: ${message}`);
}

async function main() {
  const pin = await createSessionPin();
  console.log('PIN:', pin);

  const host = await connectSocket();
  const s1 = await connectSocket();

  host.emit('join_room', { pin, name: 'Host', is_host: true });
  const hostJoin = await waitForEvent(host, 'room_joined');
  assert(hostJoin.student_theme === 'default', 'theme ban đầu phải là default');

  s1.emit('join_room', { pin, name: 'An' });
  await waitForEvent(s1, 'room_joined');

  // ── 1. Host đổi theme → HS nhận theme_update ──
  const themeUpdateP = waitForEvent(s1, 'theme_update');
  await emitAck(host, 'host_set_theme', { theme: 'galaxy' });
  const update = await themeUpdateP;
  assert(update.theme === 'galaxy', `HS phải nhận theme galaxy (got ${update.theme})`);
  console.log('1. Host đổi theme → HS nhận theme_update OK');

  // ── 2. HS join sau → room_joined mang theme đã chọn ──
  const s2 = await connectSocket();
  s2.emit('join_room', { pin, name: 'Binh' });
  const binhJoin = await waitForEvent(s2, 'room_joined');
  assert(binhJoin.student_theme === 'galaxy', `HS join sau phải nhận galaxy (got ${binhJoin.student_theme})`);
  console.log('2. HS join sau nhận đúng theme OK');

  // ── 3. HS không được đổi theme ──
  let studentBlocked = false;
  try {
    await emitAck(s1, 'host_set_theme', { theme: 'lab' });
  } catch (e) {
    studentBlocked = /giáo viên/i.test(e.message);
  }
  assert(studentBlocked, 'HS gọi host_set_theme phải bị từ chối');
  console.log('3. HS bị chặn đổi theme OK');

  // ── 4. Theme không hợp lệ bị từ chối ──
  let invalidBlocked = false;
  try {
    await emitAck(host, 'host_set_theme', { theme: 'hacker-theme' });
  } catch (e) {
    invalidBlocked = /không hợp lệ/i.test(e.message);
  }
  assert(invalidBlocked, 'theme lạ phải bị từ chối');
  console.log('4. Theme không hợp lệ bị chặn OK');

  host.close();
  s1.close();
  s2.close();
  console.log('\nTheme test OK — tất cả 4 kiểm tra đều pass');
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
