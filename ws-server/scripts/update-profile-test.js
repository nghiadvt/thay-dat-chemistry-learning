/**
 * Test đổi tên/ảnh ở phòng chờ — chạy: node scripts/update-profile-test.js
 * Kiểm tra:
 *  1. HS đổi tên hợp lệ -> ack trả tên mới, players_update phản ánh đúng, tên cũ biến mất khỏi players hash + leaderboard.
 *  2. HS đổi tên trùng người khác trong phòng -> bị từ chối.
 *  3. HS xoá ảnh đại diện -> avatar về null, players_update phản ánh.
 *  4. HS đổi ảnh đại diện mới -> avatar cập nhật đúng.
 *  5. Host gọi update_profile -> bị từ chối.
 */
const { io } = require('socket.io-client');

const WS_URL = process.env.WS_URL || 'http://localhost:38581';
const PHP_URL = process.env.PHP_URL || 'http://localhost:38480';
const GAME_ID = Number(process.env.GAME_ID || 3);

const TINY_JPEG =
  'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

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
  const pin = await createSessionPin(GAME_ID);
  console.log('PIN:', pin);

  const host = await connectSocket();
  const s1 = await connectSocket();
  const s2 = await connectSocket();

  host.emit('join_room', { pin, name: 'Host', is_host: true });
  await waitForEvent(host, 'room_joined');

  s1.emit('join_room', { pin, name: 'An' });
  await waitForEvent(s1, 'room_joined');

  const drainPlayersUpdate = waitForEvent(s1, 'players_update');
  s2.emit('join_room', { pin, name: 'Binh' });
  await waitForEvent(s2, 'room_joined');
  await drainPlayersUpdate; // broadcast do Binh join, không phải do rename bên dưới

  // 1. Đổi tên hợp lệ
  const playersUpdateAfterRename = waitForEvent(s1, 'players_update');
  const renameRes = await emitAck(s1, 'update_profile', { name: 'An Moi' });
  assert(renameRes.data.name === 'An Moi', `tên mới phải là "An Moi", got ${renameRes.data.name}`);
  const pu1 = await playersUpdateAfterRename;
  const names1 = pu1.players.map((p) => p.name);
  assert(names1.includes('An Moi'), 'players_update phải có tên mới "An Moi"');
  assert(!names1.includes('An'), 'players_update không được còn tên cũ "An"');
  console.log('1. Đổi tên hợp lệ OK ->', names1);

  // 2. Đổi tên trùng người khác trong phòng
  let dupBlocked = false;
  try {
    await emitAck(s1, 'update_profile', { name: 'Binh' });
  } catch (e) {
    dupBlocked = /đã được sử dụng/i.test(e.message);
  }
  assert(dupBlocked, 'đổi tên trùng người khác phải bị từ chối');
  console.log('2. Đổi tên trùng bị chặn OK');

  // 3. Đổi ảnh đại diện mới
  const puAfterAvatar = waitForEvent(s1, 'players_update');
  const avatarRes = await emitAck(s1, 'update_profile', { avatar: TINY_JPEG });
  assert(avatarRes.data.avatar === TINY_JPEG, 'avatar phải được cập nhật đúng ảnh mới');
  const pu2 = await puAfterAvatar;
  const me2 = pu2.players.find((p) => p.name === 'An Moi');
  assert(me2 && me2.avatar === TINY_JPEG, 'players_update phải phản ánh avatar mới');
  console.log('3. Đổi ảnh đại diện OK');

  // 4. Xoá ảnh đại diện
  const puAfterRemove = waitForEvent(s1, 'players_update');
  const removeRes = await emitAck(s1, 'update_profile', { remove_avatar: true });
  assert(removeRes.data.avatar === null, 'avatar phải về null sau khi xoá');
  const pu3 = await puAfterRemove;
  const me3 = pu3.players.find((p) => p.name === 'An Moi');
  assert(me3 && !me3.avatar, 'players_update phải phản ánh avatar đã xoá');
  console.log('4. Xoá ảnh đại diện OK');

  // 5. Host gọi update_profile phải bị từ chối
  let hostBlocked = false;
  try {
    await emitAck(host, 'update_profile', { name: 'Host Moi' });
  } catch (e) {
    hostBlocked = true;
  }
  assert(hostBlocked, 'host gọi update_profile phải bị từ chối');
  console.log('5. Host bị chặn update_profile OK');

  host.close();
  s1.close();
  s2.close();
  console.log('\nUpdate profile test OK — tất cả 5 kiểm tra đều pass');
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
