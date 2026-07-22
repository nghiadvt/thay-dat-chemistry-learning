/**
 * Test tính năng chọn vịt — chạy: node scripts/duck-pick-test.js
 * Kiểm tra:
 *  1. room_joined của HS trong phòng duck_race trả về play_mode_slug + duck_sprite + duck_sprites.
 *  2. HS chọn 1 vịt hợp lệ trong danh sách → select_duck thành công, player.duck_sprite cập nhật.
 *  3. HS chọn 1 sprite không có trong danh sách → bị từ chối.
 *  4. Host gọi select_duck → bị từ chối.
 */
const { io } = require('socket.io-client');

const WS_URL = process.env.WS_URL || 'http://localhost:38581';
const PHP_URL = process.env.PHP_URL || 'http://localhost:38480';
const DUCK_GAME_ID = Number(process.env.DUCK_GAME_ID || 4);

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

async function createSessionPin(gameId) {
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
    body: JSON.stringify({ game_id: gameId }),
  });
  const json = await sessionRes.json();
  if (!json.success) throw new Error(json.error || JSON.stringify(json));
  return json.data.pin;
}

function assert(cond, message) {
  if (!cond) throw new Error(`ASSERT: ${message}`);
}

async function main() {
  const pin = await createSessionPin(DUCK_GAME_ID);
  console.log('PIN:', pin);

  const host = await connectSocket();
  const s1 = await connectSocket();

  host.emit('join_room', { pin, name: 'Host', is_host: true });
  const hostJoin = await waitForEvent(host, 'room_joined');
  assert(hostJoin.play_mode_slug === 'duck_race', `phòng phải là duck_race (got ${hostJoin.play_mode_slug})`);
  assert(hostJoin.duck_sprites == null, 'host không cần duck_sprites');

  s1.emit('join_room', { pin, name: 'An' });
  const s1Join = await waitForEvent(s1, 'room_joined');
  assert(s1Join.play_mode_slug === 'duck_race', 'HS phải thấy play_mode_slug=duck_race');
  assert(!!s1Join.duck_sprite, 'HS phải có duck_sprite mặc định (random)');
  assert(Array.isArray(s1Join.duck_sprites) && s1Join.duck_sprites.length > 0, 'HS phải có danh sách duck_sprites');
  console.log('1. room_joined trả về play_mode_slug/duck_sprite/duck_sprites OK', {
    duck_sprite: s1Join.duck_sprite,
    count: s1Join.duck_sprites.length,
  });

  const chosen = s1Join.duck_sprites.find((s) => s !== s1Join.duck_sprite) || s1Join.duck_sprites[0];
  const selectRes = await emitAck(s1, 'select_duck', { duck_sprite: chosen });
  assert(selectRes.data.duck_sprite === chosen, 'select_duck phải trả về đúng sprite đã chọn');
  console.log('2. HS chọn vịt hợp lệ OK ->', chosen);

  let invalidBlocked = false;
  try {
    await emitAck(s1, 'select_duck', { duck_sprite: 'ducks/khong-ton-tai.gif' });
  } catch (e) {
    invalidBlocked = /không hợp lệ/i.test(e.message);
  }
  assert(invalidBlocked, 'sprite không hợp lệ phải bị từ chối');
  console.log('3. Sprite không hợp lệ bị chặn OK');

  let hostBlocked = false;
  try {
    await emitAck(host, 'select_duck', { duck_sprite: chosen });
  } catch (e) {
    hostBlocked = true;
  }
  assert(hostBlocked, 'host gọi select_duck phải bị từ chối');
  console.log('4. Host bị chặn chọn vịt OK');

  host.close();
  s1.close();
  console.log('\nDuck pick test OK — tất cả 4 kiểm tra đều pass');
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
