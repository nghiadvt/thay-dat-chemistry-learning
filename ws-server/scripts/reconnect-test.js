/**
 * Test luồng mất mạng / vào lại phòng — chạy: node scripts/reconnect-test.js
 * Yêu cầu: docker compose up, đã seed DB (teacher@hoadat.local).
 *
 * Kiểm tra:
 *  1. new_question mang question_index / question_count (đánh số toàn cục).
 *  2. HS nộp bài rồi rớt mạng trước khi chốt câu → vẫn được chấm điểm.
 *  3. HS vào lại giữa câu với player_token → reconnected + giữ điểm, nhận lại câu hiện tại.
 *  4. HS rớt mạng, phòng kết thúc → vào lại vẫn được (nhận game_ended + BXH cuối).
 *  5. HS lạ (chưa từng chơi) vào phòng đã kết thúc → bị từ chối.
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
  const pageCookie = pageCookies.join('; ');

  const loginRes = await fetch(`${PHP_URL}/admin/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Cookie: pageCookie,
    },
    body: `email=teacher@hoadat.local&password=password123&_token=${token}`,
    redirect: 'manual',
  });
  if (loginRes.status !== 302 && loginRes.status !== 200) {
    throw new Error(`Login failed with status ${loginRes.status}`);
  }
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
  console.log('Creating session via Laravel...');
  const pin = await createSessionPin();
  console.log('PIN:', pin);

  const host = await connectSocket();
  let s1 = await connectSocket();
  const s2 = await connectSocket();

  host.emit('join_room', { pin, name: 'Host', is_host: true });
  await waitForEvent(host, 'room_joined');

  s1.emit('join_room', { pin, name: 'An' });
  const anJoin = await waitForEvent(s1, 'room_joined');
  const anToken = anJoin.player_token;
  assert(anToken, 'player_token phải được cấp khi join');
  s2.emit('join_room', { pin, name: 'Binh' });
  await waitForEvent(s2, 'room_joined');
  console.log('Host + 2 students joined');

  // ── 1. new_question có chỉ số toàn cục ──
  const q1P = waitForEvent(s1, 'new_question');
  await emitAck(host, 'host_start_game');
  const q1 = await q1P;
  assert(q1.question_index === 0, `question_index đầu tiên phải là 0 (got ${q1.question_index})`);
  assert(Number(q1.question_count) >= 1, 'question_count phải có trong payload');
  console.log(`1. new_question OK — question_index=${q1.question_index}, question_count=${q1.question_count}`);

  // ── 2. An nộp bài rồi rớt mạng trước khi host chốt câu ──
  await emitAck(s1, 'submit_answer', {
    question_id: q1.question_id,
    answer: 0,
    hybrid_timestamp: Date.now(),
  });
  s1.close(); // mất mạng
  await new Promise((r) => setTimeout(r, 500));

  const binhResultP = waitForEvent(s2, 'question_result');
  await emitAck(host, 'host_finalize_question');
  await binhResultP;
  console.log('2. Finalize xong khi An offline');

  // ── 3. An vào lại với token → reconnected + điểm được giữ ──
  s1 = await connectSocket();
  s1.emit('join_room', { pin, name: 'An', player_token: anToken });
  const rejoin = await waitForEvent(s1, 'room_joined');
  assert(rejoin.reconnected === true, 'rejoin phải có reconnected=true');
  assert(Number(rejoin.score) > 0, `điểm của An phải được giữ dù offline lúc chốt câu (got ${rejoin.score})`);
  console.log(`3. An rejoin OK — reconnected=${rejoin.reconnected}, score=${rejoin.score}`);

  // Câu tiếp theo phải đến được An sau khi rejoin, với chỉ số đúng
  const q2P = waitForEvent(s1, 'new_question');
  await emitAck(host, 'host_next_question');
  const q2 = await q2P;
  assert(q2.question_index === 1, `câu 2 phải có question_index=1 (got ${q2.question_index})`);
  console.log('   An nhận được câu tiếp theo sau rejoin, question_index=1');

  // ── 4. An rớt mạng → host kết thúc game → An vào lại xem kết quả ──
  s1.close();
  const endP = waitForEvent(s2, 'game_ended');
  await emitAck(host, 'host_end_game');
  await endP;

  s1 = await connectSocket();
  const gameEndedP = waitForEvent(s1, 'game_ended');
  s1.emit('join_room', { pin, name: 'An', player_token: anToken });
  const endedJoin = await waitForEvent(s1, 'room_joined');
  assert(endedJoin.room_status === 'ended', 'room_status phải là ended');
  const ended = await gameEndedP;
  assert(Array.isArray(ended.final_leaderboard) && ended.final_leaderboard.length >= 1,
    'game_ended gửi lại phải kèm final_leaderboard');
  console.log(`4. An rejoin phòng ended OK — nhận final_leaderboard (${ended.final_leaderboard.length} người)`);

  // ── 5. HS lạ không được vào phòng đã kết thúc ──
  const stranger = await connectSocket();
  let strangerBlocked = false;
  try {
    await emitAck(stranger, 'join_room', { pin, name: 'NguoiLa' });
  } catch (e) {
    strangerBlocked = /kết thúc/i.test(e.message);
  }
  assert(strangerBlocked, 'HS chưa từng chơi phải bị chặn khi phòng đã kết thúc');
  console.log('5. HS lạ bị chặn vào phòng ended OK');

  host.close();
  s1.close();
  s2.close();
  stranger.close();
  console.log('\nReconnect test OK — tất cả 5 kiểm tra đều pass');
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
