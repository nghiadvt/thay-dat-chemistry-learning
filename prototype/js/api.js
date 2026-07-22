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
    // FormData tự đặt Content-Type kèm boundary — ép JSON sẽ làm hỏng upload.
    const isForm = options.body instanceof FormData;
    const headers = Object.assign(
      isForm ? { Accept: 'application/json' } : { Accept: 'application/json', 'Content-Type': 'application/json' },
      options.headers || {}
    );
    const token = readCookie('XSRF-TOKEN');
    if (token && !options.skipCsrf) {
      headers['X-XSRF-TOKEN'] = token;
    }

    let body;
    if (options.body) {
      body = isForm ? options.body : JSON.stringify(options.body);
    }

    const res = await fetch(apiUrl(path), {
      method: options.method || 'GET',
      credentials: 'include',
      headers,
      body,
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
    async elementsTable() {
      return request('/api/elements/table', { skipCsrf: true });
    },
    async practiceTopics({ grade } = {}) {
      const params = new URLSearchParams();
      if (grade) params.set('grade', grade);
      const qs = params.toString();
      return request(`/api/practice/topics${qs ? `?${qs}` : ''}`, { skipCsrf: true });
    },
    async practiceQuestions({ grade, topic, count } = {}) {
      const params = new URLSearchParams();
      if (grade) params.set('grade', grade);
      if (topic) params.set('topic', topic);
      if (count) params.set('count', String(count));
      const qs = params.toString();
      return request(`/api/practice/questions${qs ? `?${qs}` : ''}`, { skipCsrf: true });
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
    // ─── Tài khoản học sinh ───
    /** Trả về hồ sơ học sinh đang đăng nhập, hoặc null nếu chưa đăng nhập. */
    async studentMe() {
      try {
        return await request('/api/student/me', { skipCsrf: true });
      } catch {
        return null;
      }
    },
    /** Token ngắn hạn để ws-server xác định tài khoản học sinh khi vào phòng. */
    async studentPlayToken() {
      await ensureCsrf();
      return request('/api/student/play-token', { method: 'POST' });
    },
    /** Mở một lượt tự luyện; trả về attempt_id để nộp bài sau. */
    async studentStartAttempt({ featureKey, label, topicSlug, gradeSlug, questionIds }) {
      await ensureCsrf();
      return request('/api/student/practice-attempts', {
        method: 'POST',
        body: {
          feature_key: featureKey,
          label,
          topic_slug: topicSlug,
          grade_slug: gradeSlug,
          question_ids: questionIds,
        },
      });
    },
    /** Nộp bài lượt tự luyện; server tự chấm lại. */
    async studentFinishAttempt(attemptId, { answers, durationMs }) {
      await ensureCsrf();
      return request(`/api/student/practice-attempts/${attemptId}/finish`, {
        method: 'POST',
        body: { answers, duration_ms: durationMs },
      });
    },
    /** Quyền tính năng của học sinh đang đăng nhập. */
    async studentEntitlements() {
      return request('/api/student/entitlements', { skipCsrf: true });
    },
    async studentUpdateProfile({ displayName, currentPassword }) {
      await ensureCsrf();
      return request('/api/student/profile', {
        method: 'PATCH',
        body: { display_name: displayName, current_password: currentPassword },
      });
    },
    async studentUpdatePassword({ currentPassword, password, passwordConfirmation }) {
      await ensureCsrf();
      return request('/api/student/password', {
        method: 'PUT',
        body: {
          current_password: currentPassword,
          password,
          password_confirmation: passwordConfirmation,
        },
      });
    },
    async studentUploadAvatar({ file, currentPassword }) {
      await ensureCsrf();
      const form = new FormData();
      form.append('avatar', file);
      form.append('current_password', currentPassword);
      return request('/api/student/avatar', { method: 'POST', body: form });
    },
    studentLoginUrl() {
      return apiUrl('/login');
    },
    /** Route logout trả redirect chứ không phải JSON nên gọi fetch trực tiếp. */
    async studentLogout() {
      await ensureCsrf();
      await fetch(apiUrl('/student/logout'), {
        method: 'POST',
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') },
      });
    },
    loginUrl(redirectPath) {
      const redirect = encodeURIComponent(redirectPath || location.pathname);
      return `${apiUrl('/login')}?redirect=${redirect}`;
    },
  };
})();
