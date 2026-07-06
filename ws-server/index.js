const http = require('http');
const express = require('express');
const { Server } = require('socket.io');
const { createAdapter } = require('@socket.io/redis-adapter');
const { createRedisClient } = require('./ws/redis');
const { registerRoomHandlers } = require('./ws/room');
const { registerNtpHandlers } = require('./ws/ntp');
const { registerGameplayHandlers } = require('./ws/gameplay');

const PORT = Number(process.env.WS_PORT || 38581);

async function main() {
  const app = express();

  app.get('/health', (_req, res) => {
    res.json({ ok: true, service: 'ws-server' });
  });

  const server = http.createServer(app);
  const io = new Server(server, {
    cors: { origin: '*' },
  });

  const pubClient = createRedisClient('pub');
  const subClient = createRedisClient('sub');
  const redis = createRedisClient('data');

  pubClient.on('error', (err) => {
    console.error('[redis-pub] error (non-fatal):', err.message);
  });
  subClient.on('error', (err) => {
    console.error('[redis-sub] error (non-fatal):', err.message);
  });
  redis.on('error', (err) => {
    console.error('[redis-data] error (non-fatal):', err.message);
  });

  await Promise.all([
    pubClient.connect(),
    subClient.connect(),
    redis.connect(),
  ]);
  console.log('Connected to Redis');

  io.adapter(createAdapter(pubClient, subClient));

  io.on('connection', (socket) => {
    registerNtpHandlers(socket);
  });

  registerRoomHandlers(io, redis);
  registerGameplayHandlers(io, redis);

  server.listen(PORT, () => {
    console.log(`Socket.io listening on ${PORT}`);
  });
}

main().catch((err) => {
  console.error('Failed to start ws-server:', err);
  process.exit(1);
});
