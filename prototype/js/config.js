/**
 * Runtime config — auto-detect local Docker ports.
 * Demo mode: add ?demo=1 to URL to keep localStorage prototype flow.
 */
(function initHtdConfig(global) {
  const params = new URLSearchParams(global.location.search);
  const host = global.location.hostname || 'localhost';
  const isLocal = host === 'localhost' || host === '127.0.0.1';
  const sameOriginApi = global.location.origin;

  const defaults = {
    apiBase: isLocal ? `http://${host}:38480` : sameOriginApi,
    wsUrl: isLocal ? `http://${host}:38581` : sameOriginApi,
    useBackend: !params.has('demo'),
  };

  if (!params.has('demo') && global.location.port === '38480') {
    defaults.apiBase = sameOriginApi;
  }

  global.HTD_CONFIG = Object.assign(defaults, global.HTD_CONFIG || {});
})(window);
