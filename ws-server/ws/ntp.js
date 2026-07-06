const MAX_CLOCK_SKEW_MS = 500;

function registerNtpHandlers(socket) {
  socket.on('ntp_ping', (payload = {}) => {
    const t0 = payload.t0;
    const t1 = Date.now();
    socket.emit('ntp_pong', { t0, t1, t2: Date.now() });
  });
}

/**
 * Client hybrid_timestamp should align with server clock within tolerance.
 */
function validateHybridTimestamp(hybridTimestamp) {
  if (hybridTimestamp == null || Number.isNaN(Number(hybridTimestamp))) {
    return { ok: false, message: 'Thiếu hybrid_timestamp hợp lệ.' };
  }

  const skew = Math.abs(Number(hybridTimestamp) - Date.now());
  if (skew > MAX_CLOCK_SKEW_MS) {
    return {
      ok: false,
      message: `Đồng hồ lệch ${skew}ms (cho phép tối đa ${MAX_CLOCK_SKEW_MS}ms). Hãy đồng bộ NTP lại.`,
    };
  }

  return { ok: true };
}

module.exports = {
  registerNtpHandlers,
  validateHybridTimestamp,
  MAX_CLOCK_SKEW_MS,
};
