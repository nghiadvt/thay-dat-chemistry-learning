const Redis = require('ioredis');

function createRedisClient(label = 'redis') {
  const host = process.env.REDIS_HOST || 'redis';
  const port = Number(process.env.REDIS_PORT || 6379);

  const client = new Redis({
    host,
    port,
    maxRetriesPerRequest: null,
    lazyConnect: true,
  });

  client.on('error', (err) => {
    console.error(`[${label}] Redis error:`, err.message);
  });

  return client;
}

module.exports = { createRedisClient };
