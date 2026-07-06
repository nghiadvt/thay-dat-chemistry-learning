/**
 * Socket.io client + NTP sync wrapper.
 * Requires socket.io CDN loaded before this file.
 */
const HTDSocket = (function () {
  let socket = null;
  let ntpOffset = 0;
  let connected = false;

  function wsUrl() {
    return (window.HTD_CONFIG && window.HTD_CONFIG.wsUrl) || 'http://localhost:38581';
  }

  function ensureIo() {
    if (typeof io === 'undefined') {
      throw new Error('Socket.io client chưa được load.');
    }
  }

  function connect() {
    ensureIo();
    if (socket?.connected) return socket;
    socket = io(wsUrl(), { transports: ['websocket', 'polling'] });
    socket.on('connect', () => {
      connected = true;
    });
    socket.on('disconnect', () => {
      connected = false;
    });
    return socket;
  }

  function getSocket() {
    return socket || connect();
  }

  function emit(event, payload) {
    return new Promise((resolve, reject) => {
      getSocket().emit(event, payload || {}, res => {
        if (res && res.success === false) reject(new Error(res.error || 'Socket error'));
        else resolve(res?.data ?? res);
      });
    });
  }

  function on(event, handler) {
    getSocket().on(event, handler);
  }

  function off(event, handler) {
    getSocket().off(event, handler);
  }

  async function syncNtp(rounds = 3) {
    const offsets = [];
    const sock = getSocket();

    function pingOnce() {
      return new Promise(resolve => {
        const t0 = Date.now();
        const onPong = data => {
          sock.off('ntp_pong', onPong);
          const t3 = Date.now();
          const t1 = Number(data?.t1 ?? t3);
          const t2 = Number(data?.t2 ?? t3);
          resolve((t1 - t0 + (t2 - t3)) / 2);
        };
        sock.on('ntp_pong', onPong);
        sock.emit('ntp_ping', { t0 });
      });
    }

    for (let i = 0; i < rounds; i += 1) {
      offsets.push(await pingOnce());
    }
    offsets.sort((a, b) => a - b);
    ntpOffset = offsets[Math.floor(offsets.length / 2)] || 0;
    return ntpOffset;
  }

  function hybridTimestamp() {
    return Date.now() + ntpOffset;
  }

  function getNtpOffset() {
    return ntpOffset;
  }

  function isConnected() {
    return connected;
  }

  function disconnect() {
    if (socket) {
      socket.disconnect();
      socket = null;
      connected = false;
    }
  }

  return {
    connect,
    getSocket,
    emit,
    on,
    off,
    syncNtp,
    hybridTimestamp,
    getNtpOffset,
    isConnected,
    disconnect,
  };
})();
