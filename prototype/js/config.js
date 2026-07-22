/**
 * Runtime config — auto-detect local Docker ports.
 * Demo mode: add ?demo=1 to URL to keep localStorage prototype flow.
 *
 * Local Docker: PHP :38480 + WS :38581. When opened via LAN IP
 * (phone on same Wi‑Fi), keep API on current origin and WS on :38581.
 */
(function initHtdConfig(global) {
  const params = new URLSearchParams(global.location.search);
  const host = global.location.hostname || 'localhost';
  const port = global.location.port || '';
  const isLoopback = host === 'localhost' || host === '127.0.0.1';
  const sameOriginApi = global.location.origin;
  const wsOnDockerPort = `http://${host}:38581`;

  // PHP on 38480 (or loopback default) → companion WS on 38581.
  // Production (same host/port for both) → same-origin WS.
  const useSplitLocalPorts = isLoopback || port === '38480';

  const defaults = {
    apiBase: isLoopback ? `http://${host}:38480` : sameOriginApi,
    wsUrl: useSplitLocalPorts ? wsOnDockerPort : sameOriginApi,
    useBackend: !params.has('demo'),
  };

  if (!params.has('demo') && port === '38480') {
    defaults.apiBase = sameOriginApi;
  }

  global.HTD_CONFIG = Object.assign(defaults, global.HTD_CONFIG || {});
})(window);
