/**
 * Phase 4 automated tests — chạy: node scripts/phase4-test.js [--load]
 * Yêu cầu: docker compose up, DB seeded.
 */
const { io } = require('socket.io-client');

const WS_URL = process.env.WS_URL || 'http://localhost:38581';
const PHP_URL = process.env.PHP_URL || 'http://localhost:38480';
const GAME_ID = Number(process.env.GAME_ID || 1);
const RUN_LOAD = process.argv.includes('--load');
const LOAD_ROOMS = Number(process.env.LOAD_ROOMS || 10);
const LOAD_STUDENTS = Number(process.env.LOAD_STUDENTS || 50);

function waitForEvent(socket, event, timeoutMs = 15000) {
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
    socket.timeout(10000).emit(event, payload, (err, res) => {
      if (err) reject(err);
      else if (res && res.success === false) reject(new Error(res.error || `${event} failed`));
      else resolve(res);
    });
  });
}

function connectClient() {
  return new Promise((resolve, reject) => {
    const socket = io(WS_URL, { transports: ['websocket'], reconnection: false });
    socket.once('connect', () => resolve(socket));
    socket.once('connect_error', reject);
  });
}

async function laravelLogin() {
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

  return { cookie, xsrf };
}

async function apiJson(path, { cookie, xsrf }, options = {}) {
  const res = await fetch(`${PHP_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Cookie: cookie,
      'X-XSRF-TOKEN': xsrf,
      ...(options.headers || {}),
    },
  });
  const json = await res.json();
  if (!res.ok || json.success === false) {
    throw new Error(json.error || `API ${path} failed (${res.status})`);
  }
  return json.data;
}

async function createSession(auth) {
  const data = await apiJson('/api/game-sessions', auth, {
    method: 'POST',
    body: JSON.stringify({ game_id: GAME_ID }),
  });
  return { pin: data.pin, sessionId: (data.session && data.session.id) || data.session_id };
}

async function fetchQuestionBank(auth) {
  const { quizzes } = await apiJson(`/api/quizzes?game_id=${GAME_ID}`, auth);
  const active = (quizzes || []).filter((q) => q.is_active).sort((a, b) => a.sort_order - b.sort_order);
  const bank = [];
  for (const quiz of active) {
    const { questions } = await apiJson(`/api/quizzes/${quiz.id}/questions`, auth);
    for (const q of (questions || []).sort((a, b) => a.sort_order - b.sort_order)) {
      bank.push(q);
    }
  }
  return bank;
}

/**
 * @param {object} q question bank entry (unshuffled options, has correct_index)
 * @param {object} [liveQuestion] the new_question payload actually received —
 *   options may be shuffled per-student, so the correct index must be
 *   relocated by matching option text rather than reusing q.correct_index.
 */
function buildAnswer(q, liveQuestion) {
  switch (q.answer_type) {
    case 'mc': {
      const correctText = q.options[q.correct_index];
      const shuffledOptions = liveQuestion?.options || q.options;
      const idx = shuffledOptions.indexOf(correctText);
      return { index: idx === -1 ? q.correct_index : idx };
    }
    case 'essay':
      return { text: q.correct_answer_normalized };
    default:
      return null;
  }
}

async function joinRoom(socket, { pin, name, isHost = false }) {
  return new Promise((resolve, reject) => {
    socket.emit('join_room', { pin, name, is_host: isHost }, (res) => {
      if (res && res.success === false) reject(new Error(res.error));
      else resolve((res && res.data) || res);
    });
  });
}

async function testPlaythrough(auth) {
  console.log('\n=== 4.1 Full play-through ===');
  const bank = await fetchQuestionBank(auth);
  if (!bank.length) throw new Error('No questions in game');
  console.log(`Question bank: ${bank.length} câu`);

  const { pin, sessionId } = await createSession(auth);
  console.log('PIN:', pin, 'session:', sessionId);

  const host = await connectClient();
  const s1 = await connectClient();
  const s2 = await connectClient();
  const s3 = await connectClient();
  const students = [s1, s2, s3];
  const names = ['An', 'Binh', 'Chi'];

  await joinRoom(host, { pin, name: 'Host', isHost: true });
  await joinRoom(s1, { pin, name: 'An' });
  await joinRoom(s2, { pin, name: 'Binh' });
  await joinRoom(s3, { pin, name: 'Chi' });
  console.log('Host + 3 students joined');

  const gameStartedP = waitForEvent(s1, 'game_started');
  // Each student gets an independently shuffled options order for MC
  // questions, so every socket must capture its own new_question payload
  // (using only s1's would score s2/s3 against the wrong option order).
  let pendingQuestionPs = students.map((sock) => waitForEvent(sock, 'new_question'));
  await emitAck(host, 'host_start_game');
  await gameStartedP;

  let scores = { An: 0, Binh: 0, Chi: 0 };

  for (let i = 0; i < bank.length; i += 1) {
    const expected = bank[i];
    const liveQuestions = await Promise.all(pendingQuestionPs);
    liveQuestions.forEach((q, idx) => {
      if (String(q.question_id) !== String(expected.id)) {
        throw new Error(`Question mismatch at ${i + 1} for ${names[idx]}: got ${q.question_id}, expected ${expected.id}`);
      }
    });
    console.log(`  Q${i + 1}/${bank.length} id=${expected.id} type=${expected.answer_type}`);

    const submitPromises = students.map((sock, idx) => {
      const t0 = Date.now();
      const answer = buildAnswer(expected, liveQuestions[idx]);
      return new Promise((resolve, reject) => {
        sock.once('question_result', (result) => {
          resolve({ name: names[idx], result, ms: Date.now() - t0 });
        });
        sock.emit('submit_answer', {
          question_id: expected.id,
          answer,
          hybrid_timestamp: Date.now(),
        });
      });
    });

    // question_result only fires once the host finalizes — give submits a
    // beat to land in Redis, then finalize explicitly.
    await new Promise((r) => setTimeout(r, 150));
    await emitAck(host, 'host_finalize_question');
    const results = await Promise.all(submitPromises);
    for (const r of results) {
      if (!r.result.correct) {
        throw new Error(`${r.name} wrong on Q${i + 1}`);
      }
      scores[r.name] = r.result.total_score;
    }

    let gameEndedP = null;
    if (i === bank.length - 1) {
      gameEndedP = waitForEvent(s1, 'game_ended', 20000);
    }
    if (i < bank.length - 1) {
      pendingQuestionPs = students.map((sock) => waitForEvent(sock, 'new_question'));
    }

    const nextAck = await emitAck(host, 'host_next_question');
    if (nextAck && nextAck.data && nextAck.data.ended) {
      const ended = await gameEndedP;
      console.log('game_ended:', ended.final_leaderboard && ended.final_leaderboard.length, 'players');
      break;
    }
  }

  const session = await apiJson(`/api/reports/sessions/${sessionId}`, auth);
  const sessionStatus = (session.session && session.session.status) || session.status;
  if (sessionStatus !== 'ended') {
    throw new Error(`Session not ended in DB: ${sessionStatus}`);
  }

  host.close();
  s1.close();
  s2.close();
  s3.close();
  console.log('4.1 PASS — scores:', scores);
  return true;
}

async function testReconnect(auth) {
  console.log('\n=== 4.3 Reconnect ===');
  const bank = await fetchQuestionBank(auth);
  const { pin } = await createSession(auth);

  const host = await connectClient();
  let s1 = await connectClient();
  await joinRoom(host, { pin, name: 'Host', isHost: true });
  const firstJoin = await joinRoom(s1, { pin, name: 'Reconn' });
  if (firstJoin.reconnected) throw new Error('Expected first join, got reconnected');

  const gameStartedP = waitForEvent(s1, 'game_started');
  const firstQuestionP = waitForEvent(s1, 'new_question');
  await emitAck(host, 'host_start_game');
  await gameStartedP;
  const q1 = await firstQuestionP;
  const ans1 = buildAnswer(bank.find((q) => String(q.id) === String(q1.question_id)), q1);

  const result1P = new Promise((resolve, reject) => {
    s1.once('question_result', (result) => {
      if (!result.correct) reject(new Error('Reconnect Q1 wrong'));
      else resolve(result);
    });
  });
  s1.emit('submit_answer', {
    question_id: q1.question_id,
    answer: ans1,
    hybrid_timestamp: Date.now(),
  });
  await new Promise((r) => setTimeout(r, 150));
  await emitAck(host, 'host_finalize_question');
  const result1 = await result1P;
  const scoreBefore = result1.total_score;

  s1.close();
  await new Promise((r) => setTimeout(r, 300));

  s1 = await connectClient();
  const rejoin = await joinRoom(s1, { pin, name: 'Reconn' });
  if (!rejoin.reconnected) throw new Error('Expected reconnected=true');
  if (rejoin.score < scoreBefore) {
    throw new Error(`Score lost after reconnect: had ${scoreBefore}, got ${rejoin.score}`);
  }

  console.log('4.3 PASS — reconnected, score:', rejoin.score);
  host.close();
  s1.close();
  return true;
}

async function testGuards(auth) {
  console.log('\n=== 4.4 Resubmit & clock skew ===');
  const bank = await fetchQuestionBank(auth);
  const { pin } = await createSession(auth);

  const host = await connectClient();
  const s1 = await connectClient();
  const s2 = await connectClient();
  await joinRoom(host, { pin, name: 'Host', isHost: true });
  await joinRoom(s1, { pin, name: 'An' });
  await joinRoom(s2, { pin, name: 'Binh' });

  const gameStartedP = waitForEvent(s1, 'game_started');
  const firstQuestionP = waitForEvent(s1, 'new_question');
  await emitAck(host, 'host_start_game');
  await gameStartedP;
  const q = await firstQuestionP;
  const expected = bank.find((x) => String(x.id) === String(q.question_id));
  const answer = buildAnswer(expected, q);

  await emitAck(s1, 'submit_answer', {
    question_id: q.question_id,
    answer,
    hybrid_timestamp: Date.now(),
  });

  // Students can change their answer before the host finalizes (student.js
  // shows a "Cập nhật đáp án" button) — resubmit must succeed, not error.
  let resubmitAllowed = false;
  try {
    const ack = await emitAck(s1, 'submit_answer', {
      question_id: q.question_id,
      answer,
      hybrid_timestamp: Date.now(),
    });
    resubmitAllowed = Boolean(ack?.data?.can_change);
  } catch {
    resubmitAllowed = false;
  }

  let skewBlocked = false;
  try {
    await emitAck(s2, 'submit_answer', {
      question_id: q.question_id,
      answer,
      hybrid_timestamp: Date.now() + 2000,
    });
  } catch (e) {
    skewBlocked = /lệch/i.test(e.message);
  }

  const resultP = waitForEvent(s1, 'question_result');
  await emitAck(host, 'host_finalize_question');
  await resultP;

  if (!resubmitAllowed) throw new Error('Resubmit before finalize should have been allowed');
  if (!skewBlocked) throw new Error('Clock skew was not blocked');

  host.close();
  s1.close();
  s2.close();
  console.log('4.4 PASS — resubmit allowed, skew blocked');
  return true;
}

async function testLoad(auth) {
  console.log(`\n=== 4.2 Load test (${LOAD_ROOMS} rooms × ${LOAD_STUDENTS} students) ===`);
  const latencies = [];
  let dropped = 0;

  for (let r = 0; r < LOAD_ROOMS; r += 1) {
    const { pin } = await createSession(auth);
    const host = await connectClient();
    await joinRoom(host, { pin, name: `Host${r}`, isHost: true });

    const students = [];
    for (let i = 0; i < LOAD_STUDENTS; i += 1) {
      try {
        const s = await connectClient();
        await joinRoom(s, { pin, name: `S${r}_${i}` });
        students.push(s);
      } catch {
        dropped += 1;
      }
    }

    const firstQuestionP = waitForEvent(students[0], 'new_question', 20000);
    await emitAck(host, 'host_start_game');
    const q = await firstQuestionP;

    const t0 = Date.now();
    const resultPromises = students.map(
      (s) =>
        new Promise((resolve) => {
          s.once('question_result', () => resolve());
          s.emit('submit_answer', {
            question_id: q.question_id,
            answer: { index: 0 },
            hybrid_timestamp: Date.now(),
          });
        })
    );
    // question_result only fires once the host finalizes — measured latency
    // now covers the full submit-burst + finalize + fan-out cycle.
    await new Promise((r) => setTimeout(r, 200));
    await emitAck(host, 'host_finalize_question');
    await Promise.all(resultPromises);
    const elapsed = Date.now() - t0;
    latencies.push(elapsed);

    host.close();
    students.forEach((s) => s.close());
    process.stdout.write(`  room ${r + 1}/${LOAD_ROOMS} submit burst ${elapsed}ms\r`);
  }

  latencies.sort((a, b) => a - b);
  const p99 = latencies[Math.floor(latencies.length * 0.99)] || latencies[latencies.length - 1];
  console.log(`\nLoad: dropped joins=${dropped}, p99 burst=${p99}ms, max=${latencies[latencies.length - 1]}ms`);

  if (dropped > 0) throw new Error(`${dropped} clients failed to join`);
  if (p99 > 2000) console.warn(`WARN: p99 ${p99}ms > 2000ms (local threshold)`);
  console.log('4.2 PASS');
  return true;
}

async function main() {
  console.log('Phase 4 tests — PHP:', PHP_URL, 'WS:', WS_URL);
  const auth = await laravelLogin();

  await testGuards(auth);
  await testReconnect(auth);
  await testPlaythrough(auth);

  if (RUN_LOAD) {
    await testLoad(auth);
  } else {
    console.log('\n(Skipping 4.2 load — chạy với --load để bật)');
  }

  console.log('\n✓ Phase 4 automated tests passed');
}

main().catch((err) => {
  console.error('\n✗ FAILED:', err.message);
  process.exit(1);
});
