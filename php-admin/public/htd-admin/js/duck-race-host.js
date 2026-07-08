/**
 * Host UI — đường đua vịt (play_mode: duck_race)
 */
const DuckRaceHost = (function () {
  const DUCK_IMG = '/htd-admin/assets/duck-race/duck-blue.png';
  let lastPlayers = [];
  let targetScore = 30;

  function isDuckRaceMode() {
    const boot = window.ADMIN_BOOT?.session;
    const room = typeof HTD !== 'undefined' ? HTD.getRoom() : null;
    return boot?.playModeSlug === 'duck_race' || room?.playModeSlug === 'duck_race';
  }

  function getModeConfig() {
    return window.ADMIN_BOOT?.session?.modeConfig
      || (typeof HTD !== 'undefined' ? HTD.getRoom()?.modeConfig : null)
      || {};
  }

  function duckXPercent(score) {
    const target = targetScore || getModeConfig()?.win?.target_score || 30;
    const pct = (Number(score) / target) * 100;
    return Math.max(0, Math.min(88, pct));
  }

  function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
  }

  function renderAvatar(player) {
    if (player.avatar && String(player.avatar).startsWith('data:image')) {
      return `<img class="duck-race-duck-badge__avatar" src="${player.avatar}" alt="">`;
    }
    return '';
  }

  function renderLanes() {
    const lanes = document.getElementById('duckRaceLanes');
    if (!lanes) return;

    if (!lastPlayers.length) {
      lanes.innerHTML = '<p style="color:#fff;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.4)">Chờ học sinh tham gia…</p>';
      return;
    }

    lanes.innerHTML = lastPlayers.map((p) => {
      const x = duckXPercent(p.score);
      const rankHtml = p.finish_rank
        ? `<span class="duck-race-duck-badge__rank">🏁 Về đích #${p.finish_rank}</span>`
        : '';
      return `
        <div class="duck-race-lane">
          <div class="duck-race-duck-wrap${p.finished ? ' finished' : ''}" style="--duck-x:${x}%">
            <div class="duck-race-duck-badge">
              ${renderAvatar(p)}<span class="duck-race-duck-badge__name">${escapeHtml(p.name)}</span>
              <span class="duck-race-duck-badge__score">${p.score} điểm</span>
              ${rankHtml}
            </div>
            <img class="duck-race-duck-sprite" src="${DUCK_IMG}" alt="">
          </div>
        </div>`;
    }).join('');
  }

  function renderPodium() {
    const el = document.getElementById('duckRacePodium');
    if (!el) return;
    const finished = lastPlayers.filter((p) => p.finish_rank).sort((a, b) => a.finish_rank - b.finish_rank);
    if (!finished.length) {
      el.innerHTML = '';
      return;
    }
    const medals = ['🥇', '🥈', '🥉'];
    el.innerHTML = finished.map((p) => `
      <div class="duck-race-podium-chip">
        <span class="medal">${medals[p.finish_rank - 1] || '🏁'}</span>
        <span>${escapeHtml(p.name)} — ${p.score} điểm</span>
      </div>
    `).join('');
  }

  function applyRaceUpdate(data) {
    if (!data) return;
    lastPlayers = Array.isArray(data.players) ? data.players : [];
    targetScore = Number(data.target_score || getModeConfig()?.win?.target_score || 30);
    const targetEl = document.getElementById('duckRaceTargetScore');
    if (targetEl) targetEl.textContent = String(targetScore);
    renderLanes();
    renderPodium();
    setGridMode(true);
  }

  function setGridMode(active) {
    const grid = document.querySelector('.teacher-main-grid');
    if (!grid) return;
    grid.classList.toggle('duck-race-mode', Boolean(active && isDuckRaceMode()));
  }

  function showPhase() {
    const phase = document.getElementById('teacherDuckRacePhase');
    const question = document.getElementById('teacherQuestionPhase');
    if (phase) phase.hidden = false;
    if (question) question.hidden = true;
    const typeEl = document.getElementById('teacherQType');
    if (typeEl) typeEl.textContent = 'Đua vịt';
    const targetEl = document.getElementById('duckRaceTargetScore');
    if (targetEl) {
      targetEl.textContent = String(getModeConfig()?.win?.target_score || targetScore || 30);
    }
    setGridMode(true);
    renderLanes();
  }

  function hidePhase() {
    const phase = document.getElementById('teacherDuckRacePhase');
    const question = document.getElementById('teacherQuestionPhase');
    if (phase) phase.hidden = true;
    if (question) question.hidden = false;
    setGridMode(false);
  }

  function onPlayerFinished(data) {
    if (!data?.name) return;
    const idx = lastPlayers.findIndex((p) => p.name === data.name);
    if (idx >= 0) {
      lastPlayers[idx] = {
        ...lastPlayers[idx],
        score: data.total_score ?? lastPlayers[idx].score,
        finished: true,
        finish_rank: data.finish_rank,
      };
    }
    renderLanes();
    renderPodium();
  }

  return {
    isDuckRaceMode,
    showPhase,
    hidePhase,
    applyRaceUpdate,
    onPlayerFinished,
    setGridMode,
  };
})();

window.DuckRaceHost = DuckRaceHost;
