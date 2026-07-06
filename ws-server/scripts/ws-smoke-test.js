/**
 * Smoke test Phase 2 — chạy: node scripts/ws-smoke-test.js
 * Yêu cầu: docker compose up, đã seed DB.
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

async function createSessionPin() {
  const jar = {};
  const loginPage = await fetch(`${PHP_URL}/login`);
  const html = await loginPage.text();
  const token = (html.match(/name="_token" value="([^"]+)"/) || [])[1];
  if (!token) throw new Error('CSRF token not found');

  const pageCookies = loginPage.headers.getSetCookie
    ? loginPage.headers.getSetCookie().map((c) => c.split(';')[0])
    : [];
  const pageCookie = pageCookies.join('; ');

  const loginRes = await fetch(`${PHP_URL}/login`, {
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

async function main() {
  console.log('Creating session via Laravel...');
  const pin = await createSessionPin();
  console.log('PIN:', pin);

  const host = io(WS_URL, { transports: ['websocket'] });
  const s1 = io(WS_URL, { transports: ['websocket'] });
  const s2 = io(WS_URL, { transports: ['websocket'] });

  await Promise.all([
    waitForEvent(host, 'connect'),
    waitForEvent(s1, 'connect'),
    waitForEvent(s2, 'connect'),
  ]);

  host.emit('join_room', { pin, name: 'Host', is_host: true });
  await waitForEvent(host, 'room_joined');
  console.log('Host joined');

  s1.emit('join_room', { pin, name: 'An' });
  await waitForEvent(s1, 'room_joined');
  s2.emit('join_room', { pin, name: 'Binh' });
  await waitForEvent(s2, 'room_joined');
  console.log('Students joined');

  const gameStartedP = waitForEvent(s1, 'game_started');
  const firstQuestionP = waitForEvent(s1, 'new_question');
  await emitAck(host, 'host_start_game');
  await gameStartedP;
  const q = await firstQuestionP;
  console.log('new_question id:', q.question_id, 'type:', q.answer_type);

  const hybridTs = Date.now();
  const submitCountP = waitForEvent(host, 'submit_count_update');
  s1.emit('submit_answer', {
    question_id: q.question_id,
    answer: 0,
    hybrid_timestamp: hybridTs,
  });
  const result = await waitForEvent(s1, 'question_result');
  console.log('question_result:', result);

  const submitCount = await submitCountP;
  console.log('submit_count_update:', submitCount);

  // Double submit should error
  let doubleBlocked = false;
  try {
    await emitAck(s1, 'submit_answer', {
      question_id: q.question_id,
      answer: 0,
      hybrid_timestamp: Date.now(),
    });
  } catch (e) {
    doubleBlocked = e.message.includes('đã nộp');
  }
  console.log('double submit blocked:', doubleBlocked);

  // Clock skew
  let skewBlocked = false;
  try {
    await emitAck(s2, 'submit_answer', {
      question_id: q.question_id,
      answer: 0,
      hybrid_timestamp: Date.now() + 2000,
    });
  } catch (e) {
    skewBlocked = e.message.includes('lệch');
  }
  console.log('clock skew blocked:', skewBlocked);

  host.close();
  s1.close();
  s2.close();
  console.log('Phase 2 smoke test OK');
}

main().catch((err) => {
  console.error('FAILED:', err.message);
  process.exit(1);
});
