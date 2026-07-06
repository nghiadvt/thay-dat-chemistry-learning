const mysql = require('mysql2/promise');

let pool;

function getPool() {
  if (!pool) {
    pool = mysql.createPool({
      host: process.env.DB_HOST || 'mysql',
      port: Number(process.env.DB_PORT || 3306),
      database: process.env.DB_NAME || 'chem_quiz',
      user: process.env.DB_USER || 'app_user',
      password: process.env.DB_PASSWORD || 'changeme',
      waitForConnections: true,
      connectionLimit: 10,
    });
  }
  return pool;
}

async function getSessionByPin(pin) {
  const [rows] = await getPool().execute(
    `SELECT id, pin, host_id, game_id, status, started_at, ended_at
     FROM game_sessions WHERE pin = ? LIMIT 1`,
    [pin]
  );
  return rows[0] || null;
}

async function updateSessionStatus(pin, status, extra = {}) {
  const fields = ['status = ?'];
  const values = [status];

  if (status === 'playing' && extra.started_at) {
    fields.push('started_at = ?');
    values.push(extra.started_at);
  }
  if (status === 'ended' && extra.ended_at) {
    fields.push('ended_at = ?');
    values.push(extra.ended_at);
  }

  values.push(pin);
  await getPool().execute(
    `UPDATE game_sessions SET ${fields.join(', ')}, updated_at = NOW() WHERE pin = ?`,
    values
  );
}

async function getGameQuizzes(gameId) {
  const [rows] = await getPool().execute(
    `SELECT id, game_id, keyboard_id, name, sort_order
     FROM quizzes
     WHERE game_id = ? AND is_active = 1
     ORDER BY sort_order ASC, id ASC`,
    [gameId]
  );
  return rows;
}

async function getQuizQuestions(quizId) {
  const [rows] = await getPool().execute(
    `SELECT id, quiz_id, content, answer_type, options, correct_index,
            correct_answer_normalized, input_mode, template, correct_answer,
            time_limit_seconds, sort_order
     FROM questions
     WHERE quiz_id = ? AND is_active = 1
     ORDER BY sort_order ASC, id ASC`,
    [quizId]
  );
  return rows.map(parseQuestionRow);
}

async function getQuestionById(questionId) {
  const [rows] = await getPool().execute(
    `SELECT id, quiz_id, content, answer_type, options, correct_index,
            correct_answer_normalized, input_mode, template, correct_answer,
            time_limit_seconds, sort_order
     FROM questions WHERE id = ? LIMIT 1`,
    [questionId]
  );
  return rows[0] ? parseQuestionRow(rows[0]) : null;
}

async function getKeyboardConfig(keyboardId) {
  const [rows] = await getPool().execute(
    'SELECT config FROM keyboards WHERE id = ? LIMIT 1',
    [keyboardId]
  );
  if (!rows[0]) return null;
  const config = rows[0].config;
  return typeof config === 'string' ? JSON.parse(config) : config;
}

async function saveSessionAnswer({ sessionId, questionId, studentName, answerSubmitted, isCorrect, scoreEarned, answeredAt }) {
  await getPool().execute(
    `INSERT INTO session_answers
       (session_id, question_id, student_name, answer_submitted, is_correct, score_earned, answered_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
       answer_submitted = VALUES(answer_submitted),
       is_correct = VALUES(is_correct),
       score_earned = VALUES(score_earned),
       answered_at = VALUES(answered_at),
       updated_at = NOW()`,
    [
      sessionId,
      questionId,
      studentName,
      JSON.stringify(answerSubmitted),
      isCorrect ? 1 : 0,
      scoreEarned,
      answeredAt,
    ]
  );
}

async function saveGameResults(sessionId, leaderboardEntries) {
  const conn = await getPool().getConnection();
  try {
    await conn.beginTransaction();
    await conn.execute('DELETE FROM game_results WHERE session_id = ?', [sessionId]);

    for (let i = 0; i < leaderboardEntries.length; i += 1) {
      const entry = leaderboardEntries[i];
      await conn.execute(
        `INSERT INTO game_results
           (session_id, student_name, player_token, score, \`rank\`, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())`,
        [sessionId, entry.name, entry.player_token || null, entry.score, i + 1]
      );
    }

    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

function parseQuestionRow(row) {
  return {
    ...row,
    options: parseJson(row.options),
    template: parseJson(row.template),
    correct_answer: parseJson(row.correct_answer),
  };
}

function parseJson(value) {
  if (value == null) return null;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

module.exports = {
  getPool,
  getSessionByPin,
  updateSessionStatus,
  getGameQuizzes,
  getQuizQuestions,
  getQuestionById,
  getKeyboardConfig,
  saveSessionAnswer,
  saveGameResults,
};
