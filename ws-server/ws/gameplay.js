const {
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
} = require('./redis-keys');
const {
  getSessionByPin,
  updateSessionStatus,
  getGameQuizzes,
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

function registerGameplayHandlers(io, redis) {
  io.on('connection', (socket) => {
    socket.on('host_start_game', async (_payload, ack) => {
      try {
        await requireHost(socket);
        const result = await startGame(io, redis, socket);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không thể bắt đầu game.';
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
        await endGame(io, redis, socket.data.pin);
        if (typeof ack === 'function') ack({ success: true });
      } catch (err) {
        const message = err.message || 'Không thể kết thúc game.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('submit_answer', async (payload = {}, ack) => {
      try {
        const result = await handleSubmitAnswer(io, redis, socket, payload);
        socket.emit('question_result', result);
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

async function loadGamePlan(gameId) {
  const quizzes = await getGameQuizzes(gameId);
  const plan = [];

  for (const quiz of quizzes) {
    const questions = await getQuizQuestions(quiz.id);
    if (questions.length === 0) continue;
    const keyboardConfig = await getKeyboardConfig(quiz.keyboard_id);
    plan.push({
      quiz_id: quiz.id,
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

  const plan = await loadGamePlan(room.game_id);
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

async function advanceQuestion(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing') {
    throw new Error('Game chưa bắt đầu.');
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
  await refreshRoomTtl(redis, pin);
  await redis.expire(submittedKey(pin, question.id), 7200);

  const payload = {
    quiz_id: quiz.quiz_id,
    question_id: question.id,
    content: question.content,
    answer_type: question.answer_type,
    options: question.answer_type === 'mc' ? question.options : null,
    keyboard_config: quiz.keyboard_config,
    time_limit: question.time_limit_seconds,
    server_time: startedAt,
  };

  io.to(pin).emit('new_question', payload);
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

  const added = await redis.sadd(submittedKey(pin, questionId), name);
  if (added === 0) {
    throw new Error('Bạn đã nộp câu này rồi.');
  }
  await redis.expire(submittedKey(pin, questionId), 7200);

  const question = await getQuestionById(questionId);
  if (!question) {
    throw new Error('Câu hỏi không tồn tại.');
  }

  const { correct, correctAnswer } = checkAnswer(question, answer);
  const player = await getPlayer(redis, pin, name);
  const streakBefore = player?.streak_correct || 0;

  const { scoreEarned, streakAfter } = calculateScore({
    timeLimitSeconds: question.time_limit_seconds,
    questionStartedAt: room.question_started_at,
    hybridTimestamp,
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
  await refreshRoomTtl(redis, pin);

  const sessionId = Number(room.session_id);
  if (sessionId) {
    await saveSessionAnswer({
      sessionId,
      questionId: question.id,
      studentName: name,
      answerSubmitted: answer,
      isCorrect: correct,
      scoreEarned,
      answeredAt: new Date(Number(hybridTimestamp)),
    });
  }

  const totalScore = Number(await redis.zscore(leaderboardKey(pin), name)) || 0;
  const rank = await getPlayerRank(redis, pin, name);

  await emitLeaderboard(io, redis, pin);
  await emitSubmitCount(io, redis, pin, questionId);

  return {
    correct,
    correct_answer: correctAnswer,
    score_earned: scoreEarned,
    rank,
    total_score: totalScore,
  };
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

  const top5 = [];
  for (let i = 0; i < entries.length; i += 2) {
    const playerName = entries[i];
    const score = Number(entries[i + 1]);
    const prev = Number(prevRaw[playerName] || 0);
    top5.push({
      name: playerName,
      score,
      delta: score - prev,
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

module.exports = {
  registerGameplayHandlers,
};
