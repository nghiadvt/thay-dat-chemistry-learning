const { randomUUID } = require('crypto');
const {
  ROOM_TTL,
  roomKey,
  playersKey,
  leaderboardKey,
  submittedKey,
} = require('./redis-keys');
const { getSessionByPin } = require('./db');

const MAX_NAME_LENGTH = 20;

function registerRoomHandlers(io, redis) {
  io.on('connection', (socket) => {
    socket.on('join_room', async (payload = {}, ack) => {
      try {
        const result = await handleJoinRoom(io, redis, socket, payload);
        socket.emit('room_joined', result);
        if (typeof ack === 'function') ack({ success: true, data: result });
      } catch (err) {
        const message = err.message || 'Không thể vào phòng.';
        socket.emit('room_error', { message });
        if (typeof ack === 'function') ack({ success: false, error: message });
      }
    });

    socket.on('disconnect', async () => {
      await handleDisconnect(redis, socket);
    });
  });
}

async function handleJoinRoom(io, redis, socket, payload) {
  const pin = String(payload.pin || '').trim();
  const name = String(payload.name || '').trim();
  const isHost = Boolean(payload.is_host);

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
  if (room.status === 'ended') {
    throw new Error('Phòng đã kết thúc.');
  }

  if (isHost) {
    if (!session) {
      throw new Error('Không tìm thấy session cho PIN này.');
    }
  }

  const existingRaw = await redis.hget(playersKey(pin), name);
  let player;
  let reconnected = false;

  if (existingRaw) {
    player = JSON.parse(existingRaw);
    reconnected = true;
    player.connected = true;
    player.disconnected_at = null;
    player.socket_id = socket.id;
    player.is_host = isHost || player.is_host;
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
      };
    } else {
      player = {
        name,
        player_token: randomUUID(),
        connected: true,
        is_host: false,
        streak_correct: 0,
        socket_id: socket.id,
      };
      await redis.zadd(leaderboardKey(pin), 0, name);
    }
  }

  await redis.hset(playersKey(pin), name, JSON.stringify(player));
  await refreshRoomTtl(redis, pin);

  socket.data.pin = pin;
  socket.data.name = name;
  socket.data.isHost = player.is_host;
  socket.data.playerToken = player.player_token;

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
    }));

  for (const p of players) {
    p.score = Number(await redis.zscore(leaderboardKey(pin), p.name)) || 0;
  }

  io.to(pin).emit('players_update', { players });
}

async function refreshRoomTtl(redis, pin) {
  const keys = [
    roomKey(pin),
    playersKey(pin),
    leaderboardKey(pin),
  ];
  for (const key of keys) {
    await redis.expire(key, ROOM_TTL);
  }
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
  getConnectedStudents,
  getHostSockets,
  broadcastPlayerList,
  refreshRoomTtl,
  getPlayer,
  savePlayer,
};
