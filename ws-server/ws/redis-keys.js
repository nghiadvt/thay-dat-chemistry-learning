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

module.exports = {
  ROOM_TTL,
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
};
