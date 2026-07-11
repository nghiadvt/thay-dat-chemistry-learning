const ROOM_TTL = 7200;

function roomKey(pin) {
  return `room:${pin}`;
}

function playersKey(pin) {
  return `room:${pin}:players`;
}

function leaderboardKey(pin) {
  return `leaderboard:${pin}`;
}

function submittedKey(pin, questionId) {
  return `submitted:${pin}:${questionId}`;
}

function answerKey(pin, questionId, studentName) {
  return `room:${pin}:answer:${questionId}:${studentName}`;
}

function answersPattern(pin, questionId) {
  return `room:${pin}:answer:${questionId}:*`;
}

/** Non-blocking key lookup (SCAN) — avoids the whole-instance stall KEYS causes at scale. */
async function scanKeys(redis, pattern) {
  const keys = [];
  let cursor = '0';
  do {
    const [nextCursor, batch] = await redis.scan(cursor, 'MATCH', pattern, 'COUNT', 1000);
    cursor = nextCursor;
    keys.push(...batch);
  } while (cursor !== '0');
  return [...new Set(keys)];
}

module.exports = {
  ROOM_TTL,
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
  answerKey,
  answersPattern,
  scanKeys,
};
