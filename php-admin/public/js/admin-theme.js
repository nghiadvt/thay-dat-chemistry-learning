/**
 * Chuyển đổi theme admin (lưu localStorage, không cần DB).
 * - 'default'  : theme sáng gốc (không set data-theme)
 * - 'lab'      : "Phòng Thí Nghiệm" (admin-theme-lab.css) — bong bóng + phân tử
 * - 'notebook' : "Sổ Tay Học Trò" (admin-theme-notebook.css) — doodle nguệch ngoạc
 * - 'arcade'   : "Arcade 8-bit" (admin-theme-arcade.css) — sao pixel + sprite trôi
 * - 'chalk'    : "Bảng Phấn Lớp Học" (admin-theme-chalk.css) — bụi phấn + công thức phấn
 * - 'galaxy'   : "Vũ Trụ Galaxy" (admin-theme-galaxy.css) — sao + sao băng + hành tinh
 */
window.AdminTheme = (function () {
  'use strict';

  const KEY = 'adminTheme';
  const THEMES = ['default', 'lab', 'notebook', 'arcade', 'chalk', 'galaxy'];

  function get() {
    try {
      const t = localStorage.getItem(KEY);
      return THEMES.includes(t) ? t : 'default';
    } catch (e) {
      return 'default';
    }
  }

  function apply(name) {
    if (name === 'default') {
      document.documentElement.removeAttribute('data-theme');
    } else {
      document.documentElement.setAttribute('data-theme', name);
    }
    syncFx();
    document.dispatchEvent(new CustomEvent('admintheme:changed', { detail: { theme: name } }));
  }

  function set(name) {
    if (!THEMES.includes(name)) return;
    try { localStorage.setItem(KEY, name); } catch (e) { /* private mode */ }
    apply(name);
  }

  /* ---- Lớp hiệu ứng nền: lab = bong bóng + phân tử ---- */

  const MOLECULES = [
    `<svg viewBox="0 0 90 90"><g stroke="#6ee7f9" stroke-width="2.5" fill="none">
      <line x1="24" y1="30" x2="46" y2="46"/><line x1="46" y1="46" x2="70" y2="34"/><line x1="46" y1="46" x2="44" y2="72"/></g>
      <circle cx="24" cy="30" r="9" fill="#38bdf8"/><circle cx="70" cy="34" r="7" fill="#e2f4ff"/>
      <circle cx="46" cy="46" r="11" fill="#34d399"/><circle cx="44" cy="72" r="7" fill="#e2f4ff"/></svg>`,
    `<svg viewBox="0 0 90 90"><polygon points="45,12 74,29 74,61 45,78 16,61 16,29"
      fill="none" stroke="#6ee7f9" stroke-width="3"/><circle cx="45" cy="45" r="17" fill="none" stroke="#34d399" stroke-width="2.5"/></svg>`,
    `<svg viewBox="0 0 90 90"><g stroke="#34d399" stroke-width="2.5" fill="none">
      <line x1="20" y1="46" x2="45" y2="46"/><line x1="45" y1="46" x2="68" y2="30"/><line x1="45" y1="46" x2="68" y2="62"/></g>
      <circle cx="20" cy="46" r="8" fill="#22d3ee"/><circle cx="45" cy="46" r="10" fill="#22d3ee"/>
      <circle cx="68" cy="30" r="6.5" fill="#e2f4ff"/><circle cx="68" cy="62" r="6.5" fill="#e2f4ff"/></svg>`,
  ];

  function buildLabFx() {
    if (document.getElementById('labFx') || !document.body) return;
    const fx = document.createElement('div');
    fx.id = 'labFx';
    fx.className = 'lab-fx';
    fx.setAttribute('aria-hidden', 'true');

    for (let i = 0; i < 16; i++) {
      const b = document.createElement('span');
      b.className = 'lab-bubble';
      const size = 6 + Math.random() * 18;
      b.style.left = `${Math.random() * 100}%`;
      b.style.width = `${size}px`;
      b.style.height = `${size}px`;
      b.style.animationDuration = `${9 + Math.random() * 14}s`;
      b.style.animationDelay = `${-Math.random() * 20}s`;
      fx.appendChild(b);
    }

    MOLECULES.forEach((svg, i) => {
      const m = document.createElement('div');
      m.className = 'lab-molecule';
      m.innerHTML = svg;
      const size = 80 + Math.random() * 60;
      m.style.width = `${size}px`;
      m.style.height = `${size}px`;
      m.style.left = `${8 + i * 32 + Math.random() * 8}%`;
      m.style.top = `${10 + Math.random() * 55}%`;
      m.style.animationDelay = `${-Math.random() * 60}s`;
      fx.appendChild(m);
    });

    document.body.appendChild(fx);
  }

  /* ---- Lớp hiệu ứng nền: notebook = doodle nguệch ngoạc ---- */

  const DOODLES = [
    // máy bay giấy
    `<svg viewBox="0 0 90 90" fill="none" stroke="#8a6d3b" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
      <path d="M8,52 L82,14 L46,62 Z"/><path d="M46,62 L40,48"/></svg>`,
    // bút chì
    `<svg viewBox="0 0 90 90" fill="none" stroke="#8a6d3b" stroke-width="3" stroke-linecap="round">
      <rect x="30" y="10" width="16" height="52" rx="3" transform="rotate(24 38 36)"/>
      <path d="M22,66 L34,74 L20,80 Z" fill="#8a6d3b"/></svg>`,
    // nguyên tử vẽ tay
    `<svg viewBox="0 0 90 90" fill="none" stroke="#8a6d3b" stroke-width="2.6">
      <ellipse cx="45" cy="45" rx="34" ry="13"/><ellipse cx="45" cy="45" rx="34" ry="13" transform="rotate(60 45 45)"/>
      <ellipse cx="45" cy="45" rx="34" ry="13" transform="rotate(-60 45 45)"/><circle cx="45" cy="45" r="5" fill="#8a6d3b"/></svg>`,
    // ngôi sao nguệch ngoạc
    `<svg viewBox="0 0 90 90" fill="none" stroke="#8a6d3b" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
      <path d="M45,12 L54,36 L80,37 L59,52 L67,78 L45,62 L23,78 L31,52 L10,37 L36,36 Z"/></svg>`,
    // cốc thí nghiệm vẽ tay
    `<svg viewBox="0 0 90 90" fill="none" stroke="#8a6d3b" stroke-width="3" stroke-linecap="round">
      <path d="M34,12 h22 M38,12 v20 L20,68 a8,8 0 0 0 8,10 h34 a8,8 0 0 0 8,-10 L52,32 v-20"/>
      <path d="M30,52 q8,-5 15,0 t15,0"/></svg>`,
    // công thức H2O
    `<svg viewBox="0 0 90 90"><text x="12" y="56" font-family="Baloo 2, cursive" font-size="30" font-weight="700" fill="#8a6d3b">H₂O</text></svg>`,
  ];

  function buildNotebookFx() {
    if (document.getElementById('nbFx') || !document.body) return;
    const fx = document.createElement('div');
    fx.id = 'nbFx';
    fx.className = 'nb-fx';
    fx.setAttribute('aria-hidden', 'true');

    DOODLES.forEach((svg, i) => {
      const d = document.createElement('div');
      d.className = 'nb-doodle';
      d.innerHTML = svg;
      const size = 56 + Math.random() * 50;
      d.style.width = `${size}px`;
      d.style.height = `${size}px`;
      d.style.left = `${6 + (i % 3) * 32 + Math.random() * 10}%`;
      d.style.top = `${8 + Math.floor(i / 3) * 44 + Math.random() * 14}%`;
      d.style.animationDuration = `${10 + Math.random() * 10}s`;
      d.style.animationDelay = `${-Math.random() * 20}s`;
      fx.appendChild(d);
    });

    document.body.appendChild(fx);
  }

  /* ---- Lớp hiệu ứng nền: arcade = sao pixel + sprite 8-bit trôi ---- */

  const SPRITES = [
    // phi thuyền invader (xanh lá)
    `<svg viewBox="0 0 110 80">${[
      [20, 0], [80, 0], [30, 10], [70, 10],
      [20, 20], [30, 20], [40, 20], [50, 20], [60, 20], [70, 20], [80, 20],
      [10, 30], [20, 30], [40, 30], [50, 30], [60, 30], [80, 30], [90, 30],
      [0, 40], [10, 40], [20, 40], [30, 40], [40, 40], [50, 40], [60, 40], [70, 40], [80, 40], [90, 40], [100, 40],
      [0, 50], [20, 50], [80, 50], [100, 50],
      [0, 60], [30, 60], [70, 60], [100, 60],
      [40, 70], [60, 70],
    ].map(([x, y]) => `<rect x="${x}" y="${y}" width="10" height="10" fill="#6dff7c"/>`).join('')}</svg>`,
    // đồng xu pixel (vàng)
    `<svg viewBox="0 0 80 80">${[
      [20, 0], [30, 0], [40, 0], [50, 0],
      [10, 10], [60, 10], [0, 20], [70, 20], [0, 30], [30, 30], [40, 30], [70, 30],
      [0, 40], [30, 40], [40, 40], [70, 40], [0, 50], [70, 50],
      [10, 60], [60, 60], [20, 70], [30, 70], [40, 70], [50, 70],
    ].map(([x, y]) => `<rect x="${x}" y="${y}" width="10" height="10" fill="#ffd54a"/>`).join('')}</svg>`,
    // trái tim pixel (hồng)
    `<svg viewBox="0 0 70 60">${[
      [10, 0], [20, 0], [40, 0], [50, 0],
      [0, 10], [30, 10], [60, 10], [0, 20], [60, 20],
      [10, 30], [50, 30], [20, 40], [40, 40], [30, 50],
    ].map(([x, y]) => `<rect x="${x}" y="${y}" width="10" height="10" fill="#ff5db1"/>`).join('')}</svg>`,
  ];

  function buildArcadeFx() {
    if (document.getElementById('arcFx') || !document.body) return;
    const fx = document.createElement('div');
    fx.id = 'arcFx';
    fx.className = 'arc-fx';
    fx.setAttribute('aria-hidden', 'true');

    for (let i = 0; i < 26; i++) {
      const s = document.createElement('span');
      s.className = 'arc-star';
      const size = Math.random() < 0.75 ? 3 : 5;
      s.style.width = `${size}px`;
      s.style.height = `${size}px`;
      s.style.left = `${Math.random() * 100}%`;
      s.style.top = `${Math.random() * 100}%`;
      s.style.animationDuration = `${1.2 + Math.random() * 2.6}s`;
      s.style.animationDelay = `${-Math.random() * 3}s`;
      if (Math.random() < 0.3) s.style.background = '#4dd8ff';
      fx.appendChild(s);
    }

    SPRITES.forEach((svg, i) => {
      const sp = document.createElement('div');
      sp.className = 'arc-sprite';
      sp.innerHTML = svg;
      const size = 34 + Math.random() * 26;
      sp.style.width = `${size}px`;
      sp.style.height = `${size * 0.75}px`;
      sp.style.top = `${8 + i * 26 + Math.random() * 12}%`;
      sp.style.animationDuration = `${34 + Math.random() * 30}s`;
      sp.style.animationDelay = `${-Math.random() * 40}s`;
      fx.appendChild(sp);
    });

    document.body.appendChild(fx);
  }

  /* ---- Lớp hiệu ứng nền: chalk = bụi phấn rơi + công thức viết phấn ---- */

  const CHALK_DOODLES = [
    `<svg viewBox="0 0 220 60"><text x="8" y="40" font-family="Patrick Hand, cursive" font-size="28"
      fill="#f2f7f0">2H₂ + O₂ → 2H₂O</text></svg>`,
    `<svg viewBox="0 0 100 100" fill="none" stroke="#f2f7f0" stroke-width="3" stroke-dasharray="7 5">
      <polygon points="50,10 85,30 85,70 50,90 15,70 15,30"/><circle cx="50" cy="50" r="20"/></svg>`,
    `<svg viewBox="0 0 100 110" fill="none" stroke="#f2f7f0" stroke-width="3" stroke-linecap="round">
      <path d="M38,10 h24 M42,10 v26 L22,86 a10,10 0 0 0 9,14 h38 a10,10 0 0 0 9,-14 L58,36 v-26"/>
      <path d="M32,66 q10,-6 18,0 t18,0" stroke-dasharray="5 5"/></svg>`,
    `<svg viewBox="0 0 190 60"><text x="8" y="40" font-family="Patrick Hand, cursive" font-size="28"
      fill="#f2f7f0">NaCl  ·  CO₂</text></svg>`,
    `<svg viewBox="0 0 110 60" fill="none" stroke="#f2f7f0" stroke-width="3" stroke-linecap="round">
      <path d="M8,30 h74"/><path d="M70,16 L92,30 L70,44"/></svg>`,
  ];

  function buildChalkFx() {
    if (document.getElementById('chalkFx') || !document.body) return;
    const fx = document.createElement('div');
    fx.id = 'chalkFx';
    fx.className = 'chalk-fx';
    fx.setAttribute('aria-hidden', 'true');

    for (let i = 0; i < 18; i++) {
      const d = document.createElement('span');
      d.className = 'chalk-dust';
      const size = 2 + Math.random() * 3;
      d.style.width = `${size}px`;
      d.style.height = `${size}px`;
      d.style.left = `${Math.random() * 100}%`;
      d.style.animationDuration = `${14 + Math.random() * 18}s`;
      d.style.animationDelay = `${-Math.random() * 30}s`;
      fx.appendChild(d);
    }

    CHALK_DOODLES.forEach((svg, i) => {
      const d = document.createElement('div');
      d.className = 'chalk-doodle';
      d.innerHTML = svg;
      const w = 110 + Math.random() * 90;
      d.style.width = `${w}px`;
      d.style.height = `${w * 0.5}px`;
      d.style.left = `${5 + (i % 3) * 33 + Math.random() * 8}%`;
      d.style.top = `${10 + Math.floor(i / 3) * 42 + Math.random() * 14}%`;
      d.style.animationDuration = `${9 + Math.random() * 8}s`;
      d.style.animationDelay = `${-Math.random() * 16}s`;
      fx.appendChild(d);
    });

    document.body.appendChild(fx);
  }

  /* ---- Lớp hiệu ứng nền: galaxy = sao + sao băng + hành tinh ---- */

  function buildGalaxyFx() {
    if (document.getElementById('galFx') || !document.body) return;
    const fx = document.createElement('div');
    fx.id = 'galFx';
    fx.className = 'gal-fx';
    fx.setAttribute('aria-hidden', 'true');

    for (let i = 0; i < 34; i++) {
      const s = document.createElement('span');
      s.className = 'gal-star';
      const size = 1.5 + Math.random() * 2.5;
      s.style.width = `${size}px`;
      s.style.height = `${size}px`;
      s.style.left = `${Math.random() * 100}%`;
      s.style.top = `${Math.random() * 100}%`;
      s.style.animationDuration = `${1.8 + Math.random() * 3}s`;
      s.style.animationDelay = `${-Math.random() * 4}s`;
      fx.appendChild(s);
    }

    for (let i = 0; i < 2; i++) {
      const sh = document.createElement('span');
      sh.className = 'gal-shoot';
      sh.style.left = `${45 + Math.random() * 50}%`;
      sh.style.top = `${5 + Math.random() * 30}%`;
      sh.style.animationDelay = `${i * 4.5 + Math.random() * 2}s`;
      fx.appendChild(sh);
    }

    const planet = document.createElement('div');
    planet.className = 'gal-planet';
    planet.style.width = '120px';
    planet.style.height = '120px';
    planet.style.right = '6%';
    planet.style.top = '12%';
    planet.innerHTML = `<svg viewBox="0 0 120 120">
      <defs><radialGradient id="galPl" cx=".35" cy=".3" r=".8">
        <stop offset="0" stop-color="#c4b5fd"/><stop offset="1" stop-color="#6d28d9"/>
      </radialGradient></defs>
      <ellipse cx="60" cy="62" rx="52" ry="14" fill="none" stroke="#f9a8d4" stroke-width="4" opacity=".6" transform="rotate(-18 60 62)"/>
      <circle cx="60" cy="60" r="30" fill="url(#galPl)"/>
      <ellipse cx="50" cy="52" rx="10" ry="6" fill="#ddd2ff" opacity=".5"/>
      <ellipse cx="60" cy="62" rx="52" ry="14" fill="none" stroke="#f9a8d4" stroke-width="4" opacity=".9"
        stroke-dasharray="80 90" transform="rotate(-18 60 62)"/>
    </svg>`;
    fx.appendChild(planet);

    document.body.appendChild(fx);
  }

  function syncFx() {
    const theme = document.documentElement.getAttribute('data-theme');
    if (theme === 'lab') buildLabFx();
    if (theme === 'notebook') buildNotebookFx();
    if (theme === 'arcade') buildArcadeFx();
    if (theme === 'chalk') buildChalkFx();
    if (theme === 'galaxy') buildGalaxyFx();
    const layers = { labFx: 'lab', nbFx: 'notebook', arcFx: 'arcade', chalkFx: 'chalk', galFx: 'galaxy' };
    Object.keys(layers).forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.display = theme === layers[id] ? '' : 'none';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncFx);
  } else {
    syncFx();
  }

  return { get, set, apply, themes: THEMES.slice() };
})();
