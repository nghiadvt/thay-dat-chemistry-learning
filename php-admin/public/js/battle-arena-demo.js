/**
 * Admin demo — Đấu Trường Hóa Học (battle arena theo đội).
 * Toàn bộ dữ liệu giả lập bằng JS để hình dung gameplay; chưa nối database.
 */
window.BattleArenaDemo = (function () {
  'use strict';

  /* ============================== Cấu hình & fake data ============================== */

  const CLASSES = {
    fire: {
      label: 'Hỏa Pháp Sư',
      robe: ['#ff8a5c', '#d2401e'],
      trim: '#8f2413',
      hat: '#e04b2b',
      hatBand: '#ffd166',
      orb: '#ffb347',
      orbCore: '#fff3c4',
      burst: ['#ffb347', '#ff6b35', '#ffd166', '#ff8a5c'],
    },
    ice: {
      label: 'Băng Pháp Sư',
      robe: ['#6fbcf8', '#2f6fd0'],
      trim: '#1d4f9e',
      hat: '#3b82d6',
      hatBand: '#dbeeff',
      orb: '#9fdcff',
      orbCore: '#ffffff',
      burst: ['#9fdcff', '#5cb3f0', '#e6f7ff', '#7cc6f5'],
    },
    alchemy: {
      label: 'Nhà Giả Kim',
      robe: ['#5ecb84', '#2e9457'],
      trim: '#1e6b3c',
      hat: '#37a35f',
      hatBand: '#ffe9a8',
      orb: '#b8f36b',
      orbCore: '#f2ffd9',
      burst: ['#b8f36b', '#5ecb84', '#e4ffb0', '#7fe09a'],
    },
    thunder: {
      label: 'Lôi Pháp Sư',
      robe: ['#a78bfa', '#7c3aed'],
      trim: '#5b21b6',
      hat: '#8b5cf6',
      hatBand: '#ffe75e',
      orb: '#ffe75e',
      orbCore: '#fffbe6',
      burst: ['#ffe75e', '#a78bfa', '#fff3a0', '#c4b5fd'],
    },
  };

  const FAKE_STUDENTS = [
    { name: 'Minh', cls: 'fire', team: 'red' },
    { name: 'Lan', cls: 'ice', team: 'red' },
    { name: 'Hùng', cls: 'thunder', team: 'red' },
    { name: 'Mai', cls: 'alchemy', team: 'red' },
    { name: 'Tuấn', cls: 'ice', team: 'blue' },
    { name: 'Hoa', cls: 'fire', team: 'blue' },
    { name: 'Nam', cls: 'alchemy', team: 'blue' },
    { name: 'Thu', cls: 'thunder', team: 'blue' },
  ];

  const TEAM_META = {
    red: { name: 'Rồng Lửa', icon: '🐉', color: '#e0492e' },
    blue: { name: 'Phượng Băng', icon: '🦅', color: '#2f6fd0' },
  };

  const COMBO_CAP = 5;

  function readNumber(id, fallback) {
    const el = document.getElementById(id);
    const n = Number(el?.value);
    return Number.isFinite(n) ? n : fallback;
  }

  function cfg() {
    return {
      teamHp: Math.max(20, readNumber('bat_team_hp', 100)),
      dmgCorrect: Math.max(1, readNumber('bat_dmg', 8)),
      comboStep: Math.max(0, readNumber('bat_combo_step', 2)),
      selfPenalty: Math.max(0, readNumber('bat_penalty', 4)),
    };
  }

  /* ============================== Vẽ nhân vật SVG ============================== */

  function staffWeapon(c, gid) {
    return `
      <path d="M76,74 Q88,80 94,88" stroke="${c.robe[1]}" stroke-width="9" stroke-linecap="round" fill="none"/>
      <line x1="97" y1="92" x2="105" y2="42" stroke="#8b5a2b" stroke-width="5" stroke-linecap="round"/>
      <circle cx="106" cy="36" r="13" fill="${c.orb}" opacity=".28" class="bat-orb-glow"/>
      <circle cx="106" cy="36" r="8.5" fill="url(#${gid}-orb)"/>
      <circle cx="103.5" cy="33" r="2.4" fill="#fff" opacity=".9"/>
      <circle cx="97" cy="92" r="6" fill="#ffdcb8"/>`;
  }

  function flaskWeapon(c, gid) {
    return `
      <path d="M76,74 Q88,76 96,82" stroke="${c.robe[1]}" stroke-width="9" stroke-linecap="round" fill="none"/>
      <circle cx="98" cy="84" r="6" fill="#ffdcb8"/>
      <g transform="rotate(14 103 66)">
        <rect x="99.5" y="46" width="7" height="9" rx="2" fill="#c9d6e2"/>
        <rect x="98" y="44" width="10" height="4" rx="2" fill="#9fb2c4"/>
        <path d="M97,54 Q86,74 103,77 Q120,74 109,54 Z" fill="url(#${gid}-orb)"/>
        <circle cx="100" cy="68" r="2.2" fill="#fff" opacity=".8" class="bat-bubble"/>
        <circle cx="106" cy="63" r="1.6" fill="#fff" opacity=".65" class="bat-bubble bat-bubble--slow"/>
      </g>`;
  }

  function characterSVG(key) {
    const c = CLASSES[key];
    const gid = `batG-${key}`;
    const weapon = key === 'alchemy' ? flaskWeapon(c, gid) : staffWeapon(c, gid);
    return `
<svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg" class="bat-svg" aria-hidden="true">
  <defs>
    <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${c.robe[0]}"/>
      <stop offset="1" stop-color="${c.robe[1]}"/>
    </linearGradient>
    <radialGradient id="${gid}-orb" cx=".5" cy=".38" r=".62">
      <stop offset="0" stop-color="${c.orbCore}"/>
      <stop offset="1" stop-color="${c.orb}"/>
    </radialGradient>
  </defs>
  <ellipse cx="60" cy="143" rx="30" ry="6" fill="rgba(20,30,60,.18)"/>
  <path d="M46,76 Q34,84 30,92" stroke="${c.robe[1]}" stroke-width="9" stroke-linecap="round" fill="none"/>
  <circle cx="29" cy="94" r="5.5" fill="#ffdcb8"/>
  <path d="M60,58 C40,60 32,92 27,130 Q60,142 93,130 C88,92 80,60 60,58 Z" fill="url(#${gid})"/>
  <path d="M27,130 Q60,142 93,130 L92,124 Q60,136 28,124 Z" fill="${c.trim}" opacity=".7"/>
  <rect x="41" y="98" width="38" height="8" rx="4" fill="${c.trim}"/>
  <circle cx="60" cy="102" r="4" fill="${c.hatBand}"/>
  <ellipse cx="48" cy="138" rx="9" ry="5" fill="${c.trim}"/>
  <ellipse cx="73" cy="138" rx="9" ry="5" fill="${c.trim}"/>
  ${weapon}
  <circle cx="60" cy="44" r="24" fill="#ffdcb8"/>
  <circle cx="52" cy="45" r="4.6" fill="#26303f"/>
  <circle cx="70" cy="45" r="4.6" fill="#26303f"/>
  <circle cx="53.6" cy="43.4" r="1.7" fill="#fff"/>
  <circle cx="71.6" cy="43.4" r="1.7" fill="#fff"/>
  <ellipse cx="44" cy="53" rx="4.5" ry="2.6" fill="#ff9d9d" opacity=".55"/>
  <ellipse cx="78" cy="53" rx="4.5" ry="2.6" fill="#ff9d9d" opacity=".55"/>
  <path d="M55,55 Q61,61 67,55" stroke="#a3503c" stroke-width="2.4" stroke-linecap="round" fill="none"/>
  <path d="M40,26 Q56,-16 84,-6 Q72,0 80,26 Z" fill="${c.hat}"/>
  <ellipse cx="60" cy="27" rx="31" ry="9" fill="${c.hat}"/>
  <path d="M31,27 Q60,36 89,27 Q60,32 31,27 Z" fill="${c.trim}" opacity=".5"/>
  <rect x="46" y="15" width="28" height="7" rx="3.5" fill="${c.hatBand}"/>
  <circle cx="85" cy="-6" r="4" fill="${c.hatBand}" class="bat-hat-star"/>
</svg>`;
  }

  function projectileSVG(key) {
    const c = CLASSES[key];
    if (key === 'ice') {
      return `<svg viewBox="0 0 40 40" class="bat-proj-svg"><g stroke="${c.orb}" stroke-width="3" stroke-linecap="round">
        <line x1="20" y1="4" x2="20" y2="36"/><line x1="4" y1="20" x2="36" y2="20"/>
        <line x1="8" y1="8" x2="32" y2="32"/><line x1="32" y1="8" x2="8" y2="32"/></g>
        <circle cx="20" cy="20" r="6.5" fill="${c.orbCore}"/></svg>`;
    }
    if (key === 'thunder') {
      return `<svg viewBox="0 0 40 40" class="bat-proj-svg"><circle cx="20" cy="20" r="13" fill="${c.orb}" opacity=".55"/>
        <polygon points="23,4 10,23 18,23 15,36 30,16 21,16" fill="${c.orbCore}" stroke="${c.orb}" stroke-width="1.5"/></svg>`;
    }
    if (key === 'alchemy') {
      return `<svg viewBox="0 0 40 40" class="bat-proj-svg bat-proj-svg--spin"><rect x="16.5" y="3" width="7" height="8" rx="2" fill="#c9d6e2"/>
        <path d="M14,10 Q4,28 20,31 Q36,28 26,10 Z" fill="${c.orb}"/>
        <circle cx="17" cy="24" r="2.2" fill="#fff" opacity=".85"/><circle cx="24" cy="20" r="1.6" fill="#fff" opacity=".7"/></svg>`;
    }
    return `<svg viewBox="0 0 40 40" class="bat-proj-svg"><circle cx="24" cy="20" r="11" fill="${c.orb}"/>
      <path d="M15,20 Q2,12 6,20 Q2,28 15,20 Z" fill="${c.orb}" opacity=".7"/>
      <circle cx="26" cy="18" r="5.5" fill="${c.orbCore}"/></svg>`;
  }

  /* ============================== Âm thanh WebAudio (không cần file) ============================== */

  const sound = (function () {
    let ctx = null;
    let muted = false;

    function ac() {
      if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (AC) ctx = new AC();
      }
      return ctx;
    }

    function tone(freq, time, dur, type, gain) {
      const a = ac();
      if (!a || muted) return;
      const osc = a.createOscillator();
      const g = a.createGain();
      osc.type = type;
      osc.frequency.setValueAtTime(freq, a.currentTime + time);
      g.gain.setValueAtTime(0.0001, a.currentTime + time);
      g.gain.exponentialRampToValueAtTime(gain, a.currentTime + time + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + time + dur);
      osc.connect(g).connect(a.destination);
      osc.start(a.currentTime + time);
      osc.stop(a.currentTime + time + dur + 0.05);
    }

    return {
      shoot() { tone(520, 0, 0.12, 'triangle', 0.12); tone(760, 0.05, 0.1, 'triangle', 0.1); },
      hit() { tone(180, 0, 0.16, 'square', 0.14); tone(120, 0.04, 0.18, 'sawtooth', 0.1); },
      wrong() { tone(160, 0, 0.22, 'sawtooth', 0.12); tone(110, 0.12, 0.28, 'sawtooth', 0.1); },
      combo() { tone(660, 0, 0.1, 'triangle', 0.12); tone(880, 0.08, 0.1, 'triangle', 0.12); tone(1100, 0.16, 0.14, 'triangle', 0.12); },
      win() { [523, 659, 784, 1047].forEach((f, i) => tone(f, i * 0.13, 0.3, 'triangle', 0.14)); },
      toggle() { muted = !muted; return muted; },
      isMuted() { return muted; },
    };
  })();

  /* ============================== Trạng thái game ============================== */

  const state = {
    teams: {},
    students: [],
    finished: false,
    autoTimer: null,
    busy: 0,
  };

  let stageEl = null;
  let fxEl = null;

  function resetState() {
    const c = cfg();
    state.finished = false;
    state.busy = 0;
    state.teams = {
      red: { hp: c.teamHp, maxHp: c.teamHp },
      blue: { hp: c.teamHp, maxHp: c.teamHp },
    };
    state.students = FAKE_STUDENTS.map((s, i) => ({ ...s, id: i, streak: 0, el: null }));
  }

  /* ============================== Render ============================== */

  function renderCharacters() {
    ['red', 'blue'].forEach((team) => {
      const slot = stageEl.querySelector(`[data-slots="${team}"]`);
      if (!slot) return;
      slot.innerHTML = '';
      state.students.filter((s) => s.team === team).forEach((s) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `bat-char bat-char--${team}`;
        el.title = `${s.name} — ${CLASSES[s.cls].label} (bấm = trả lời đúng)`;
        el.innerHTML = `
          <span class="bat-char__streak" hidden></span>
          <span class="bat-char__sprite">${characterSVG(s.cls)}</span>
          <span class="bat-char__plate">${s.name}</span>`;
        el.addEventListener('click', () => answer(team, true, s));
        s.el = el;
        slot.appendChild(el);
      });
    });
  }

  function renderHp(team, animate) {
    const t = state.teams[team];
    const bar = document.querySelector(`[data-hpbar="${team}"]`);
    if (!bar) return;
    const pct = Math.max(0, (t.hp / t.maxHp) * 100);
    const fill = bar.querySelector('.bat-hpbar__fill');
    const ghost = bar.querySelector('.bat-hpbar__ghost');
    const label = bar.querySelector('.bat-hpbar__value');
    if (!animate) {
      fill.style.transition = 'none';
      ghost.style.transition = 'none';
    }
    fill.style.width = `${pct}%`;
    ghost.style.width = `${pct}%`;
    if (!animate) {
      void fill.offsetWidth;
      fill.style.transition = '';
      ghost.style.transition = '';
    }
    if (label) label.textContent = `${Math.max(0, t.hp)} / ${t.maxHp}`;
    bar.classList.toggle('is-low', pct <= 30 && pct > 0);
  }

  function renderStreak(s) {
    const badge = s.el?.querySelector('.bat-char__streak');
    if (!badge) return;
    if (s.streak >= 2) {
      badge.hidden = false;
      badge.textContent = `🔥x${s.streak}`;
    } else {
      badge.hidden = true;
    }
  }

  /* ============================== Hiệu ứng ============================== */

  function stageRect() {
    return stageEl.getBoundingClientRect();
  }

  function centerOf(el) {
    const r = el.getBoundingClientRect();
    const sr = stageRect();
    return { x: r.left - sr.left + r.width / 2, y: r.top - sr.top + r.height / 2 };
  }

  function spawnFloatText(x, y, text, className) {
    const el = document.createElement('div');
    el.className = `bat-float ${className || ''}`;
    el.textContent = text;
    el.style.left = `${x}px`;
    el.style.top = `${y}px`;
    fxEl.appendChild(el);
    setTimeout(() => el.remove(), 1300);
  }

  function spawnBurst(x, y, colors, big) {
    const burst = document.createElement('div');
    burst.className = `bat-burst${big ? ' bat-burst--big' : ''}`;
    burst.style.left = `${x}px`;
    burst.style.top = `${y}px`;
    const count = big ? 14 : 10;
    for (let i = 0; i < count; i++) {
      const p = document.createElement('span');
      const angle = (Math.PI * 2 * i) / count + Math.random() * 0.6;
      const dist = (big ? 70 : 46) * (0.6 + Math.random() * 0.7);
      p.style.setProperty('--dx', `${Math.cos(angle) * dist}px`);
      p.style.setProperty('--dy', `${Math.sin(angle) * dist}px`);
      p.style.background = colors[i % colors.length];
      burst.appendChild(p);
    }
    const ring = document.createElement('span');
    ring.className = 'bat-burst__ring';
    ring.style.borderColor = colors[0];
    burst.appendChild(ring);
    fxEl.appendChild(burst);
    setTimeout(() => burst.remove(), 700);
  }

  function shakeStage() {
    stageEl.classList.remove('is-shaking');
    void stageEl.offsetWidth;
    stageEl.classList.add('is-shaking');
    setTimeout(() => stageEl.classList.remove('is-shaking'), 350);
  }

  function flashClass(el, cls, ms) {
    if (!el) return;
    el.classList.add(cls);
    setTimeout(() => el.classList.remove(cls), ms);
  }

  function showCombo(text) {
    const el = document.getElementById('batCombo');
    if (!el) return;
    el.textContent = text;
    el.classList.remove('is-showing');
    void el.offsetWidth;
    el.classList.add('is-showing');
  }

  /** Đạn bay theo cung bezier từ attacker → target, kèm vệt sáng. */
  function fireProjectile(attacker, target, clsKey, onImpact) {
    const from = centerOf(attacker.el.querySelector('.bat-char__sprite'));
    const to = centerOf(target.el.querySelector('.bat-char__sprite'));
    const proj = document.createElement('div');
    proj.className = 'bat-proj';
    proj.innerHTML = projectileSVG(clsKey);
    fxEl.appendChild(proj);

    const dist = Math.hypot(to.x - from.x, to.y - from.y);
    const dur = Math.max(380, Math.min(750, dist * 1.3));
    const arc = Math.min(120, dist * 0.35);
    const midX = (from.x + to.x) / 2;
    const midY = Math.min(from.y, to.y) - arc;
    const start = performance.now();
    const flip = to.x < from.x;
    let frame = 0;

    function step(now) {
      const t = Math.min(1, (now - start) / dur);
      const it = 1 - t;
      const x = it * it * from.x + 2 * it * t * midX + t * t * to.x;
      const y = it * it * from.y + 2 * it * t * midY + t * t * to.y;
      proj.style.transform = `translate(${x - 20}px, ${y - 20}px)${flip ? ' scaleX(-1)' : ''}`;
      frame++;
      if (frame % 3 === 0) {
        const dot = document.createElement('span');
        dot.className = 'bat-trail';
        dot.style.left = `${x}px`;
        dot.style.top = `${y}px`;
        dot.style.background = CLASSES[clsKey].orb;
        fxEl.appendChild(dot);
        setTimeout(() => dot.remove(), 450);
      }
      if (t < 1) {
        requestAnimationFrame(step);
      } else {
        proj.remove();
        onImpact(to);
      }
    }
    requestAnimationFrame(step);
  }

  /* ============================== Gameplay ============================== */

  function randomOf(list) {
    return list[Math.floor(Math.random() * list.length)];
  }

  function studentsOf(team) {
    return state.students.filter((s) => s.team === team);
  }

  function enemyOf(team) {
    return team === 'red' ? 'blue' : 'red';
  }

  function answer(team, correct, student) {
    if (state.finished) return;
    const s = student || randomOf(studentsOf(team));
    const c = cfg();

    if (!correct) {
      s.streak = 0;
      renderStreak(s);
      sound.wrong();
      flashClass(s.el, 'is-wrong', 750);
      const pos = centerOf(s.el.querySelector('.bat-char__sprite'));
      spawnBurst(pos.x, pos.y - 10, ['#9aa5b1', '#c3cbd4', '#7b8794'], false);
      if (c.selfPenalty > 0) {
        state.teams[team].hp = Math.max(0, state.teams[team].hp - c.selfPenalty);
        spawnFloatText(pos.x, pos.y - 46, `-${c.selfPenalty}`, 'bat-float--self');
        renderHp(team, true);
        checkVictory();
      }
      return;
    }

    s.streak = Math.min(s.streak + 1, 99);
    renderStreak(s);
    const bonus = Math.min(s.streak - 1, COMBO_CAP) * c.comboStep;
    const dmg = c.dmgCorrect + bonus;
    const enemyTeam = enemyOf(team);
    const target = randomOf(studentsOf(enemyTeam));

    sound.shoot();
    flashClass(s.el, 'is-attacking', 420);
    if (s.streak >= 3) {
      sound.combo();
      showCombo(`⚡ ${s.name} COMBO x${s.streak} — sát thương ${dmg}!`);
    }

    state.busy++;
    fireProjectile(s, target, s.cls, (pos) => {
      state.busy--;
      if (state.finished) return;
      sound.hit();
      shakeStage();
      flashClass(target.el, 'is-hit', 520);
      spawnBurst(pos.x, pos.y, CLASSES[s.cls].burst, s.streak >= 3);
      spawnFloatText(pos.x, pos.y - 50, `-${dmg}`, 'bat-float--dmg');
      state.teams[enemyTeam].hp = Math.max(0, state.teams[enemyTeam].hp - dmg);
      renderHp(enemyTeam, true);
      checkVictory();
    });
  }

  function checkVictory() {
    if (state.finished) return;
    const loser = ['red', 'blue'].find((t) => state.teams[t].hp <= 0);
    if (!loser) return;
    state.finished = true;
    stopAuto();
    const winner = enemyOf(loser);

    studentsOf(winner).forEach((s) => s.el.classList.add('is-winner'));
    studentsOf(loser).forEach((s) => s.el.classList.add('is-ko'));
    sound.win();

    const overlay = document.getElementById('batVictory');
    if (overlay) {
      const meta = TEAM_META[winner];
      overlay.querySelector('.bat-victory__team').textContent = `${meta.icon} Đội ${meta.name} chiến thắng!`;
      overlay.querySelector('.bat-victory__card').style.setProperty('--team-color', meta.color);
      overlay.hidden = false;
      spawnConfetti(overlay);
    }
  }

  function spawnConfetti(overlay) {
    const wrap = overlay.querySelector('.bat-victory__confetti');
    if (!wrap) return;
    wrap.innerHTML = '';
    const colors = ['#ffd166', '#ff6b6b', '#4ecdc4', '#a78bfa', '#5ecb84', '#63b3f5'];
    for (let i = 0; i < 60; i++) {
      const p = document.createElement('span');
      p.style.left = `${Math.random() * 100}%`;
      p.style.background = colors[i % colors.length];
      p.style.animationDelay = `${Math.random() * 1.4}s`;
      p.style.animationDuration = `${2 + Math.random() * 2}s`;
      p.style.setProperty('--drift', `${(Math.random() - 0.5) * 140}px`);
      wrap.appendChild(p);
    }
  }

  function resetGame() {
    stopAuto();
    resetState();
    renderCharacters();
    renderHp('red', false);
    renderHp('blue', false);
    const overlay = document.getElementById('batVictory');
    if (overlay) overlay.hidden = true;
    fxEl.innerHTML = '';
  }

  /* ============================== Auto demo ============================== */

  function autoTick() {
    if (state.finished) {
      stopAuto();
      return;
    }
    const team = Math.random() < 0.5 ? 'red' : 'blue';
    answer(team, Math.random() < 0.68);
  }

  function autoInterval() {
    const speed = Number(document.getElementById('bat_speed')?.value) || 1;
    return 1400 / speed;
  }

  function startAuto() {
    stopAuto();
    state.autoTimer = setInterval(autoTick, autoInterval());
    document.querySelector('[data-act="auto"]')?.classList.add('is-on');
    const btn = document.querySelector('[data-act="auto"]');
    if (btn) btn.textContent = '⏸ Dừng demo';
  }

  function stopAuto() {
    if (state.autoTimer) clearInterval(state.autoTimer);
    state.autoTimer = null;
    const btn = document.querySelector('[data-act="auto"]');
    if (btn) {
      btn.classList.remove('is-on');
      btn.textContent = '▶ Tự động demo';
    }
  }

  /* ============================== Trang trí sân khấu ============================== */

  function decorateStage() {
    const sky = stageEl.querySelector('.bat-stage__sky');
    if (!sky) return;
    for (let i = 0; i < 26; i++) {
      const star = document.createElement('span');
      star.className = 'bat-star';
      star.style.left = `${Math.random() * 100}%`;
      star.style.top = `${Math.random() * 55}%`;
      star.style.animationDelay = `${Math.random() * 3}s`;
      star.style.transform = `scale(${0.5 + Math.random()})`;
      sky.appendChild(star);
    }
  }

  /* ============================== Init ============================== */

  function init(root) {
    stageEl = root.querySelector('#batStage');
    fxEl = root.querySelector('#batFx');
    if (!stageEl || !fxEl) return;

    decorateStage();
    resetState();
    renderCharacters();
    renderHp('red', false);
    renderHp('blue', false);

    root.querySelectorAll('[data-act]').forEach((btn) => {
      const act = btn.dataset.act;
      btn.addEventListener('click', () => {
        if (act === 'auto') {
          state.autoTimer ? stopAuto() : startAuto();
        } else if (act === 'reset' || act === 'replay') {
          resetGame();
        } else if (act === 'sound') {
          const muted = sound.toggle();
          btn.textContent = muted ? '🔇 Âm thanh: tắt' : '🔊 Âm thanh: bật';
        } else {
          const [team, result] = act.split('-');
          if (team === 'red' || team === 'blue') answer(team, result === 'correct');
        }
      });
    });

    document.getElementById('bat_speed')?.addEventListener('change', () => {
      if (state.autoTimer) startAuto();
    });

    document.getElementById('bat_team_hp')?.addEventListener('change', resetGame);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('battleArenaDemo');
    if (root) init(root);
  });

  return { init };
})();
