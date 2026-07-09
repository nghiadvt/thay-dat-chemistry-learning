const { hrtime } = require('process');
const {
  playersKey,
  leaderboardKey,
  roomKey,
} = require('../redis-keys');

const DEFAULT_DUCK_SPRITES = ['ducks/duck-blue.gif'];

const DEFAULT_CONFIG = {
  scoring: { correct_delta: 3, wrong_delta: -5, allow_negative: true },
  win: { target_score: 30, podium_size: 3 },
  flow: { sync_questions: false, advance_on: 'submit', use_timer: false, end_when_podium_full: true },
  visual: {
    theme: 'duck_race',
    track_steps: 30,
    track_bounds: { start_pct: 20, end_pct: 90 },
    lane_bounds: { top_pct: 50, bottom_pct: 92 },
    duck_sprite_px: 64,
    duck_swim_ms: 1150,
    duck_sprites: DEFAULT_DUCK_SPRITES,
  },
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

function duckPoolKey(pin) {
  return `room:${pin}:duck_pool`;
}

function shuffleArray(items) {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function getDuckSprites(config) {
  const sprites = config?.visual?.duck_sprites;
  if (Array.isArray(sprites) && sprites.length > 0) {
    return sprites.map(String);
  }
  return [...DEFAULT_DUCK_SPRITES];
}

async function refillDuckPool(redis, pin, sprites) {
  const key = duckPoolKey(pin);
  const pool = shuffleArray(sprites);
  if (!pool.length) return;
  await redis.del(key);
  await redis.rpush(key, ...pool);
  await redis.expire(key, 7200);
}

async function drawDuckSprite(redis, pin, sprites) {
  const key = duckPoolKey(pin);
  let sprite = await redis.lpop(key);
  if (!sprite) {
    await refillDuckPool(redis, pin, sprites);
    sprite = await redis.lpop(key);
  }
  return sprite || sprites[0] || DEFAULT_DUCK_SPRITES[0];
}

async function assignDuckSprite(redis, pin, config, existingSprite) {
  if (existingSprite) return existingSprite;
  const sprites = getDuckSprites(config);
  return drawDuckSprite(redis, pin, sprites);
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
  return { question_index: 0, finished: false, finish_rank: null, finished_at: null, finish_elapsed_s: null };
}

function captureRaceStartHr() {
  return hrtime.bigint().toString();
}

function elapsedSecondsFromStart(raceStartedHr) {
  if (!raceStartedHr) return null;
  const elapsedNs = hrtime.bigint() - BigInt(raceStartedHr);
  const seconds = Number(elapsedNs) / 1e9;
  return Math.round(seconds * 10000) / 10000;
}

function formatFinishElapsedS(elapsedS) {
  if (elapsedS == null || !Number.isFinite(Number(elapsedS))) return null;
  return `${Number(elapsedS).toFixed(4)}s`;
}

async function savePlayerProgress(redis, pin, name, progress) {
  await redis.set(playerProgressKey(pin, name), JSON.stringify(progress), 'EX', 7200);
}

async function getFinishersCount(redis, pin) {
  return redis.llen(finishersKey(pin));
}

async function getStudentCount(redis, pin) {
  const all = await redis.hgetall(playersKey(pin));
  return Object.values(all).filter((raw) => {
    try {
      return !JSON.parse(raw).is_host;
    } catch {
      return false;
    }
  }).length;
}

/** Số người về đích cần để tự kết thúc — không vượt quá số HS thực tế trong phòng. */
function getRequiredFinisherCount(podiumSize, studentCount) {
  const podium = Math.max(1, Number(podiumSize) || 3);
  const students = Math.max(0, Number(studentCount) || 0);
  if (students === 0) return podium;
  return Math.min(podium, students);
}

async function recalculateFinisherRanks(redis, pin) {
  const names = await getFinishersList(redis, pin);
  const rows = [];
  for (const name of names) {
    const progress = await getPlayerProgress(redis, pin, name);
    rows.push({
      name,
      elapsed_s: progress.finish_elapsed_s ?? Infinity,
    });
  }
  rows.sort((a, b) => a.elapsed_s - b.elapsed_s || a.name.localeCompare(b.name));

  let rank = 1;
  for (let i = 0; i < rows.length; i += 1) {
    if (i > 0 && rows[i].elapsed_s !== rows[i - 1].elapsed_s) {
      rank = i + 1;
    }
    const progress = await getPlayerProgress(redis, pin, rows[i].name);
    if (progress.finish_rank !== rank) {
      progress.finish_rank = rank;
      await savePlayerProgress(redis, pin, rows[i].name, progress);
    }
  }
}

/** Ghi nhận về đích + xếp hạng (đồng hạng nếu cùng `finish_elapsed_s`). */
async function registerFinisher(redis, pin, name, elapsedS, finishedAtMs) {
  const progress = await getPlayerProgress(redis, pin, name);
  progress.finished = true;
  progress.finish_elapsed_s = elapsedS;
  progress.finished_at = finishedAtMs;
  await savePlayerProgress(redis, pin, name, progress);

  const list = await getFinishersList(redis, pin);
  if (!list.includes(name)) {
    await redis.rpush(finishersKey(pin), name);
    await redis.expire(finishersKey(pin), 7200);
  }

  await recalculateFinisherRanks(redis, pin);
  const updated = await getPlayerProgress(redis, pin, name);
  return updated.finish_rank;
}

async function getFinishersList(redis, pin) {
  return redis.lrange(finishersKey(pin), 0, -1);
}

/** Vị trí trên đường đua 0–100 (%). Điểm âm không đẩy vịt lùi quá vạch xuất phát. */
function positionFromScore(score, rules) {
  const target = rules.targetScore || rules.trackSteps || 30;
  const forwardSteps = Math.max(0, Number(score));
  const pct = (forwardSteps / target) * 100;
  return Math.round(Math.min(100, pct) * 10) / 10;
}

async function buildRacePlayers(redis, pin, rules, config) {
  const allPlayers = await redis.hgetall(playersKey(pin));
  let modeConfig = config;
  if (!modeConfig) {
    const room = await redis.hgetall(roomKey(pin));
    modeConfig = parseModeConfig(room);
  }
  const players = [];

  for (const raw of Object.values(allPlayers)) {
    const player = JSON.parse(raw);
    if (player.is_host) continue;

    if (!player.duck_sprite) {
      player.duck_sprite = await assignDuckSprite(redis, pin, modeConfig, null);
      await redis.hset(playersKey(pin), player.name, JSON.stringify(player));
    }

    const score = Number(await redis.zscore(leaderboardKey(pin), player.name)) || 0;
    const progress = await getPlayerProgress(redis, pin, player.name);

    players.push({
      name: player.name,
      avatar: player.avatar || null,
      duck_sprite: player.duck_sprite || null,
      score,
      position: positionFromScore(score, rules),
      finished: Boolean(progress.finished),
      finish_rank: progress.finish_rank,
      finish_elapsed_s: progress.finish_elapsed_s ?? null,
    });
  }

  players.sort((a, b) => {
    if (a.finished && b.finished) {
      const rankDiff = (a.finish_rank || 99) - (b.finish_rank || 99);
      if (rankDiff !== 0) return rankDiff;
      const timeDiff = (a.finish_elapsed_s ?? Infinity) - (b.finish_elapsed_s ?? Infinity);
      if (timeDiff !== 0) return timeDiff;
      return a.name.localeCompare(b.name);
    }
    if (a.finished) return -1;
    if (b.finished) return 1;
    return b.score - a.score || a.name.localeCompare(b.name);
  });

  return players;
}

module.exports = {
  DEFAULT_CONFIG,
  DEFAULT_DUCK_SPRITES,
  parseModeConfig,
  getRules,
  finishersKey,
  duckPoolKey,
  getDuckSprites,
  assignDuckSprite,
  refillDuckPool,
  playerProgressKey,
  getPlayerProgress,
  savePlayerProgress,
  getFinishersCount,
  getStudentCount,
  getRequiredFinisherCount,
  registerFinisher,
  getFinishersList,
  captureRaceStartHr,
  elapsedSecondsFromStart,
  formatFinishElapsedS,
  positionFromScore,
  buildRacePlayers,
};
