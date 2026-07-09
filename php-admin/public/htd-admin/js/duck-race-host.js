/**
 * Host UI — đường đua vịt (play_mode: duck_race)
 */
const DuckRaceHost = (function () {
  const DUCK_ASSET_BASE = '/htd-admin/assets/duck-race/';
  const DUCK_FALLBACK = `${DUCK_ASSET_BASE}ducks/duck-blue.gif`;
  const COLLISION_SPREAD_PX = 14;
  let lastPlayers = [];
  let targetScore = 30;
  let trackLayoutObserver = null;

  function isDuckRaceMode() {
    const boot = window.ADMIN_BOOT?.session;
    const room = typeof HTD !== 'undefined' ? HTD.getRoom() : null;
    return boot?.playModeSlug === 'duck_race' || room?.playModeSlug === 'duck_race';
  }

  /** Vùng ảnh thực sau object-fit: contain + object-position: center bottom. */
  function getRenderedImageContentRect(img) {
    const rect = img.getBoundingClientRect();
    const nw = img.naturalWidth;
    const nh = img.naturalHeight;
    if (!nw || !nh) {
      return { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
    }
    const scale = Math.min(rect.width / nw, rect.height / nh);
    const width = nw * scale;
    const height = nh * scale;
    return {
      left: rect.left + (rect.width - width) / 2,
      top: rect.top + rect.height - height,
      width,
      height,
    };
  }

  function getModeConfig() {
    return window.ADMIN_BOOT?.session?.modeConfig
      || (typeof HTD !== 'undefined' ? HTD.getRoom()?.modeConfig : null)
      || {};
  }

  function getLaneBounds() {
    const visual = getModeConfig()?.visual || {};
    const bounds = visual.lane_bounds || {};
    return {
      top: Number(bounds.top_pct ?? 50),
      bottom: Number(bounds.bottom_pct ?? 92),
    };
  }

  function syncTrackLanesLayout() {
    const frame = document.querySelector('.duck-race-track__frame');
    const bg = frame?.querySelector('.duck-race-track__bg');
    const pond = document.getElementById('duckRaceLanes');
    if (!frame || !bg || !pond) return;

    const frameRect = frame.getBoundingClientRect();
    const content = getRenderedImageContentRect(bg);
    const left = content.left - frameRect.left;
    const top = content.top - frameRect.top;
    const { top: laneTopPct, bottom: laneBottomPct } = getLaneBounds();
    const laneTop = top + content.height * (laneTopPct / 100);
    const laneBottom = top + content.height * (laneBottomPct / 100);

    pond.style.left = `${left}px`;
    pond.style.top = `${laneTop}px`;
    pond.style.width = `${content.width}px`;
    pond.style.height = `${Math.max(40, laneBottom - laneTop)}px`;
  }

  function bindTrackLayout() {
    const track = document.getElementById('duckRaceTrack');
    const bg = track?.querySelector('.duck-race-track__bg');
    if (!track || !bg || trackLayoutObserver) return;

    const run = () => {
      syncTrackLanesLayout();
      renderDucks();
    };

    if (bg.complete) run();
    else bg.addEventListener('load', run, { once: true });

    trackLayoutObserver = new ResizeObserver(run);
    trackLayoutObserver.observe(track);
    trackLayoutObserver.observe(bg);
  }

  function getDuckSpritePx() {
    const visual = getModeConfig()?.visual || {};
    const px = Number(visual.duck_sprite_px);
    return Number.isFinite(px) ? clamp(px, 32, 128) : 64;
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function duckImgUrl(sprite) {
    if (!sprite) return DUCK_FALLBACK;
    const path = String(sprite).replace(/^\//, '');
    return path.startsWith('htd-admin/') ? `/${path}` : `${DUCK_ASSET_BASE}${path}`;
  }

  function getTrackBounds() {
    const visual = getModeConfig()?.visual || {};
    const bounds = visual.track_bounds || {};
    return {
      start: Number(bounds.start_pct ?? 20),
      end: Number(bounds.end_pct ?? 90),
    };
  }

  function scoreToProgress(player) {
    if (player.finished) return 1;
    const target = targetScore || getModeConfig()?.win?.target_score || 30;
    if (player.position != null) {
      return Math.max(0, Math.min(1, Number(player.position) / 100));
    }
    const forwardSteps = Math.max(0, Number(player.score));
    return Math.min(1, forwardSteps / target);
  }

  function duckLeftPercent(player) {
    const { start, end } = getTrackBounds();
    const progress = scoreToProgress(player);
    return start + progress * (end - start);
  }

  function getSingleLaneBottomPct() {
    return 12;
  }

  function computeCollisionOffsets(players) {
    const offsets = new Map();
    const groups = new Map();
    for (const p of players) {
      const key = Math.round(duckLeftPercent(p) * 2) / 2;
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(p);
    }
    for (const group of groups.values()) {
      if (group.length <= 1) {
        offsets.set(group[0].name, 0);
        continue;
      }
      const sorted = [...group].sort((a, b) => a.name.localeCompare(b.name));
      sorted.forEach((p, i) => {
        const center = (sorted.length - 1) / 2;
        offsets.set(p.name, (i - center) * COLLISION_SPREAD_PX);
      });
    }
    return offsets;
  }

  function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
  }

  function getDuckSwimMs() {
    const visual = getModeConfig()?.visual || {};
    const ms = Number(visual.duck_swim_ms);
    return Number.isFinite(ms) ? clamp(ms, 400, 3000) : 1150;
  }

  function applyDuckSize(wrap) {
    if (!wrap) return;
    const px = getDuckSpritePx();
    wrap.style.setProperty('--duck-sprite-px', `${px}px`);
    wrap.style.width = `${Math.max(px + 8, 72)}px`;
    const sprite = wrap.querySelector('.duck-race-duck-sprite');
    if (sprite) {
      sprite.style.width = `${px}px`;
      sprite.style.height = `${px}px`;
    }
  }

  function applyDuckSwimStyle(wrap) {
    if (!wrap) return;
    wrap.style.transition = `left ${getDuckSwimMs()}ms ease-in-out`;
  }

  function setDuckPosition(wrap, leftPct, collisionPx) {
    if (!wrap) return;
    applyDuckSwimStyle(wrap);
    const nextLeft = `${leftPct}%`;
    const prevLeft = wrap.dataset.duckLeft;
    wrap.style.left = nextLeft;
    wrap.style.setProperty('--duck-collision-x', `${collisionPx || 0}px`);
    if (prevLeft != null && prevLeft !== nextLeft) {
      const ms = getDuckSwimMs();
      wrap.classList.add('is-swimming');
      clearTimeout(wrap._swimTimer);
      wrap._swimTimer = setTimeout(() => {
        wrap.classList.remove('is-swimming');
      }, ms + 80);
    }
    wrap.dataset.duckLeft = nextLeft;
  }

  function formatScore(score) {
    const n = Number(score);
    return Number.isFinite(n) ? String(n) : '0';
  }

  function updateDuckElement(wrap, p, collisionPx) {
    const leftPct = duckLeftPercent(p);
    wrap.classList.toggle('finished', Boolean(p.finished));
    setDuckPosition(wrap, leftPct, collisionPx);
    applyDuckSize(wrap);

    const nameEl = wrap.querySelector('.duck-race-duck-badge__name');
    const scoreEl = wrap.querySelector('.duck-race-duck-badge__score');
    if (nameEl) nameEl.textContent = p.name ?? '';
    if (scoreEl) scoreEl.textContent = `${formatScore(p.score)} điểm`;

    let rankEl = wrap.querySelector('.duck-race-duck-badge__rank');
    if (p.finish_rank) {
      if (!rankEl) {
        rankEl = document.createElement('span');
        rankEl.className = 'duck-race-duck-badge__rank';
        wrap.querySelector('.duck-race-duck-badge')?.appendChild(rankEl);
      }
      rankEl.textContent = `🏁 Về đích #${p.finish_rank}`;
    } else if (rankEl) {
      rankEl.remove();
    }

    const avatarEl = wrap.querySelector('.duck-race-duck-badge__avatar');
    if (p.avatar && String(p.avatar).startsWith('data:image')) {
      if (!avatarEl) {
        const img = document.createElement('img');
        img.className = 'duck-race-duck-badge__avatar';
        img.alt = '';
        wrap.querySelector('.duck-race-duck-badge')?.prepend(img);
        img.src = p.avatar;
      } else if (avatarEl.src !== p.avatar) {
        avatarEl.src = p.avatar;
      }
    } else if (avatarEl) {
      avatarEl.remove();
    }

    const spriteEl = wrap.querySelector('.duck-race-duck-sprite');
    const src = duckImgUrl(p.duck_sprite);
    if (spriteEl) spriteEl.src = src;
  }

  function createDuckElement(p, collisionPx) {
    const { start } = getTrackBounds();
    const wrap = document.createElement('div');
    wrap.className = `duck-race-duck-wrap${p.finished ? ' finished' : ''}`;
    wrap.dataset.playerName = p.name;
    wrap.innerHTML = `
      <div class="duck-race-duck-badge">
        <span class="duck-race-duck-badge__name"></span>
        <span class="duck-race-duck-badge__score"></span>
      </div>
      <img class="duck-race-duck-sprite" src="${duckImgUrl(p.duck_sprite)}" alt="">`;
    wrap.style.left = `${start}%`;
    wrap.style.bottom = `${getSingleLaneBottomPct()}%`;
    updateDuckElement(wrap, p, collisionPx);
    return wrap;
  }

  function renderDucks() {
    const pond = document.getElementById('duckRaceLanes');
    if (!pond) return;

    if (!lastPlayers.length) {
      pond.innerHTML = '<p class="duck-race-pond-empty">Chờ học sinh tham gia…</p>';
      return;
    }

    const emptyMsg = pond.querySelector('.duck-race-pond-empty');
    if (emptyMsg) emptyMsg.remove();

    const collisionOffsets = computeCollisionOffsets(lastPlayers);
    const laneBottomPct = getSingleLaneBottomPct();

    const existing = new Map(
      [...pond.querySelectorAll('.duck-race-duck-wrap')].map((el) => [el.dataset.playerName, el]),
    );
    const nextNames = new Set(lastPlayers.map((p) => p.name));

    for (const p of lastPlayers) {
      const collisionPx = collisionOffsets.get(p.name) ?? 0;
      let wrap = existing.get(p.name);
      if (!wrap) {
        wrap = createDuckElement(p, collisionPx);
        pond.appendChild(wrap);
      } else {
        wrap.style.bottom = `${laneBottomPct}%`;
        updateDuckElement(wrap, p, collisionPx);
      }
    }

    for (const [name, el] of existing) {
      if (!nextNames.has(name)) el.remove();
    }
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
    bindTrackLayout();
    syncTrackLanesLayout();
    renderDucks();
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
    bindTrackLayout();
    syncTrackLanesLayout();
    setGridMode(true);
    renderDucks();
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
    renderDucks();
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
