/**
 * Laravel API client (session cookie + CSRF).
 */
window.HTDApi = (function () {
  const cfg = () => window.HTD_CONFIG || {};

  function apiUrl(path) {
    const base = (cfg().apiBase || '').replace(/\/$/, '');
    return `${base}${path.startsWith('/') ? path : `/${path}`}`;
  }

  function readCookie(name) {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
  }

  async function ensureCsrf() {
    if (readCookie('XSRF-TOKEN')) return;
    await fetch(apiUrl('/login'), { credentials: 'include' });
  }

  async function request(path, options = {}) {
    const headers = Object.assign(
      { Accept: 'application/json', 'Content-Type': 'application/json' },
      options.headers || {}
    );
    const token = readCookie('XSRF-TOKEN');
    if (token && !options.skipCsrf) {
      headers['X-XSRF-TOKEN'] = token;
    }

    const res = await fetch(apiUrl(path), {
      method: options.method || 'GET',
      credentials: 'include',
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
    });

    let json;
    try {
      json = await res.json();
    } catch {
      throw new Error(`API không trả JSON hợp lệ (${res.status}).`);
    }

    if (!res.ok || json.success === false) {
      throw new Error(json.error || `API lỗi ${res.status}`);
    }
    return json.data;
  }

  return {
    apiUrl,
    async checkPin(pin) {
      return request(`/api/rooms/${encodeURIComponent(pin)}`, { skipCsrf: true });
    },
    async listGames() {
      await ensureCsrf();
      const data = await request('/api/games');
      return data.games || data || [];
    },
    async createGameSession(gameId) {
      await ensureCsrf();
      return request('/api/game-sessions', {
        method: 'POST',
        body: { game_id: gameId },
      });
    },
    async exportSessionCsv(sessionId) {
      window.open(apiUrl(`/admin/reports/${sessionId}/export`), '_blank');
    },
    async getKeyboard(id) {
      await ensureCsrf();
      const data = await request(`/api/keyboards/${id}`);
      return data.keyboard || data;
    },
    async updateKeyboard(id, body) {
      await ensureCsrf();
      const data = await request(`/api/keyboards/${id}`, {
        method: 'PUT',
        body,
      });
      return data.keyboard || data;
    },
    async uploadKeyboardPreview(id, imageDataUrl) {
      await ensureCsrf();
      const data = await request(`/api/keyboards/${id}/preview`, {
        method: 'POST',
        body: { image: imageDataUrl },
      });
      return data;
    },
    loginUrl(redirectPath) {
      const redirect = encodeURIComponent(redirectPath || location.pathname);
      return `${apiUrl('/login')}?redirect=${redirect}`;
    },
  };
})();
