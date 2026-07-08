(function () {
  const boot = window.ADMIN_BOOT && window.ADMIN_BOOT.session;
  if (!boot || !boot.pin) return;

  function start() {
    if (typeof joinExistingRoomFromAdmin !== 'function') {
      console.error('admin-session-init: teacher.js not loaded');
      return;
    }
    if (window.HTD) {
      HTD.setPlayers([]);
    }
    const params = new URLSearchParams({
      game_id: String(boot.gameId || ''),
      session_id: String(boot.sessionId || ''),
      from: 'admin',
    });
    joinExistingRoomFromAdmin(boot.pin, params);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
