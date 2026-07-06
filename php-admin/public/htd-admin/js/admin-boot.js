(function () {
  const boot = window.ADMIN_BOOT || {};
  const host = window.location.hostname || 'localhost';
  window.HTD_CONFIG = {
    apiBase: boot.apiBase || window.location.origin,
    wsUrl: boot.wsUrl || ('http://' + host + ':38581'),
    useBackend: true,
  };
})();
