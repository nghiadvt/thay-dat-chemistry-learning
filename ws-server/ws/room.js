const { randomUUID } = require('crypto');
const {
  ROOM_TTL,
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
  scanKeys,
} = require('./redis-keys');
const { getSessionByPin } = require('./db');

const MAX_NAME_LENGTH = 20;
/** JPEG data URL from student camera (~240×240) — reject oversized payloads */
const MAX_AVATAR_LENGTH = 120000;

/** Theme màn hình học sinh — GV chọn, đồng bộ mọi HS (khớp prototype/js/student-theme.js) */
const STUDENT_THEMES = ['default', 'lab', 'galaxy', 'arcade', 'chalk'];

/** Khóa Redis do Laravel ghi — xem App\Services\StudentPlayToken. */
const PLAY_TOKEN_PREFIX = 'student_play_token:';

/**
 * Đổi token ngắn hạn lấy student_id.
 *
 * Token do Laravel phát ra sau khi đã xác thực phiên học sinh, nên đây là cách
 * duy nhất để ws-server biết chắc lượt chơi thuộc về tài khoản nào. Token sai
 * hoặc hết hạn thì coi như chơi ẩn danh chứ không chặn vào phòng — học sinh
 * chưa có tài khoản vẫn phải chơi được bằng PIN như trước.
 */
async function resolvePlayToken(redis, rawToken) {
  if (!rawToken) return null;

  const token = String(rawToken).trim();
  if (!token || token.length > 128) return null;

  try {
    const value = await redis.get(PLAY_TOKEN_PREFIX + token);
    const studentId = Number(value);
    return Number.isInteger(studentId) && studentId > 0 ? studentId : null;
  } catch {
    // Redis lỗi thì vẫn cho vào phòng, chỉ là không gắn được tài khoản.
    return null;
  }
}

function sanitizeAvatar(raw) {
  if (raw == null || raw === '') return null;
  const avatar = String(raw).trim();
  if (!avatar) return null;
  if (avatar.length > MAX_AVATAR_LENGTH) {
    throw new Error('Ảnh đại diện quá lớn. Chụp lại hoặc bỏ qua.');
  }
  if (!/^data:image\/(jpeg|jpg|png|webp);base64,/i.test(avatar)) {
    throw new Error('Ảnh đại diện không hợp lệ.');
  }
  return avatar;
}

function registerRoomHandlers(io, redis) {
  io.on('connection', (socket) => {
    socket.on('join_room', async (payload = {}, ack) => {
      try {
        const result = await handleJoinRoom(io, redis, socket, payload);
        socket.emit('room_joined', result);
        if (typeof ack === 'function') ack({ success: true, data: result });
        if (payload.is_host && (result.room_status === 'playing' || result.room_status === 'ended')) {
          const { syncLateJoinHost } = require('./gameplay');
          await syncLateJoinHost(io, redis, socket, result.pin);
        } else if (!payload.is_host && (result.room_status === 'playing' || result.room_status === 'ended')) {
          const { syncLateJoinStudent } = require('./gameplay');
          await syncLateJoinStudent(io, redis, socket, result.pin);
        }
      } catch (err) {
        const message = err.message || 'Không thể vào phòng.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('host_set_theme', async (payload = {}, ack) => {
      try {
        const { pin, isHost } = socket.data || {};
        if (!isHost || !pin) {
          throw new Error('Chỉ giáo viên mới được đổi giao diện.');
        }
        const theme = String(payload.theme || '').trim();
        if (!STUDENT_THEMES.includes(theme)) {
          throw new Error('Giao diện không hợp lệ.');
        }
        await redis.hset(roomKey(pin), 'student_theme', theme);
        await refreshRoomTtl(redis, pin);
        io.to(pin).emit('theme_update', { theme });
        if (typeof ack === 'function') ack({ success: true, data: { theme } });
      } catch (err) {
        const message = err.message || 'Không đổi được giao diện.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('select_duck', async (payload = {}, ack) => {
      try {
        const result = await handleSelectDuck(redis, socket, payload);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không chọn được vịt.';
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('update_profile', async (payload = {}, ack) => {
      try {
        const result = await handleUpdateProfile(io, redis, socket, payload);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không cập nhật được thông tin.';
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('disconnect', async () => {
      await handleDisconnect(redis, socket);
    });
  });
}

async function handleSelectDuck(redis, socket, payload) {
  const { pin, name, isHost } = socket.data || {};
  if (!pin || !name || isHost) {
    throw new Error('Không thể chọn vịt.');
  }

  const room = await redis.hgetall(roomKey(pin));
  if ((room.play_mode_slug || '') !== 'duck_race') {
    throw new Error('Phòng này không phải chế độ đua vịt.');
  }
  if (room.status !== 'waiting') {
    throw new Error('Không thể đổi vịt sau khi ván đấu đã bắt đầu.');
  }

  const { parseModeConfig, getDuckSprites } = require('./engines/duck-race-config');
  const config = parseModeConfig(room);
  const sprites = getDuckSprites(config);
  const sprite = String(payload.duck_sprite || '');
  if (!sprites.includes(sprite)) {
    throw new Error('Vịt không hợp lệ.');
  }

  const player = await getPlayer(redis, pin, name);
  if (!player) {
    throw new Error('Không tìm thấy người chơi.');
  }
  player.duck_sprite = sprite;
  await savePlayer(redis, pin, player);

  return { duck_sprite: sprite };
}

/**
 * Đổi tên/ảnh giữa lúc chờ. Tên là khóa định danh người chơi trong Redis
 * (players hash + leaderboard zset), nên đổi tên phải rename cả 2 chỗ và
 * cập nhật socket.data.name — chỉ cho phép khi phòng còn 'waiting' để tránh
 * vỡ dữ liệu theo tên đã ghi lúc đang chơi (submitted:*, answer:*...).
 */
async function handleUpdateProfile(io, redis, socket, payload) {
  const { pin, name, isHost } = socket.data || {};
  if (!pin || !name || isHost) {
    throw new Error('Không thể cập nhật thông tin.');
  }

  const room = await redis.hgetall(roomKey(pin));
  if (room.status !== 'waiting') {
    throw new Error('Không thể chỉnh sửa sau khi ván đấu đã bắt đầu.');
  }

  const player = await getPlayer(redis, pin, name);
  if (!player) {
    throw new Error('Không tìm thấy người chơi.');
  }

  let currentName = name;
  const rawName = payload.name != null ? String(payload.name).trim() : null;
  if (rawName && rawName !== name) {
    if (rawName.length > MAX_NAME_LENGTH) {
      throw new Error(`Tên tối đa ${MAX_NAME_LENGTH} ký tự.`);
    }
    const taken = await redis.hexists(playersKey(pin), rawName);
    if (taken) {
      throw new Error('Tên đã được sử dụng trong phòng này.');
    }
    const score = Number(await redis.zscore(leaderboardKey(pin), name)) || 0;
    await redis.hdel(playersKey(pin), name);
    await redis.zrem(leaderboardKey(pin), name);
    await redis.zadd(leaderboardKey(pin), score, rawName);
    player.name = rawName;
    currentName = rawName;
    socket.data.name = rawName;
  }

  if (payload.remove_avatar) {
    player.avatar = null;
  } else if (payload.avatar) {
    player.avatar = sanitizeAvatar(payload.avatar);
  }

  await savePlayer(redis, pin, player);
  await broadcastPlayerList(io, redis, pin);

  return { name: currentName, avatar: player.avatar || null };
}

async function handleJoinRoom(io, redis, socket, payload) {
  const pin = String(payload.pin || '').trim();
  const name = String(payload.name || '').trim();
  const isHost = Boolean(payload.is_host);
  const avatar = isHost ? null : sanitizeAvatar(payload.avatar);

  if (!/^\d{6}$/.test(pin)) {
    throw new Error('PIN phải là 6 chữ số.');
  }
  if (!name) {
    throw new Error('Tên không được để trống.');
  }
  if (name.length > MAX_NAME_LENGTH) {
    throw new Error(`Tên tối đa ${MAX_NAME_LENGTH} ký tự.`);
  }

  const roomExists = await redis.exists(roomKey(pin));
  if (!roomExists) {
    throw new Error('PIN không hợp lệ hoặc phòng đã hết hạn.');
  }

  const session = await getSessionByPin(pin);
  if (!session) {
    throw new Error('Không tìm thấy session cho PIN này.');
  }
  if (!Number(session.is_active)) {
    throw new Error('Phòng đã bị tắt.');
  }

  const room = await redis.hgetall(roomKey(pin));

  const providedToken = payload.player_token ? String(payload.player_token).trim() : null;
  // Danh tính tài khoản học sinh: KHÔNG nhận student_id do client gửi (sửa được
  // để gán lượt chơi cho bạn khác). Chỉ tin token ngắn hạn do Laravel phát ra.
  const resolvedStudentId = await resolvePlayToken(redis, payload.play_token);
  const existingRaw = await redis.hget(playersKey(pin), name);

  // HS mới không vào được phòng đã kết thúc; HS đã chơi được quay lại xem kết quả.
  if (room.status === 'ended' && !isHost && !existingRaw) {
    throw new Error('Phòng đã kết thúc.');
  }

  if (isHost) {
    if (!session) {
      throw new Error('Không tìm thấy session cho PIN này.');
    }
  }
  let player;
  let reconnected = false;

  if (existingRaw) {
    player = JSON.parse(existingRaw);
    // Reconnect must prove ownership with the token issued on first join.
    // Fallback (no token supplied) is only allowed while the previous socket
    // is already gone — an actively-connected name can't be hijacked either way.
    if (providedToken) {
      if (providedToken !== player.player_token) {
        throw new Error('Tên đã được sử dụng trong phòng này.');
      }
    } else if (player.connected) {
      throw new Error('Tên đã được sử dụng trong phòng này.');
    }
    reconnected = true;
    // Vào lại kèm token hợp lệ thì cập nhật; không có token thì giữ nguyên
    // danh tính đã gắn lúc vào lần đầu.
    if (resolvedStudentId) {
      player.student_id = resolvedStudentId;
    }
    player.connected = true;
    player.disconnected_at = null;
    player.socket_id = socket.id;
    player.is_host = isHost || player.is_host;
    // Rejoin with a new photo updates avatar; omit avatar → keep previous.
    if (!isHost && avatar) {
      player.avatar = avatar;
    }
  } else {
    if (isHost) {
      // Host is not counted as a student player in leaderboard
      player = {
        name,
        player_token: randomUUID(),
        connected: true,
        is_host: true,
        streak_correct: 0,
        socket_id: socket.id,
        avatar: null,
      };
    } else {
      player = {
        name,
        player_token: randomUUID(),
        student_id: resolvedStudentId,
        connected: true,
        is_host: false,
        streak_correct: 0,
        socket_id: socket.id,
        avatar: avatar || null,
      };
      await redis.zadd(leaderboardKey(pin), 0, name);
    }
  }

  let duckSprites;
  if (!player.is_host && (room.play_mode_slug || '') === 'duck_race') {
    const { parseModeConfig, assignDuckSprite, getDuckSprites } = require('./engines/duck-race-config');
    const config = parseModeConfig(room);
    player.duck_sprite = await assignDuckSprite(redis, pin, config, player.duck_sprite);
    duckSprites = getDuckSprites(config);
  }

  await redis.hset(playersKey(pin), name, JSON.stringify(player));
  await refreshRoomTtl(redis, pin);

  socket.data.pin = pin;
  socket.data.name = name;
  socket.data.isHost = player.is_host;
  socket.data.playerToken = player.player_token;
  socket.data.studentId = player.student_id || null;

  await socket.join(pin);
  if (player.is_host) {
    await socket.join(`host:${pin}`);
  }

  const score = player.is_host
    ? 0
    : Number(await redis.zscore(leaderboardKey(pin), name)) || 0;

  await broadcastPlayerList(io, redis, pin);

  return {
    pin,
    name,
    player_token: player.player_token,
    reconnected,
    score,
    is_host: player.is_host,
    room_status: room.status || 'waiting',
    question_index: room.status === 'playing' ? Number(room.question_index || 0) : null,
    student_theme: STUDENT_THEMES.includes(room.student_theme) ? room.student_theme : 'default',
    play_mode_slug: room.play_mode_slug || null,
    duck_sprite: player.duck_sprite || null,
    duck_sprites: duckSprites || null,
  };
}

async function handleDisconnect(redis, socket) {
  const { pin, name } = socket.data || {};
  if (!pin || !name) return;

  const raw = await redis.hget(playersKey(pin), name);
  if (!raw) return;

  const player = JSON.parse(raw);
  player.connected = false;
  player.disconnected_at = Date.now();
  player.socket_id = null;

  await redis.hset(playersKey(pin), name, JSON.stringify(player));
  await refreshRoomTtl(redis, pin);
}

async function getConnectedStudents(redis, pin) {
  const all = await redis.hgetall(playersKey(pin));
  return Object.values(all)
    .map((raw) => JSON.parse(raw))
    .filter((p) => !p.is_host && p.connected);
}

async function getHostSockets(io, pin) {
  const sockets = await io.in(`host:${pin}`).fetchSockets();
  return sockets;
}

async function broadcastPlayerList(io, redis, pin) {
  const all = await redis.hgetall(playersKey(pin));
  const players = Object.values(all)
    .map((raw) => JSON.parse(raw))
    .filter((p) => !p.is_host)
    .map((p) => ({
      name: p.name,
      connected: p.connected,
      score: 0,
      avatar: p.avatar || null,
    }));

  for (const p of players) {
    p.score = Number(await redis.zscore(leaderboardKey(pin), p.name)) || 0;
  }

  io.to(pin).emit('players_update', { players });
}

/** Read avatar from Redis player hash (for leaderboard / game_ended). */
async function getPlayerAvatar(redis, pin, name) {
  const player = await getPlayer(redis, pin, name);
  return player?.avatar || null;
}

/**
 * Refresh TTL on every key belonging to this room, not just the 3 primary
 * ones — auxiliary per-question keys (plan, finalized:*, option_order:*,
 * answer:*, prev_scores, submitted:*) were previously stamped once at
 * creation and could expire mid-session while the primary keys lived on.
 */
async function refreshRoomTtl(redis, pin) {
  const roomKeys = await scanKeys(redis, `room:${pin}*`);
  const submittedKeys = await scanKeys(redis, `submitted:${pin}:*`);
  const keys = [...roomKeys, leaderboardKey(pin), ...submittedKeys];
  await Promise.all(keys.map((key) => redis.expire(key, ROOM_TTL)));
}

async function getPlayer(redis, pin, name) {
  const raw = await redis.hget(playersKey(pin), name);
  return raw ? JSON.parse(raw) : null;
}

async function savePlayer(redis, pin, player) {
  await redis.hset(playersKey(pin), player.name, JSON.stringify(player));
  await refreshRoomTtl(redis, pin);
}

module.exports = {
  registerRoomHandlers,
  resolvePlayToken,
  getConnectedStudents,
  getHostSockets,
  broadcastPlayerList,
  refreshRoomTtl,
  getPlayer,
  getPlayerAvatar,
  savePlayer,
};
