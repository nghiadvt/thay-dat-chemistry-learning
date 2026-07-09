const {
  roomKey,
  playersKey,
  leaderboardKey,
} = require('../redis-keys');
const {
  updateSessionStatus,
  getQuizById,
  getQuizQuestions,
  getKeyboardConfig,
  getQuestionById,
  saveSessionAnswer,
  saveGameResults,
  getSessionByPin,
} = require('../db');
const { checkAnswer } = require('../scoring');
const {
  getConnectedStudents,
  refreshRoomTtl,
  getPlayer,
  savePlayer,
  broadcastPlayerList,
} = require('../room');
const {
  parseModeConfig,
  getRules,
  finishersKey,
  getPlayerProgress,
  savePlayerProgress,
  getFinishersCount,
  getStudentCount,
  getRequiredFinisherCount,
  getFinishersList,
  positionFromScore,
  buildRacePlayers,
  captureRaceStartHr,
  elapsedSecondsFromStart,
  registerFinisher,
} = require('./duck-race-config');

function isDuckRaceRoom(room) {
  return (room?.play_mode_slug || 'kahoot_sync') === 'duck_race';
}

async function loadDuckRacePlan(redis, pin, room) {
  const raw = await redis.get(`room:${pin}:plan`);
  if (raw) return JSON.parse(raw);

  const quizId = room.quiz_id ? Number(room.quiz_id) : null;
  if (!quizId) throw new Error('Phòng đua vịt cần gắn quiz.');

  const quiz = await getQuizById(quizId);
  if (!quiz) throw new Error('Quiz không tồn tại.');

  const questions = await getQuizQuestions(quizId);
  if (questions.length === 0) throw new Error('Quiz không có câu hỏi.');

  const keyboardConfig = await getKeyboardConfig(quiz.keyboard_id);
  const plan = [{
    quiz_id: quiz.id,
    show_explanation: Boolean(Number(quiz.show_explanation)),
    shuffle_options: Boolean(Number(quiz.shuffle_options)),
    keyboard_id: quiz.keyboard_id,
    keyboard_config: keyboardConfig,
    questions,
  }];

  await redis.set(`room:${pin}:plan`, JSON.stringify(plan), 'EX', 7200);
  return plan;
}

function buildStudentQuestionPayload(quiz, question) {
  return {
    quiz_id: quiz.quiz_id,
    question_id: question.id,
    content: question.content,
    answer_type: question.answer_type,
    options: question.answer_type === 'mc' ? question.options : null,
    template: question.answer_type === 'structured' ? question.template : null,
    input_mode: question.input_mode || null,
    keyboard_config: quiz.keyboard_config,
    time_limit: null,
    play_mode: 'duck_race',
    server_time: Date.now(),
  };
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

async function mapShuffledAnswer(redis, pin, questionId, studentName, question, answer) {
  if (question.answer_type !== 'mc' || answer == null) return answer;

  const orderRaw = await redis.get(optionOrderKey(pin, questionId, studentName));
  if (!orderRaw) return answer;

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

async function emitRaceUpdate(io, redis, pin, rules) {
  const room = await redis.hgetall(roomKey(pin));
  const config = parseModeConfig(room);
  const players = await buildRacePlayers(redis, pin, rules, config);
  io.to(pin).emit('race_update', {
    players,
    target_score: rules.targetScore,
    track_steps: rules.trackSteps,
  });
}

async function emitQuestionToStudent(io, redis, pin, socket, quiz, question) {
  const payload = buildStudentQuestionPayload(quiz, question);

  if (question.answer_type === 'mc'
    && quiz.shuffle_options
    && Array.isArray(question.options)
    && question.options.length > 1) {
    const order = shuffleIndices(question.options.length);
    await redis.set(
      optionOrderKey(pin, question.id, socket.data.name),
      JSON.stringify(order),
      'EX',
      7200,
    );
    socket.emit('new_question', {
      ...payload,
      options: order.map((idx) => question.options[idx]),
    });
    return;
  }

  socket.emit('new_question', payload);
}

async function emitQuestionForPlayer(io, redis, pin, name, plan, questionIndex) {
  const quiz = plan[0];
  const questions = quiz.questions;
  const idx = questionIndex % questions.length;
  const question = questions[idx];

  const sockets = await io.in(pin).fetchSockets();
  const socket = sockets.find((s) => s.data?.name === name && !s.data?.isHost);
  if (!socket) return;

  await emitQuestionToStudent(io, redis, pin, socket, quiz, question);
}

async function startGame(io, redis, socket) {
  const pin = socket.data.pin;
  const room = await redis.hgetall(roomKey(pin));

  if (room.status === 'playing') throw new Error('Game đã bắt đầu.');
  if (room.status === 'ended') throw new Error('Game đã kết thúc.');

  const session = await getSessionByPin(pin);
  if (!session) throw new Error('Session không tồn tại.');
  if (!Number(session.is_active)) throw new Error('Phòng đã bị tắt.');

  const config = parseModeConfig(room);
  const rules = getRules(config);
  const plan = await loadDuckRacePlan(redis, pin, room);

  await updateSessionStatus(pin, 'playing', { started_at: new Date() });

  await redis.hset(roomKey(pin), {
    status: 'playing',
    session_id: String(session.id),
    quiz_index: '0',
    question_index: '0',
    play_mode_slug: 'duck_race',
    race_started_hr: captureRaceStartHr(),
    race_started_at: String(Date.now()),
  });
  await redis.del(finishersKey(pin));
  await refreshRoomTtl(redis, pin);

  const students = await getConnectedStudents(redis, pin);
  for (const student of students) {
    await savePlayerProgress(redis, pin, student.name, {
      question_index: 0,
      finished: false,
      finish_rank: null,
      finished_at: null,
      finish_elapsed_s: null,
    });
  }

  io.to(pin).emit('game_started', { play_mode: 'duck_race', mode_config: config });

  const sockets = await io.in(pin).fetchSockets();
  for (const studentSocket of sockets.filter((s) => !s.data?.isHost)) {
    await emitQuestionToStudent(io, redis, pin, studentSocket, plan[0], plan[0].questions[0]);
  }

  await emitRaceUpdate(io, redis, pin, rules);
  await broadcastPlayerList(io, redis, pin);

  return { play_mode: 'duck_race', started: true };
}

async function handleSubmit(io, redis, socket, payload) {
  const pin = socket.data?.pin;
  const name = socket.data?.name;

  if (!pin || !name || socket.data?.isHost) {
    throw new Error('Chỉ học sinh mới được nộp đáp án.');
  }

  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing') throw new Error('Game chưa bắt đầu.');

  const config = parseModeConfig(room);
  const rules = getRules(config);
  const plan = await loadDuckRacePlan(redis, pin, room);
  const quiz = plan[0];
  const questions = quiz.questions;

  let progress = await getPlayerProgress(redis, pin, name);
  if (progress.finished) {
    throw new Error('Bạn đã về đích.');
  }

  const { question_id: questionId, answer } = payload;
  const question = await getQuestionById(questionId);
  if (!question) throw new Error('Câu hỏi không tồn tại.');

  const expectedQuestion = questions[progress.question_index % questions.length];
  if (String(expectedQuestion.id) !== String(questionId)) {
    throw new Error('Không phải câu hỏi hiện tại.');
  }

  const answerForScoring = await mapShuffledAnswer(redis, pin, questionId, name, question, answer);
  const { correct, correctAnswer } = checkAnswer(question, answerForScoring);

  const currentScore = Number(await redis.zscore(leaderboardKey(pin), name)) || 0;
  const delta = correct ? rules.correctDelta : rules.wrongDelta;
  const newScore = currentScore + delta;

  await redis.zadd(leaderboardKey(pin), newScore, name);

  const sessionId = Number(room.session_id);
  if (sessionId) {
    await saveSessionAnswer({
      sessionId,
      questionId: question.id,
      studentName: name,
      answerSubmitted: answer,
      isCorrect: correct,
      scoreEarned: delta,
      answeredAt: new Date(),
    });
  }

  let finishRank = null;
  let finishElapsedS = null;
  if (!progress.finished && newScore >= rules.targetScore) {
    finishElapsedS = elapsedSecondsFromStart(room.race_started_hr);
    const finishedAtMs = Date.now();
    finishRank = await registerFinisher(redis, pin, name, finishElapsedS, finishedAtMs);
    progress = await getPlayerProgress(redis, pin, name);

    io.to(pin).emit('player_finished', {
      name,
      finish_rank: finishRank,
      finish_elapsed_s: finishElapsedS,
      total_score: newScore,
    });
  }

  const position = positionFromScore(newScore, rules);

  socket.emit('answer_feedback', {
    correct,
    score_delta: delta,
    total_score: newScore,
    position,
    correct_answer: correctAnswer,
    finish_rank: finishRank,
    finish_elapsed_s: finishElapsedS,
    target_score: rules.targetScore,
    ...(quiz.show_explanation && question.explanation && !correct
      ? { explanation: question.explanation }
      : {}),
  });

  await emitRaceUpdate(io, redis, pin, rules);
  await broadcastPlayerList(io, redis, pin);
  await refreshRoomTtl(redis, pin);

  if (!progress.finished) {
    progress.question_index += 1;
    await savePlayerProgress(redis, pin, name, progress);
    await emitQuestionForPlayer(io, redis, pin, name, plan, progress.question_index);
  } else if (rules.endWhenPodiumFull) {
    const finisherCount = await getFinishersCount(redis, pin);
    const studentCount = await getStudentCount(redis, pin);
    const requiredFinishers = getRequiredFinisherCount(rules.podiumSize, studentCount);
    if (finisherCount >= requiredFinishers) {
      await endGame(io, redis, pin);
    }
  }

  return {
    correct,
    score_delta: delta,
    total_score: newScore,
    position,
    finish_rank: finishRank,
    finish_elapsed_s: finishElapsedS,
  };
}

async function buildFinalLeaderboard(redis, pin, rules) {
  const finishers = await getFinishersList(redis, pin);
  const allPlayers = await redis.hgetall(playersKey(pin));
  const entries = [];

  for (const finisherName of finishers) {
    const raw = allPlayers[finisherName];
    if (!raw) continue;
    const player = JSON.parse(raw);
    const score = Number(await redis.zscore(leaderboardKey(pin), finisherName)) || 0;
    const progress = await getPlayerProgress(redis, pin, finisherName);
    entries.push({
      name: finisherName,
      score,
      finish_rank: progress.finish_rank,
      finish_elapsed_s: progress.finish_elapsed_s ?? null,
      finished_at: progress.finished_at,
      player_token: player.player_token || null,
      avatar: player.avatar || null,
      duck_sprite: player.duck_sprite || null,
    });
  }

  entries.sort((a, b) => {
    const rankDiff = (a.finish_rank || 99) - (b.finish_rank || 99);
    if (rankDiff !== 0) return rankDiff;
    const timeDiff = (a.finish_elapsed_s ?? Infinity) - (b.finish_elapsed_s ?? Infinity);
    if (timeDiff !== 0) return timeDiff;
    return a.name.localeCompare(b.name);
  });

  const finisherSet = new Set(finishers);
  const rest = [];

  for (const raw of Object.values(allPlayers)) {
    const player = JSON.parse(raw);
    if (player.is_host || finisherSet.has(player.name)) continue;
    const score = Number(await redis.zscore(leaderboardKey(pin), player.name)) || 0;
    rest.push({
      name: player.name,
      score,
      finish_rank: null,
      finish_elapsed_s: null,
      finished_at: null,
      player_token: player.player_token || null,
      avatar: player.avatar || null,
      duck_sprite: player.duck_sprite || null,
    });
  }

  rest.sort((a, b) => b.score - a.score || a.name.localeCompare(b.name));
  const combined = [...entries, ...rest];

  return combined.map((entry, index) => ({
    name: entry.name,
    score: entry.score,
    rank: index + 1,
    finish_rank: entry.finish_rank,
    finish_elapsed_s: entry.finish_elapsed_s ?? null,
    player_token: entry.player_token,
    avatar: entry.avatar,
    duck_sprite: entry.duck_sprite ?? null,
  }));
}

async function endGame(io, redis, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status === 'ended') return;

  const config = parseModeConfig(room);
  const rules = getRules(config);
  const session = await getSessionByPin(pin);
  const finalLeaderboard = await buildFinalLeaderboard(redis, pin, rules);

  if (session) {
    await updateSessionStatus(pin, 'ended', { ended_at: new Date() });
    await saveGameResults(session.id, finalLeaderboard);
  }

  await redis.hset(roomKey(pin), { status: 'ended' });
  await refreshRoomTtl(redis, pin);

  io.to(pin).emit('game_ended', {
    play_mode: 'duck_race',
    final_leaderboard: finalLeaderboard,
  });
}

async function syncLateJoinHost(io, redis, socket, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing' || !isDuckRaceRoom(room)) return;

  const config = parseModeConfig(room);
  const rules = getRules(config);
  const players = await buildRacePlayers(redis, pin, rules);

  socket.emit('game_started', { play_mode: 'duck_race', mode_config: config });
  socket.emit('race_update', {
    players,
    target_score: rules.targetScore,
    track_steps: rules.trackSteps,
  });
}

async function syncLateJoin(io, redis, socket, pin) {
  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'playing' || !isDuckRaceRoom(room)) return;

  const config = parseModeConfig(room);
  const rules = getRules(config);
  const plan = await loadDuckRacePlan(redis, pin, room);

  const progressKey = require('./duck-race-config').playerProgressKey(pin, socket.data.name);
  const existingProgress = await redis.get(progressKey);
  if (!existingProgress) {
    await savePlayerProgress(redis, pin, socket.data.name, {
      question_index: 0,
      finished: false,
      finish_rank: null,
      finished_at: null,
      finish_elapsed_s: null,
    });
  }

  const progress = await getPlayerProgress(redis, pin, socket.data.name);

  socket.emit('game_started', { play_mode: 'duck_race', mode_config: config });

  if (!progress.finished) {
    const qIdx = progress.question_index || 0;
    const quiz = plan[0];
    const questions = quiz.questions;
    const question = questions[qIdx % questions.length];
    await emitQuestionToStudent(io, redis, pin, socket, quiz, question);
  }

  await emitRaceUpdate(io, redis, pin, rules);
}

module.exports = {
  isDuckRaceRoom,
  startGame,
  handleSubmit,
  endGame,
  buildFinalLeaderboard,
  syncLateJoin,
  syncLateJoinHost,
};
