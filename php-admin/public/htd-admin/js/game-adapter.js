/**
 * Map backend payloads (API / WebSocket) to UI question model used by prototype screens.
 */
const HTDGameAdapter = (function () {
  function stripHtml(html) {
    const el = document.createElement('div');
    el.innerHTML = html || '';
    return (el.textContent || el.innerText || '').trim();
  }

  function structuredTemplateToUi(template, contentHtml) {
    if (Array.isArray(template)) return template;

    const parts = [];
    const text = stripHtml(contentHtml);
    if (text) parts.push({ t: 'txt', text: `${text} ` });

    if (template && typeof template === 'object') {
      if (Array.isArray(template.coef)) {
        template.coef.forEach((id, idx) => {
          if (idx > 0) parts.push({ t: 'txt', text: ' ' });
          parts.push({ t: 'coef', id });
        });
      }
      if (Array.isArray(template.blank)) {
        template.blank.forEach((id, idx) => {
          if (parts.length) parts.push({ t: 'txt', text: ' ' });
          parts.push({ t: 'blank', id });
        });
      }
      if (parts.length > (text ? 1 : 0)) return parts;
    }

    return parts.length
      ? parts
      : [
          { t: 'txt', text: text ? `${text} ` : '' },
          { t: 'blank', id: 'b0' },
        ];
  }

  function mapNewQuestion(payload, index) {
    const base = {
      id: String(payload.question_id),
      quizId: payload.quiz_id,
      prompt: stripHtml(payload.content),
      contentHtml: payload.content,
      timeLimit: Number(payload.time_limit || 30),
      serverTime: Number(payload.server_time || Date.now()),
      keyboardConfig: payload.keyboard_config || null,
      index: typeof index === 'number' ? index : 0,
    };

    switch (payload.answer_type) {
      case 'mc':
        return {
          ...base,
          type: 'mc',
          media: 'text',
          options: payload.options || [],
        };
      case 'essay':
        return {
          ...base,
          type: 'input',
          inputMode: 'essay',
          media: 'text',
          template: [{ t: 'blank', id: 'b0' }],
        };
      default:
        return {
          ...base,
          type: 'mc',
          media: 'text',
          options: payload.options || ['—'],
        };
    }
  }

  function buildSubmitPayload(question, uiState) {
    if (!question) return null;

    if (question.type === 'mc') {
      if (uiState.selectedAnswer === null || uiState.selectedAnswer === undefined) {
        return { index: -1 };
      }
      return { index: uiState.selectedAnswer };
    }

    if (question.type === 'input') {
      const blank = uiState.inputValues?.blank || {};
      const first = Object.values(blank)[0] || '';
      return { text: first };
    }

    return uiState.answer ?? null;
  }

  function mapPlayersUpdate(players) {
    return (players || []).map((p, idx) => ({
      id: `p-${p.name}-${idx}`,
      name: p.name,
      score: Number(p.score || 0),
      avatarEmoji: '😀',
      connected: p.connected !== false,
      isFake: false,
    }));
  }

  function mapLeaderboard(top5) {
    return (top5 || []).map((row, idx) => ({
      id: `lb-${row.name}-${idx}`,
      name: row.name,
      score: Number(row.score || 0),
      delta: Number(row.delta || 0),
      avatarEmoji: '😀',
      isFake: false,
    }));
  }

  return {
    stripHtml,
    mapNewQuestion,
    buildSubmitPayload,
    mapPlayersUpdate,
    mapLeaderboard,
  };
})();
