const {
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
  answerKey,
} = require('./redis-keys');
const {
  getSessionByPin,
  updateSessionStatus,
  getGameQuizzes,
  getQuizById,
  getQuizQuestions,
  getQuestionById,
  getKeyboardConfig,
  saveSessionAnswer,
  saveGameResults,
} = require('./db');
const { validateHybridTimestamp } = require('./ntp');
const { checkAnswer, calculateScore } = require('./scoring');
const {
  getConnectedStudents,
  getHostSockets,
  refreshRoomTtl,
  getPlayer,
  savePlayer,
} = require('./room');

const duckRace = require('./engines/duck-race');

function registerGameplayHandlers(io, redis) {
  io.on('connection', (socket) => {
    socket.on('host_start_game', async (_payload, ack) => {
      try {
        await requireHost(socket);
        const pin = socket.data.pin;
        const room = await redis.hgetall(roomKey(pin));
        const result = duckRace.isDuckRaceRoom(room)
          ? await duckRace.startGame(io, redis, socket)
          : await startGame(io, redis, socket);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không thể bắt đầu game.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('host_finalize_question', async (_payload, ack) => {
      try {
        await requireHost(socket);
        const pin = socket.data.pin;
        await finalizeCurrentQuestion(io, redis, pin);
        if (typeof ack === 'function') ack({ success: true });
      } catch (err) {
        const message = err.message || 'Không thể chốt câu hỏi.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('host_next_question', async (_payload, ack) => {
      try {
        await requireHost(socket);
        const result = await advanceQuestion(io, redis, socket.data.pin);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không thể chuyển câu.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('host_end_game', async (_payload, ack) => {
      try {
        await requireHost(socket);
        const pin = socket.data.pin;
        const room = await redis.hgetall(roomKey(pin));
        if (duckRace.isDuckRaceRoom(room)) {
          await duckRace.endGame(io, redis, pin);
        } else {
          if (room.status === 'playing' && room.current_question_id) {
            await finalizeCurrentQuestion(io, redis, pin);
          }
          await endGame(io, redis, pin);
        }
        if (typeof ack === 'function') ack({ success: true });
      } catch (err) {
        const message = err.message || 'Không thể kết thúc game.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('submit_answer', async (payload = {}, ack) => {
      try {
        const pin = socket.data?.pin;
        const room = pin ? await redis.hgetall(roomKey(pin)) : {};
        const result = duckRace.isDuckRaceRoom(room)
          ? await duckRace.handleSubmit(io, redis, socket, payload)
          : await handleSubmitAnswer(io, redis, socket, payload);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không thể nộp đáp án.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });
  });
}

function requireHost(socket) {
  if (!socket.data?.isHost || !socket.data?.pin) {
    throw new Error('Chỉ host mới được thực hiện thao tác này.');
  }
}

async function loadGamePlan(gameId, quizId = null) {
  const quizzes = quizId
    ? [await getQuizById(quizId)].filter(Boolean)
    : await getGameQuizzes(gameId);
  const plan = [];

  for (const quiz of quizzes) {
    const questions = await getQuizQuestions(quiz.id);
    if (questions.length === 0) continue;
    const keyboardConfig = await getKeyboardConfig(quiz.keyboard_id);
    plan.push({
      quiz_id: quiz.id,
      show_explanation: Boolean(Number(quiz.show_explanation)),
      shuffle_options: Boolean(Number(quiz.shuffle_options)),
      keyboard_id: quiz.keyboard_id,
      keyboard_config: keyboardConfig,
      questions,
    });
  }

  return plan;
}

async function startGame(io, redis, socket) {
  const pin = socket.data.pin;
  const room = await redis.hgetall(roomKey(pin));

  if (room.status === 'playing') {
    throw new Error('Game đã bắt đầu.');
  }
  if (room.status === 'ended') {
    throw new Error('Game đã kết thúc.');
  }

  const session = await getSessionByPin(pin);
  if (!session) {
    throw new Error('Session không tồn tại.');
  }
  if (!Number(session.is_active)) {
    throw new Error('Phòng đã bị tắt.');
  }

  const quizId = room.quiz_id ? Number(room.quiz_id) : (session.quiz_id ? Number(session.quiz_id) : null);
  const plan = await loadGamePlan(room.game_id, quizId);
  if (plan.length === 0) {
    throw new Error('Game không có câu hỏi.');
  }

  await redis.set(`room:${pin}:plan`, JSON.stringify(plan), 'EX', 7200);

  const now = new Date();
  await updateSessionStatus(pin, 'playing', { started_at: now });

  await redis.hset(roomKey(pin), {
    status: 'playing',
    session_id: String(session.id),
    quiz_index: '0',
    question_index: '0',
  });
  await refreshRoomTtl(redis, pin);

  io.to(pin).emit('game_started', {});

  return emitCurrentQuestion(io, redis, pin);
}

function finalizedKey(pin, questionId) {
  return `room:${pin}:finalized:${questionId}`;
}

async function advanceQuestion(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing') {
    throw new Error('Game chưa bắt đầu.');
  }

  const currentQuestionId = room.current_question_id;
  if (currentQuestionId) {
    const done = await redis.get(finalizedKey(pin, currentQuestionId));
    if (!done) {
      await finalizeCurrentQuestion(io, redis, pin);
    }
  }

  const plan = await getPlan(redis, pin);
  let quizIndex = Number(room.quiz_index || 0);
  let questionIndex = Number(room.question_index || 0) + 1;

  if (questionIndex >= plan[quizIndex].questions.length) {
    quizIndex += 1;
    questionIndex = 0;
  }

  if (quizIndex >= plan.length) {
    await endGame(io, redis, pin);
    return { ended: true };
  }

  await redis.hset(roomKey(pin), {
    quiz_index: String(quizIndex),
    question_index: String(questionIndex),
  });

  return emitCurrentQuestion(io, redis, pin);
}

function buildStudentQuestionPayload(quiz, question, serverTime) {
  return {
    quiz_id: quiz.quiz_id,
    question_id: question.id,
    content: question.content,
    answer_type: question.answer_type,
    options: question.answer_type === 'mc' ? question.options : null,
    template: question.answer_type === 'structured' ? question.template : null,
    input_mode: question.input_mode || null,
    keyboard_config: quiz.keyboard_config,
    time_limit: question.time_limit_seconds,
    server_time: serverTime,
  };
}

function shouldShuffleOptions(quiz, question) {
  return question.answer_type === 'mc'
    && quiz.shuffle_options
    && Array.isArray(question.options)
    && question.options.length > 1;
}

async function getOrCreateOptionOrder(redis, pin, question, studentName) {
  const key = optionOrderKey(pin, question.id, studentName);
  const existing = await redis.get(key);
  if (existing) {
    return JSON.parse(existing);
  }
  const order = shuffleIndices(question.options.length);
  await redis.set(key, JSON.stringify(order), 'EX', 7200);
  return order;
}

async function emitNewQuestionToStudentSocket(io, redis, pin, socket, quiz, question, startedAt) {
  const studentPayload = buildStudentQuestionPayload(quiz, question, startedAt);

  if (shouldShuffleOptions(quiz, question)) {
    const order = await getOrCreateOptionOrder(redis, pin, question, socket.data.name);
    socket.emit('new_question', {
      ...studentPayload,
      options: order.map((originalIndex) => question.options[originalIndex]),
    });
    return;
  }

  socket.emit('new_question', studentPayload);
}

/** HS join sau khi game đã bắt đầu — đồng bộ câu hiện tại + timer theo question_started_at. */
async function syncLateJoinStudent(io, redis, socket, pin) {
  if (socket.data?.isHost) return;

  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing') return;

  if (duckRace.isDuckRaceRoom(room)) {
    await duckRace.syncLateJoin(io, redis, socket, pin);
    return;
  }

  if (!room.current_question_id) return;

  const plan = await getPlan(redis, pin);
  const quizIndex = Number(room.quiz_index || 0);
  const questionIndex = Number(room.question_index || 0);
  const quiz = plan[quizIndex];
  const question = quiz.questions[questionIndex];
  const startedAt = Number(room.question_started_at);
  const finalized = await redis.get(finalizedKey(pin, question.id));

  socket.emit('game_started', {});
  if (finalized) {
    // Giữa các câu (đã finalize) — chờ new_question tiếp theo.
    return;
  }

  await emitNewQuestionToStudentSocket(io, redis, pin, socket, quiz, question, startedAt);
  await emitSubmitCount(io, redis, pin, question.id);
}

async function emitCurrentQuestion(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  const plan = await getPlan(redis, pin);
  const quizIndex = Number(room.quiz_index || 0);
  const questionIndex = Number(room.question_index || 0);
  const quiz = plan[quizIndex];
  const question = quiz.questions[questionIndex];
  const startedAt = Date.now();

  await redis.hset(roomKey(pin), {
    current_quiz_id: String(quiz.quiz_id),
    current_question_id: String(question.id),
    question_started_at: String(startedAt),
  });
  await redis.del(submittedKey(pin, question.id));
  await redis.del(finalizedKey(pin, question.id));
  await refreshRoomTtl(redis, pin);
  await redis.expire(submittedKey(pin, question.id), 7200);

  const studentPayload = buildStudentQuestionPayload(quiz, question, startedAt);

  const hostPayload = {
    ...studentPayload,
    correct_index: question.correct_index,
    correct_answer_normalized: question.correct_answer_normalized,
    correct_answer: question.correct_answer,
    explanation: question.explanation,
  };

  if (shouldShuffleOptions(quiz, question)) {
    const studentSockets = await getStudentSockets(io, pin);
    for (const studentSocket of studentSockets) {
      const order = shuffleIndices(question.options.length);
      await redis.set(
        optionOrderKey(pin, question.id, studentSocket.data.name),
        JSON.stringify(order),
        'EX',
        7200,
      );
      studentSocket.emit('new_question', {
        ...studentPayload,
        options: order.map((originalIndex) => question.options[originalIndex]),
      });
    }

    const hostSockets = await getHostSockets(io, pin);
    for (const hostSocket of hostSockets) {
      hostSocket.emit('new_question', hostPayload);
    }
  } else {
    const studentSockets = await getStudentSockets(io, pin);
    const hostSockets = await getHostSockets(io, pin);
    studentSockets.forEach((studentSocket) => {
      studentSocket.emit('new_question', studentPayload);
    });
    hostSockets.forEach((hostSocket) => {
      hostSocket.emit('new_question', hostPayload);
    });
  }

  await emitSubmitCount(io, redis, pin, question.id);

  return { question_id: question.id, quiz_id: quiz.quiz_id };
}

async function handleSubmitAnswer(io, redis, socket, payload) {
  const pin = socket.data?.pin;
  const name = socket.data?.name;

  if (!pin || !name || socket.data?.isHost) {
    throw new Error('Chỉ học sinh mới được nộp đáp án.');
  }

  const { question_id: questionId, answer, hybrid_timestamp: hybridTimestamp } = payload;

  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing') {
    throw new Error('Game chưa bắt đầu.');
  }
  if (String(room.current_question_id) !== String(questionId)) {
    throw new Error('Không phải câu hỏi hiện tại.');
  }

  const tsCheck = validateHybridTimestamp(hybridTimestamp);
  if (!tsCheck.ok) {
    throw new Error(tsCheck.message);
  }

  const question = await getQuestionById(questionId);
  if (!question) {
    throw new Error('Câu hỏi không tồn tại.');
  }

  const startedAt = Number(room.question_started_at);
  const key = answerKey(pin, questionId, name);
  const existingRaw = await redis.get(key);
  let firstSubmitAt = Number(hybridTimestamp);

  if (existingRaw) {
    try {
      const existing = JSON.parse(existingRaw);
      if (existing.first_submit_at) firstSubmitAt = Number(existing.first_submit_at);
    } catch {
      /* keep new timestamp */
    }
  } else {
    await redis.sadd(submittedKey(pin, questionId), name);
    await redis.expire(submittedKey(pin, questionId), 7200);
  }

  await redis.set(
    key,
    JSON.stringify({
      answer,
      first_submit_at: firstSubmitAt,
      last_submit_at: Number(hybridTimestamp),
    }),
    'EX',
    7200,
  );
  await refreshRoomTtl(redis, pin);

  await emitSubmitCount(io, redis, pin, questionId);

  const lastSubmitAt = Number(hybridTimestamp);
  const displayElapsedSeconds = Math.max(0, Math.ceil((lastSubmitAt - startedAt) / 1000));
  const answerDisplay = formatAnswerDisplay(question, answer);

  return {
    locked: true,
    elapsed_seconds: displayElapsedSeconds,
    answer_display: answerDisplay,
    can_change: true,
  };
}

async function finalizeCurrentQuestion(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  const questionId = room.current_question_id;
  if (!questionId) return;

  const question = await getQuestionById(questionId);
  if (!question) return;

  const plan = await getPlan(redis, pin);
  const quizIndex = Number(room.quiz_index || 0);
  const quiz = plan[quizIndex];
  const startedAt = Number(room.question_started_at);
  const timeLimitSeconds = question.time_limit_seconds;
  const studentSockets = await getStudentSockets(io, pin);
  const scored = [];

  for (const studentSocket of studentSockets) {
    const name = studentSocket.data.name;
    const raw = await redis.get(answerKey(pin, questionId, name));
    let answer = null;
    let firstSubmitAt = startedAt + timeLimitSeconds * 1000;

    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        answer = parsed.answer;
        firstSubmitAt = Number(parsed.first_submit_at || parsed.last_submit_at || firstSubmitAt);
      } catch {
        answer = null;
      }
    }

    const answerForScoring = await mapShuffledAnswer(redis, pin, questionId, name, question, answer);
    const { correct, correctAnswer } = checkAnswer(question, answerForScoring);
    const player = await getPlayer(redis, pin, name);
    const streakBefore = player?.streak_correct || 0;

    const { scoreEarned, streakAfter } = calculateScore({
      timeLimitSeconds,
      questionStartedAt: startedAt,
      hybridTimestamp: firstSubmitAt,
      correct,
      streakBefore,
    });

    if (player) {
      player.streak_correct = streakAfter;
      await savePlayer(redis, pin, player);
    }

    if (scoreEarned > 0) {
      await redis.zincrby(leaderboardKey(pin), scoreEarned, name);
    }

    const sessionId = Number(room.session_id);
    if (sessionId && raw) {
      await saveSessionAnswer({
        sessionId,
        questionId: question.id,
        studentName: name,
        answerSubmitted: answer,
        isCorrect: correct,
        scoreEarned,
        answeredAt: new Date(firstSubmitAt),
      });
    }

    const elapsedSeconds = Math.max(0, Math.ceil((firstSubmitAt - startedAt) / 1000));
    const totalScore = Number(await redis.zscore(leaderboardKey(pin), name)) || 0;

    scored.push({
      name,
      socket: studentSocket,
      correct,
      correctAnswer,
      scoreEarned,
      totalScore,
      elapsedSeconds,
      answerDisplay: formatAnswerDisplay(question, answer),
      myAnswer: answer,
    });
  }

  await refreshRoomTtl(redis, pin);

  const ranking = buildQuestionRanking(scored);
  const fastestCorrect = ranking.find((entry) => entry.correct) || null;

  for (const entry of ranking) {
    const rank = await getPlayerRank(redis, pin, entry.name);
    const correctRank = entry.correct
      ? ranking.filter((r) => r.correct).findIndex((r) => r.name === entry.name) + 1
      : null;

    entry.socket.emit('question_result', {
      correct: entry.correct,
      correct_answer: entry.correctAnswer,
      score_earned: entry.scoreEarned,
      rank,
      total_score: entry.totalScore,
      elapsed_seconds: entry.elapsedSeconds,
      my_answer: entry.answerDisplay,
      question_rank_correct: correctRank,
      question_total: ranking.length,
      fastest_correct: fastestCorrect
        ? {
            name: fastestCorrect.name,
            elapsed_seconds: fastestCorrect.elapsedSeconds,
          }
        : null,
      ...(quiz.show_explanation && question.explanation
        ? { explanation: question.explanation }
        : {}),
    });
  }

  await emitLeaderboard(io, redis, pin);
  await redis.set(finalizedKey(pin, questionId), '1', 'EX', 7200);
  await redis.del(submittedKey(pin, questionId));
}

function formatAnswerDisplay(question, answer) {
  if (answer == null) return '—';

  if (question.answer_type === 'mc') {
    const submitted = typeof answer === 'object' && answer !== null && 'index' in answer
      ? answer.index
      : answer;
    const idx = Number(submitted);
    if (!Number.isFinite(idx) || idx < 0) return '—';
    const labels = ['A', 'B', 'C', 'D', 'E', 'F'];
    const label = labels[idx] || String(idx + 1);
    const text = question.options?.[idx] || '';
    return text ? `${label}. ${text}` : label;
  }

  if (typeof answer === 'object' && answer !== null && 'text' in answer) {
    return String(answer.text || '—');
  }

  if (typeof answer === 'string') return answer || '—';
  return '—';
}

function buildQuestionRanking(scored) {
  const correct = scored
    .filter((entry) => entry.correct)
    .sort((a, b) => a.elapsedSeconds - b.elapsedSeconds || a.name.localeCompare(b.name));
  const wrong = scored
    .filter((entry) => !entry.correct)
    .sort((a, b) => a.elapsedSeconds - b.elapsedSeconds || a.name.localeCompare(b.name));
  return [...correct, ...wrong];
}

async function getPlayerRank(redis, pin, name) {
  const top = await redis.zrevrange(leaderboardKey(pin), 0, -1);
  const index = top.indexOf(name);
  return index === -1 ? top.length + 1 : index + 1;
}

async function emitLeaderboard(io, redis, pin) {
  const prevKey = `room:${pin}:prev_scores`;
  const prevRaw = await redis.hgetall(prevKey);
  const entries = await redis.zrevrange(leaderboardKey(pin), 0, 4, 'WITHSCORES');
  const allPlayers = await redis.hgetall(playersKey(pin));

  const top5 = [];
  for (let i = 0; i < entries.length; i += 2) {
    const playerName = entries[i];
    const score = Number(entries[i + 1]);
    const prev = Number(prevRaw[playerName] || 0);
    let avatar = null;
    try {
      const playerRaw = allPlayers[playerName];
      if (playerRaw) avatar = JSON.parse(playerRaw).avatar || null;
    } catch {
      avatar = null;
    }
    top5.push({
      name: playerName,
      score,
      delta: score - prev,
      avatar,
    });
    await redis.hset(prevKey, playerName, String(score));
  }
  await redis.expire(prevKey, 7200);

  io.to(pin).emit('leaderboard_update', { top5 });
}

async function emitSubmitCount(io, redis, pin, questionId) {
  const submitted = await redis.scard(submittedKey(pin, questionId));
  const students = await getConnectedStudents(redis, pin);
  const payload = { submitted, total: students.length };

  const hostSockets = await getHostSockets(io, pin);
  for (const hostSocket of hostSockets) {
    hostSocket.emit('submit_count_update', payload);
  }
}

async function endGame(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status === 'ended') return;

  const session = await getSessionByPin(pin);
  const entries = await redis.zrevrange(leaderboardKey(pin), 0, -1, 'WITHSCORES');
  const finalLeaderboard = [];
  const allPlayers = await redis.hgetall(playersKey(pin));

  for (let i = 0; i < entries.length; i += 2) {
    const playerName = entries[i];
    const score = Number(entries[i + 1]);
    const playerRaw = allPlayers[playerName];
    const player = playerRaw ? JSON.parse(playerRaw) : { name: playerName };
    finalLeaderboard.push({
      name: playerName,
      score,
      rank: finalLeaderboard.length + 1,
      player_token: player.player_token || null,
      avatar: player.avatar || null,
    });
  }

  if (session) {
    await updateSessionStatus(pin, 'ended', { ended_at: new Date() });
    await saveGameResults(session.id, finalLeaderboard);
  }

  await redis.hset(roomKey(pin), { status: 'ended' });
  await refreshRoomTtl(redis, pin);

  io.to(pin).emit('game_ended', { final_leaderboard: finalLeaderboard });
}

async function getPlan(redis, pin) {
  const raw = await redis.get(`room:${pin}:plan`);
  if (!raw) {
    throw new Error('Game plan chưa được khởi tạo. Host cần bắt đầu game.');
  }
  return JSON.parse(raw);
}

function optionOrderKey(pin, questionId, studentName) {
  return `room:${pin}:option_order:${questionId}:${studentName}`;
}

function shuffleIndices(length) {
  const indices = Array.from({ length }, (_, index) => index);
  for (let i = indices.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [indices[i], indices[j]] = [indices[j], indices[i]];
  }
  return indices;
}

async function getStudentSockets(io, pin) {
  const sockets = await io.in(pin).fetchSockets();
  return sockets.filter((socket) => !socket.data?.isHost);
}

async function mapShuffledAnswer(redis, pin, questionId, studentName, question, answer) {
  if (question.answer_type !== 'mc') {
    return answer;
  }
  if (answer == null) {
    return { index: -1 };
  }

  const orderRaw = await redis.get(optionOrderKey(pin, questionId, studentName));
  if (!orderRaw) {
    return answer;
  }

  const order = JSON.parse(orderRaw);
  const submitted = typeof answer === 'object' && answer !== null && 'index' in answer
    ? answer.index
    : answer;
  const originalIndex = order[Number(submitted)];

  if (typeof answer === 'object' && answer !== null) {
    return { ...answer, index: originalIndex };
  }

  return originalIndex;
}

module.exports = {
  registerGameplayHandlers,
  syncLateJoinStudent,
};
