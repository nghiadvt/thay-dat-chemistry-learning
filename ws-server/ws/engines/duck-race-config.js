const {
  playersKey,
  leaderboardKey,
} = require('../redis-keys');

const DEFAULT_CONFIG = {
  scoring: { correct_delta: 3, wrong_delta: -5, allow_negative: true },
  win: { target_score: 30, podium_size: 3 },
  flow: { sync_questions: false, advance_on: 'submit', use_timer: false, end_when_podium_full: true },
  visual: { theme: 'duck_race', track_steps: 30 },
};

function parseModeConfig(room) {
  const raw = room?.mode_config;
  if (!raw) return { ...DEFAULT_CONFIG };
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return { ...DEFAULT_CONFIG, ...parsed };
  } catch {
    return { ...DEFAULT_CONFIG };
  }
}

function getRules(config) {
  return {
    correctDelta: Number(config?.scoring?.correct_delta ?? 3),
    wrongDelta: Number(config?.scoring?.wrong_delta ?? -5),
    targetScore: Number(config?.win?.target_score ?? 30),
    podiumSize: Number(config?.win?.podium_size ?? 3),
    trackSteps: Number(config?.visual?.track_steps ?? config?.win?.target_score ?? 30),
    endWhenPodiumFull: config?.flow?.end_when_podium_full !== false,
  };
}

function finishersKey(pin) {
  return `room:${pin}:finishers`;
}

function playerProgressKey(pin, name) {
  return `room:${pin}:race_progress:${name}`;
}

async function getPlayerProgress(redis, pin, name) {
  const raw = await redis.get(playerProgressKey(pin, name));
  if (raw) {
    try {
      return JSON.parse(raw);
    } catch {
      /* fall through */
    }
  }
  return { question_index: 0, finished: false, finish_rank: null, finished_at: null };
}

async function savePlayerProgress(redis, pin, name, progress) {
  await redis.set(playerProgressKey(pin, name), JSON.stringify(progress), 'EX', 7200);
}

async function getFinishersCount(redis, pin) {
  return redis.lLen(finishersKey(pin));
}

async function addFinisher(redis, pin, name) {
  const count = await getFinishersCount(redis, pin);
  await redis.rPush(finishersKey(pin), name);
  await redis.expire(finishersKey(pin), 7200);
  return count + 1;
}

async function getFinishersList(redis, pin) {
  return redis.lRange(finishersKey(pin), 0, -1);
}

function positionFromScore(score, rules) {
  return Math.round((Number(score) / rules.trackSteps) * 1000) / 10;
}

async function buildRacePlayers(redis, pin, rules) {
  const allPlayers = await redis.hgetall(playersKey(pin));
  const players = [];

  for (const raw of Object.values(allPlayers)) {
    const player = JSON.parse(raw);
    if (player.is_host) continue;

    const score = Number(await redis.zscore(leaderboardKey(pin), player.name)) || 0;
    const progress = await getPlayerProgress(redis, pin, player.name);

    players.push({
      name: player.name,
      avatar: player.avatar || null,
      score,
      position: positionFromScore(score, rules),
      finished: Boolean(progress.finished),
      finish_rank: progress.finish_rank,
    });
  }

  players.sort((a, b) => {
    if (a.finished && b.finished) return (a.finish_rank || 99) - (b.finish_rank || 99);
    if (a.finished) return -1;
    if (b.finished) return 1;
    return b.score - a.score || a.name.localeCompare(b.name);
  });

  return players;
}

module.exports = {
  DEFAULT_CONFIG,
  parseModeConfig,
  getRules,
  finishersKey,
  playerProgressKey,
  getPlayerProgress,
  savePlayerProgress,
  getFinishersCount,
  addFinisher,
  getFinishersList,
  positionFromScore,
  buildRacePlayers,
};
