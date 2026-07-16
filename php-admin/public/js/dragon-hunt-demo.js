/**
 * Admin demo — Săn Rồng Hóa Học (boss raid cả lớp đánh Hắc Long).
 * Dữ liệu giả lập bằng JS; toàn bộ art vẽ bằng SVG code + particle canvas, chưa nối database.
 */
window.DragonHuntDemo = (function () {
  'use strict';

  /* ============================== Fake data & cấu hình ============================== */

  const OUTLINE = '#241432';

  const CLASSES = {
    sword: {
      label: 'Kiếm Sĩ Lửa',
      robe: ['#ff9a62', '#c93a1c'],
      trim: '#7a1c0e',
      cape: '#8f2413',
      capeIn: '#e0663a',
      accent: '#ffd166',
      hair: '#b3432b',
      eye: '#a3512b',
      rgb: '255,150,60',
    },
    bow: {
      label: 'Cung Thủ Băng',
      robe: ['#74c0f8', '#2f6fd0'],
      trim: '#1d4f9e',
      cape: '#173f80',
      capeIn: '#5ba3e8',
      accent: '#dbeeff',
      hair: '#9fd4f5',
      eye: '#2f6fd0',
      rgb: '130,200,255',
    },
    thunder: {
      label: 'Pháp Sư Lôi',
      robe: ['#b39bfc', '#7c3aed'],
      trim: '#5b21b6',
      cape: '#3f1786',
      capeIn: '#9a7df0',
      accent: '#ffe75e',
      hair: '#d9c6ff',
      eye: '#7c3aed',
      rgb: '255,230,110',
    },
    alchemy: {
      label: 'Giả Kim Sư',
      robe: ['#6cd692', '#2e9457'],
      trim: '#1e6b3c',
      cape: '#14532d',
      capeIn: '#57b87e',
      accent: '#e4ffb0',
      hair: '#7a5a36',
      eye: '#2e9457',
      rgb: '150,240,120',
    },
  };

  const FAKE_HEROES = [
    { name: 'Minh', cls: 'sword' },
    { name: 'Lan', cls: 'bow' },
    { name: 'Hùng', cls: 'thunder' },
    { name: 'Mai', cls: 'alchemy' },
  ];

  /** Màu ngọn lửa thật của các nguyên tố (thí nghiệm flame test). */
  const FLAME_TESTS = [
    { sym: 'Li', color: '#ff4d4d', rgb: '255,80,80' },
    { sym: 'Na', color: '#ffd93b', rgb: '255,220,80' },
    { sym: 'K', color: '#c9a7ff', rgb: '200,170,255' },
    { sym: 'Cu', color: '#3ee6c5', rgb: '70,230,200' },
    { sym: 'Ba', color: '#7ee65c', rgb: '130,230,100' },
    { sym: 'Sr', color: '#ff6b4d', rgb: '255,110,80' },
  ];

  const ELEMENT_CHIPS = [
    { t: 'H₂', bg: '#e0f2fe' }, { t: 'O₂', bg: '#fee2e2' }, { t: 'Na', bg: '#fef9c3' },
    { t: 'Cl₂', bg: '#dcfce7' }, { t: 'Fe', bg: '#e2e8f0' }, { t: 'Cu', bg: '#ffedd5' },
    { t: 'Zn', bg: '#ede9fe' }, { t: 'Mg', bg: '#f0fdf4' }, { t: 'K', bg: '#fce7f3' },
    { t: 'H₂O', bg: '#dbeafe' }, { t: 'CO₂', bg: '#f1f5f9' }, { t: 'NaCl', bg: '#fef3c7' },
  ];

  function readNumber(id, fallback) {
    const n = Number(document.getElementById(id)?.value);
    return Number.isFinite(n) ? n : fallback;
  }

  function cfg() {
    return {
      bossHp: Math.max(50, readNumber('drg_boss_hp', 300)),
      dmg: Math.max(1, readNumber('drg_dmg', 12)),
      critPct: Math.min(100, Math.max(0, readNumber('drg_crit', 20))),
      hearts: Math.min(10, Math.max(1, readNumber('drg_hearts', 5))),
      comboNeed: Math.min(12, Math.max(2, readNumber('drg_combo_need', 5))),
    };
  }

  /* ============================== SVG — Hắc Long (chi tiết cao) ============================== */

  /** Sinh các hàng vảy hình vảy cá dọc thân/cổ. */
  function scaleRows(rows, color, width, opacity) {
    let s = '';
    rows.forEach((row, ri) => {
      const off = ri % 2 ? row.r : 0;
      for (let x = row.x0 + off; x + row.r * 2 <= row.x1; x += row.r * 2) {
        const yy = row.y + (row.bend || 0) * Math.sin(((x - row.x0) / (row.x1 - row.x0)) * Math.PI);
        s += `<path d="M${x},${yy} a${row.r},${row.r} 0 0 1 ${row.r * 2},0" fill="none" stroke="${color}" stroke-width="${width}" opacity="${opacity}"/>`;
      }
    });
    return s;
  }

  function dragonSVG() {
    const bodyScales = scaleRows([
      { y: 242, x0: 302, x1: 452, r: 13, bend: -7 },
      { y: 272, x0: 290, x1: 460, r: 14, bend: -4 },
      { y: 302, x0: 288, x1: 462, r: 14, bend: 2 },
      { y: 332, x0: 300, x1: 452, r: 13, bend: 6 },
    ], '#4a0d1a', 2.4, 0.5);
    const neckScales = scaleRows([
      { y: 128, x0: 238, x1: 300, r: 8 },
      { y: 150, x0: 244, x1: 308, r: 8 },
      { y: 172, x0: 252, x1: 318, r: 8 },
    ], '#4a0d1a', 2, 0.45);

    return `
<svg viewBox="0 0 560 460" xmlns="http://www.w3.org/2000/svg" class="drg-svg" aria-hidden="true">
  <defs>
    <linearGradient id="drgBody" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#c13a52"/><stop offset=".5" stop-color="#8c1c34"/><stop offset="1" stop-color="#57101f"/>
    </linearGradient>
    <linearGradient id="drgBodyDark" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#7c1626"/><stop offset="1" stop-color="#3c0812"/>
    </linearGradient>
    <linearGradient id="drgWing" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#e0526e"/><stop offset=".45" stop-color="#8c1a3c"/><stop offset="1" stop-color="#45102e"/>
    </linearGradient>
    <linearGradient id="drgBelly" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#f7d489"/><stop offset=".5" stop-color="#e0a844"/><stop offset="1" stop-color="#b47822"/>
    </linearGradient>
    <linearGradient id="drgHorn" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f5e9c8"/><stop offset="1" stop-color="#c2a26a"/>
    </linearGradient>
    <radialGradient id="drgCoreG" cx=".5" cy=".4" r=".65">
      <stop offset="0" stop-color="#fff3c4"/><stop offset=".5" stop-color="#ffb347"/><stop offset="1" stop-color="#ff7b2d"/>
    </radialGradient>
    <radialGradient id="drgShadow" cx=".5" cy=".5" r=".5">
      <stop offset="0" stop-color="rgba(0,0,0,.45)"/><stop offset="1" stop-color="rgba(0,0,0,0)"/>
    </radialGradient>
  </defs>

  <ellipse cx="320" cy="434" rx="200" ry="24" fill="url(#drgShadow)"/>

  <g class="drg-breathe">
    <!-- ================= ĐUÔI ================= -->
    <path d="M400,326 C468,322 518,288 534,232 C540,214 554,210 551,230 C543,300 480,356 404,364 Z"
          fill="url(#drgBodyDark)" stroke="${OUTLINE}" stroke-width="3"/>
    <path d="M406,330 C470,326 514,294 530,242" fill="none" stroke="#d4586f" stroke-width="2.5" opacity=".5"/>
    <path d="M428,338 q10,4 6,16 M462,322 q10,4 6,15 M494,298 q10,3 7,14 M518,266 q9,3 7,13"
          fill="none" stroke="#3c0812" stroke-width="3" opacity=".6"/>
    <path d="M498,268 L512,240 L524,266 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M524,240 L537,212 L547,240 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M545,232 L560,202 L558,240 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M549,230 L544,208 L552,231 Z" fill="#fff" opacity=".35"/>

    <!-- ================= CÁNH SAU ================= -->
    <g class="drg-wing-back">
      <path d="M348,198 C366,108 452,66 508,78 C470,108 450,148 444,206 Z"
            fill="#5a1230" stroke="${OUTLINE}" stroke-width="3"/>
      <path d="M348,198 C372,116 445,72 508,78" fill="none" stroke="#43092a" stroke-width="5" stroke-linecap="round"/>
      <path d="M430,88 L448,182 M462,80 L452,148" stroke="#43092a" stroke-width="3.5" stroke-linecap="round" opacity=".8"/>
    </g>

    <!-- ================= CHÂN SAU ================= -->
    <ellipse cx="398" cy="378" rx="44" ry="36" fill="url(#drgBodyDark)" stroke="${OUTLINE}" stroke-width="3"/>
    <path d="M370,360 q22,-14 48,-4" fill="none" stroke="#d4586f" stroke-width="2.5" opacity=".4"/>
    <path d="M436,352 L452,332 L444,358 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M378,400 q-4,10 2,18 h52 q8,-8 2,-18 Z" fill="#6d1322" stroke="${OUTLINE}" stroke-width="3"/>
    <path d="M382,418 L368,410 L382,404 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>
    <path d="M398,420 L386,413 L398,407 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>
    <path d="M414,420 L403,414 L414,408 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>

    <!-- ================= THÂN ================= -->
    <path d="M255,250 C245,330 290,395 360,400 C430,405 465,350 458,280 C452,215 405,180 345,182 C300,184 265,205 255,250 Z"
          fill="url(#drgBody)" stroke="${OUTLINE}" stroke-width="3.5"/>
    <path d="M262,238 C276,206 306,188 344,185" fill="none" stroke="#e06a7f" stroke-width="3" opacity=".55"/>
    <path d="M300,392 C340,402 420,404 450,348 C452,376 420,402 362,399 Z" fill="#3c0812" opacity=".35"/>
    ${bodyScales}

    <!-- bụng nhiều tấm giáp -->
    <path d="M300,218 C266,266 270,342 344,390 C300,386 268,338 264,288 C262,252 278,228 300,218 Z"
          fill="url(#drgBelly)" stroke="#8a5a14" stroke-width="2.5"/>
    <path d="M272,254 Q296,266 308,256 M268,286 Q296,300 312,290 M270,318 Q298,334 316,324 M280,350 Q306,366 324,356"
          fill="none" stroke="#8a5a14" stroke-width="2.2" opacity=".8"/>
    <path d="M274,248 Q296,258 306,250 M270,280 Q294,292 310,284 M272,312 Q298,326 314,318"
          fill="none" stroke="#fbe4ad" stroke-width="1.6" opacity=".7"/>

    <!-- gai lưng 2 lớp -->
    <path d="M293,192 L307,156 L322,188 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M307,156 L313,186 L322,188 Z" fill="#b08d4e" opacity=".8"/>
    <path d="M328,182 L344,144 L361,180 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M344,144 L351,178 L361,180 Z" fill="#b08d4e" opacity=".8"/>
    <path d="M367,182 L382,148 L397,185 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M382,148 L389,182 L397,185 Z" fill="#b08d4e" opacity=".8"/>
    <path d="M420,196 L436,166 L449,201 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M436,166 L442,198 L449,201 Z" fill="#b08d4e" opacity=".8"/>

    <!-- ================= CHÂN TRƯỚC ================= -->
    <ellipse cx="328" cy="376" rx="40" ry="32" fill="url(#drgBody)" stroke="${OUTLINE}" stroke-width="3"/>
    <path d="M300,360 q22,-14 50,-4" fill="none" stroke="#e06a7f" stroke-width="2.5" opacity=".5"/>
    <path d="M296,396 q-5,11 2,22 h56 q9,-9 2,-22 Z" fill="#8c1c2e" stroke="${OUTLINE}" stroke-width="3"/>
    <path d="M300,418 L284,409 L300,402 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>
    <path d="M318,420 L304,412 L318,405 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>
    <path d="M336,420 L323,413 L336,406 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.5"/>

    <!-- ================= LÕI PHẢN ỨNG Ở NGỰC ================= -->
    <circle class="drg-core-glow" cx="296" cy="292" r="30" fill="#ffb347" opacity=".25"/>
    <circle cx="296" cy="292" r="20" fill="#57101f" stroke="#8a5a14" stroke-width="2"/>
    <circle class="drg-core" cx="296" cy="292" r="15" fill="url(#drgCoreG)" stroke="#8a5a14" stroke-width="2.5"/>
    <path d="M290,288 q6,-6 10,2 q-6,6 -10,-2" fill="#fff" opacity=".75"/>
    <circle cx="296" cy="272" r="2.6" fill="#e0a844"/><circle cx="296" cy="312" r="2.6" fill="#e0a844"/>
    <circle cx="276" cy="292" r="2.6" fill="#e0a844"/><circle cx="316" cy="292" r="2.6" fill="#e0a844"/>
    <!-- mạch độc phát sáng khi nổi giận -->
    <g class="drg-veins" opacity="0">
      <path d="M296,272 C300,250 316,240 330,236 M282,280 C264,270 258,254 260,240 M290,310 C284,330 292,348 304,356 M312,300 C332,308 344,324 346,340"
            fill="none" stroke="#7ee65c" stroke-width="3" stroke-linecap="round"/>
    </g>

    <!-- ================= CỔ ================= -->
    <path d="M300,195 C258,185 232,148 226,102 L270,88 C276,132 296,164 332,184 Z"
          fill="url(#drgBody)" stroke="${OUTLINE}" stroke-width="3.5"/>
    ${neckScales}
    <path d="M240,110 Q252,146 282,172" fill="none" stroke="#e06a7f" stroke-width="2.5" opacity=".45"/>
    <path d="M234,116 q14,4 18,16 M244,144 q14,5 19,17 M258,168 q13,6 17,17"
          fill="none" stroke="#8a5a14" stroke-width="2.4" opacity=".55"/>
    <path d="M262,118 L276,92 L288,122 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    <path d="M276,92 L282,118 L288,122 Z" fill="#b08d4e" opacity=".8"/>

    <!-- ================= ĐẦU ================= -->
    <g class="drg-head">
      <!-- vây sau gáy 3 múi -->
      <path d="M226,96 L262,58 L244,100 Z" fill="#5a1230" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M232,106 L276,78 L252,112 Z" fill="#7c1626" stroke="${OUTLINE}" stroke-width="2.5"/>

      <!-- hàm dưới -->
      <g class="drg-jaw">
        <path d="M212,128 C180,148 140,148 106,126 C114,152 152,170 194,162 C208,159 214,144 212,128 Z"
              fill="#7c1626" stroke="${OUTLINE}" stroke-width="3"/>
        <path d="M118,136 L124,122 L133,138 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
        <path d="M142,144 L148,130 L157,146 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
        <path d="M166,148 L172,135 L180,149 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
        <path d="M120,150 C150,162 185,158 202,148" fill="none" stroke="#43092a" stroke-width="2.5" opacity=".7"/>
        <!-- lưỡi -->
        <path class="drg-tongue" d="M140,140 Q160,150 184,142 Q166,156 146,150 Z" fill="#d94f6b" opacity="0"/>
      </g>
      <ellipse class="drg-mawglow" cx="158" cy="130" rx="36" ry="13" fill="#ff7b2d" opacity="0"/>
      <ellipse class="drg-mawglow-toxic" cx="158" cy="130" rx="36" ry="13" fill="#8ef060" opacity="0"/>

      <!-- sọ + mõm -->
      <path d="M232,108 C226,72 194,54 152,58 C116,62 92,80 85,98 C82,105 87,110 95,110 C119,110 139,114 151,122 C175,136 210,138 232,120 Z"
            fill="url(#drgBody)" stroke="${OUTLINE}" stroke-width="3.5"/>
      <path d="M96,92 C112,74 138,64 162,62" fill="none" stroke="#e06a7f" stroke-width="3" opacity=".55"/>
      <!-- gồ mũi + 2 lỗ mũi -->
      <path d="M96,86 q10,-8 22,-6" fill="none" stroke="#57101f" stroke-width="3" opacity=".6"/>
      <ellipse cx="101" cy="97" rx="4" ry="3" fill="#2b0510"/>
      <ellipse cx="112" cy="103" rx="3" ry="2.4" fill="#2b0510"/>
      <circle class="drg-smoke drg-smoke--a" cx="99" cy="86" r="5" fill="#c8b8c9" opacity=".5"/>
      <circle class="drg-smoke drg-smoke--b" cx="106" cy="78" r="3.6" fill="#c8b8c9" opacity=".4"/>
      <!-- vảy trên sọ -->
      <path d="M168,66 q12,8 8,20 M192,70 q11,9 6,22 M212,84 q9,9 4,20" fill="none" stroke="#57101f" stroke-width="2.4" opacity=".5"/>
      <!-- răng trên -->
      <path d="M104,108 L110,122 L118,108 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
      <path d="M122,112 L128,127 L136,112 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
      <path d="M140,116 L146,131 L154,117 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
      <path d="M158,121 L164,134 L172,121 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
      <path d="M176,124 L181,136 L189,123 Z" fill="#fff8e7" stroke="#8a6f3c" stroke-width="1.2"/>
      <!-- mắt: hốc + tròng + con ngươi + 2 điểm sáng + mí -->
      <path d="M158,84 Q176,74 196,80" fill="none" stroke="#57101f" stroke-width="4" opacity=".5"/>
      <circle class="drg-eye-glow" cx="178" cy="93" r="17" fill="#ffcf3f" opacity=".25"/>
      <ellipse cx="178" cy="93" rx="10" ry="11" fill="#ffd44d" stroke="${OUTLINE}" stroke-width="2.5"/>
      <ellipse cx="178" cy="93" rx="6.5" ry="9" fill="#ff9a2e" opacity=".8"/>
      <ellipse cx="178" cy="93" rx="2.6" ry="7.5" fill="#1a0508"/>
      <circle cx="175" cy="88" r="1.8" fill="#fff"/>
      <circle cx="181" cy="97" r="1" fill="#fff" opacity=".8"/>
      <path d="M168,102 Q178,106 188,101" fill="none" stroke="#57101f" stroke-width="2"/>
      <path d="M160,78 L200,70" stroke="${OUTLINE}" stroke-width="6" stroke-linecap="round"/>
      <path d="M162,75 L196,68" stroke="#a83048" stroke-width="2" stroke-linecap="round" opacity=".8"/>
      <!-- sừng lớn có khấc + sừng phụ + gai má -->
      <path d="M196,64 C205,32 232,18 254,22 C236,36 224,54 219,76 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2.5"/>
      <path d="M208,52 q10,6 16,4 M216,40 q9,6 15,4 M226,30 q8,5 14,3" fill="none" stroke="#8a6f3c" stroke-width="2" opacity=".8"/>
      <path d="M172,58 C176,40 190,30 203,30 C193,42 187,54 185,68 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2.5"/>
      <path d="M225,86 L254,78 L234,104 Z" fill="#5a1230" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M231,88 L246,84 L236,97 Z" fill="#8c1a3c" opacity=".9"/>
      <path d="M148,64 L158,48 L164,66 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    </g>

    <!-- ================= CÁNH TRƯỚC ================= -->
    <g class="drg-wing">
      <path d="M335,195 C345,98 418,46 494,54 C480,86 472,114 484,150 C452,132 432,148 418,180 C400,148 378,158 366,202 Z"
            fill="url(#drgWing)" stroke="${OUTLINE}" stroke-width="3.5"/>
      <!-- rách mép cánh -->
      <path d="M484,150 l-9,-4 4,10 -10,-3 3,9" fill="none" stroke="${OUTLINE}" stroke-width="2" opacity=".7"/>
      <!-- gân màng cánh -->
      <path d="M368,180 C390,120 430,84 470,70 M400,190 C416,140 448,104 482,92 M420,176 C436,146 458,124 480,116"
            fill="none" stroke="#5a1025" stroke-width="2" opacity=".5"/>
      <!-- xương cánh + khớp -->
      <path d="M335,195 C360,106 418,58 494,54" fill="none" stroke="#5a1025" stroke-width="7" stroke-linecap="round"/>
      <path d="M337,193 C362,108 420,62 490,57" fill="none" stroke="#d4586f" stroke-width="2" stroke-linecap="round" opacity=".6"/>
      <path d="M412,70 L484,148" stroke="#5a1025" stroke-width="4.5" stroke-linecap="round"/>
      <path d="M406,74 L418,178" stroke="#5a1025" stroke-width="4.5" stroke-linecap="round"/>
      <circle cx="410" cy="70" r="6" fill="#7c1626" stroke="${OUTLINE}" stroke-width="2"/>
      <path d="M410,64 L404,46 L418,58 Z" fill="url(#drgHorn)" stroke="#8a6f3c" stroke-width="2"/>
    </g>
  </g>
</svg>`;
  }

  /* ============================== SVG — vạc điều chế của boss ============================== */

  function cauldronSVG() {
    return `
<svg viewBox="0 0 200 185" xmlns="http://www.w3.org/2000/svg" class="drg-caul-svg" aria-hidden="true">
  <defs>
    <linearGradient id="drgCaulPot" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#4a5261"/><stop offset=".5" stop-color="#2b313c"/><stop offset="1" stop-color="#14181f"/>
    </linearGradient>
    <linearGradient id="drgCaulLiq" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#c9ff7e"/><stop offset="1" stop-color="#3fae54"/>
    </linearGradient>
  </defs>
  <ellipse cx="100" cy="176" rx="66" ry="8" fill="rgba(0,0,0,.4)"/>
  <!-- củi + lửa -->
  <rect x="54" y="152" width="92" height="9" rx="4.5" fill="#6b4423" stroke="#3d2413" stroke-width="2" transform="rotate(-6 100 156)"/>
  <rect x="58" y="154" width="86" height="8" rx="4" fill="#7d5230" stroke="#3d2413" stroke-width="2" transform="rotate(5 100 158)"/>
  <path class="drg-caul-flame drg-caul-flame--1" d="M80,154 C74,138 84,126 90,114 C94,128 102,134 98,148 Q90,158 80,154 Z" fill="#ff8c2b" opacity=".95"/>
  <path class="drg-caul-flame drg-caul-flame--2" d="M100,156 C96,140 106,128 110,118 C114,132 120,138 116,150 Q108,160 100,156 Z" fill="#ffb347" opacity=".9"/>
  <path class="drg-caul-flame drg-caul-flame--3" d="M92,152 C90,142 96,134 99,128 C102,138 106,142 103,150 Q98,156 92,152 Z" fill="#fff3c4" opacity=".9"/>
  <!-- chân vạc -->
  <path d="M56,140 L44,162" stroke="#14181f" stroke-width="8" stroke-linecap="round"/>
  <path d="M144,140 L156,162" stroke="#14181f" stroke-width="8" stroke-linecap="round"/>
  <!-- thân vạc -->
  <path d="M36,92 C32,140 70,158 100,158 C130,158 168,140 164,92 Q100,110 36,92 Z"
        fill="url(#drgCaulPot)" stroke="#0c0f14" stroke-width="3.5"/>
  <path d="M46,108 C50,132 68,146 84,151" fill="none" stroke="#6b7686" stroke-width="3" opacity=".55"/>
  <circle cx="60" cy="104" r="3" fill="#6b7686"/><circle cx="84" cy="110" r="3" fill="#6b7686"/>
  <circle cx="116" cy="110" r="3" fill="#6b7686"/><circle cx="140" cy="104" r="3" fill="#6b7686"/>
  <!-- đầu lâu trang trí -->
  <g opacity=".9">
    <ellipse cx="100" cy="128" rx="10" ry="9" fill="#d8d3c2" stroke="#0c0f14" stroke-width="2"/>
    <rect x="95" y="134" width="10" height="5" rx="2" fill="#d8d3c2" stroke="#0c0f14" stroke-width="1.5"/>
    <circle cx="96" cy="127" r="2.4" fill="#0c0f14"/><circle cx="104" cy="127" r="2.4" fill="#0c0f14"/>
  </g>
  <!-- miệng vạc + dung dịch -->
  <ellipse cx="100" cy="92" rx="66" ry="17" fill="#262c36" stroke="#0c0f14" stroke-width="3.5"/>
  <ellipse cx="100" cy="92" rx="53" ry="12" fill="#14181f"/>
  <ellipse class="drg-caul-liquid" cx="100" cy="92" rx="50" ry="10.5" fill="url(#drgCaulLiq)"/>
  <path d="M60,88 Q80,82 100,87 T140,88" fill="none" stroke="#e8ffb0" stroke-width="2.5" opacity=".8"/>
  <ellipse class="drg-caul-glow" cx="100" cy="86" rx="48" ry="15" fill="#b6ff6e" opacity=".22"/>
  <!-- bong bóng -->
  <circle class="drg-caul-bub drg-caul-bub--1" cx="82" cy="90" r="4" fill="#e8ffb0" opacity=".9"/>
  <circle class="drg-caul-bub drg-caul-bub--2" cx="104" cy="92" r="5.5" fill="#d2ff8e" opacity=".85"/>
  <circle class="drg-caul-bub drg-caul-bub--3" cx="122" cy="89" r="3.4" fill="#e8ffb0" opacity=".9"/>
  <circle class="drg-caul-bub drg-caul-bub--4" cx="93" cy="88" r="2.6" fill="#f2ffd9" opacity=".95"/>
  <!-- hơi độc -->
  <path class="drg-steam drg-steam--a" d="M76,72 C72,60 80,54 76,42" fill="none" stroke="#bfe8bf" stroke-width="4.5" stroke-linecap="round" opacity=".4"/>
  <path class="drg-steam drg-steam--b" d="M124,70 C120,58 128,52 124,40" fill="none" stroke="#bfe8bf" stroke-width="4" stroke-linecap="round" opacity=".35"/>
</svg>`;
  }

  /* ============================== SVG — anh hùng (chi tiết cao) ============================== */

  function heroEmblem(cls, c) {
    if (cls === 'sword') return `<path d="M70,89 L72.5,95 L70,101 L67.5,95 Z M66,94 h8" stroke="${c.trim}" stroke-width="1.8" fill="${c.accent}"/>`;
    if (cls === 'bow') return `<path d="M66,91 L74,99 M74,99 l-4,-1 M74,99 l-1,-4" stroke="${c.trim}" stroke-width="2" fill="none" stroke-linecap="round"/>`;
    if (cls === 'thunder') return `<path d="M72,88 L66,96 h4 L68,103 L75,94 h-4 Z" fill="${c.trim}"/>`;
    return `<path d="M70,88 C74,94 75,98 70,101 C65,98 66,94 70,88 Z" fill="${c.trim}"/>`;
  }

  function heroWeapon(cls, c, gid) {
    if (cls === 'sword') {
      return `
      <path d="M88,84 Q100,80 108,84" stroke="${c.robe[1]}" stroke-width="10" stroke-linecap="round" fill="none"/>
      <g transform="rotate(-14 112 62)">
        <polygon points="108,80 118,80 116,20 113,12 110,20" fill="url(#${gid}-metal)" stroke="#5a6b80" stroke-width="2"/>
        <line x1="113" y1="20" x2="113" y2="76" stroke="#8ea2b8" stroke-width="1.6"/>
        <path d="M109,26 L112,20 L114,26" fill="none" stroke="#fff" stroke-width="1.4" opacity=".8"/>
        <rect x="100" y="79" width="26" height="7" rx="3.5" fill="${c.accent}" stroke="#8a6f3c" stroke-width="2"/>
        <circle cx="100" cy="82.5" r="3" fill="#ff6b6b" stroke="#8a6f3c" stroke-width="1.5"/>
        <rect x="110" y="86" width="6" height="14" rx="3" fill="#7a4a1d" stroke="${OUTLINE}" stroke-width="1.5"/>
        <path d="M110,89 h6 M110,93 h6 M110,97 h6" stroke="#3d2413" stroke-width="1.2"/>
        <circle cx="113" cy="103" r="4" fill="${c.accent}" stroke="#8a6f3c" stroke-width="1.5"/>
      </g>
      <circle cx="110" cy="86" r="6.5" fill="#ffdcb8" stroke="#c98b52" stroke-width="2"/>
      <path d="M106,83 a6.5,6.5 0 0 1 8,0" fill="none" stroke="${c.trim}" stroke-width="3"/>`;
    }
    if (cls === 'bow') {
      return `
      <!-- bao tên sau lưng -->
      <g transform="rotate(24 44 92)">
        <rect x="38" y="72" width="14" height="34" rx="6" fill="#7a4a1d" stroke="${OUTLINE}" stroke-width="2"/>
        <rect x="38" y="72" width="14" height="7" rx="3.5" fill="#5b3a24"/>
        <line x1="43" y1="72" x2="46" y2="58" stroke="#8b5a2b" stroke-width="2.4"/>
        <line x1="48" y1="72" x2="52" y2="60" stroke="#8b5a2b" stroke-width="2.4"/>
        <path d="M44,60 l4,-6 3,7 Z" fill="#9fd4f5"/>
        <path d="M50,62 l4,-6 3,7 Z" fill="#dbeeff"/>
      </g>
      <path d="M88,82 Q100,78 106,78" stroke="${c.robe[1]}" stroke-width="10" stroke-linecap="round" fill="none"/>
      <path d="M104,44 C124,58 124,94 104,108" fill="none" stroke="#8a5a2b" stroke-width="6" stroke-linecap="round"/>
      <path d="M104,44 C120,58 120,94 104,108" fill="none" stroke="#c98b52" stroke-width="2" stroke-linecap="round" opacity=".8"/>
      <path d="M104,50 l-5,-8 M104,102 l-5,8" stroke="#8a5a2b" stroke-width="4" stroke-linecap="round"/>
      <line x1="102" y1="46" x2="102" y2="106" stroke="#e6edf5" stroke-width="1.6"/>
      <line x1="84" y1="76" x2="112" y2="76" stroke="#8ea2b8" stroke-width="3.4" stroke-linecap="round"/>
      <polygon points="112,76 104,71 104,81" fill="${c.accent}" stroke="#5a6b80" stroke-width="1.4"/>
      <path d="M84,76 l-6,-4 v8 Z" fill="#9fd4f5"/>
      <circle cx="102" cy="76" r="6.5" fill="#ffdcb8" stroke="#c98b52" stroke-width="2"/>`;
    }
    if (cls === 'alchemy') {
      return `
      <!-- dây đeo 3 lọ thuốc -->
      <path d="M50,82 L92,108" stroke="#5b3a24" stroke-width="5"/>
      <g transform="rotate(10 62 92)"><rect x="58" y="86" width="7" height="10" rx="3" fill="#63b3f5" stroke="${OUTLINE}" stroke-width="1.5"/><rect x="59" y="84" width="5" height="3" fill="#c9d6e2"/></g>
      <g transform="rotate(6 74 99)"><rect x="70" y="93" width="7" height="10" rx="3" fill="#ff8a5c" stroke="${OUTLINE}" stroke-width="1.5"/><rect x="71" y="91" width="5" height="3" fill="#c9d6e2"/></g>
      <path d="M88,84 Q98,78 104,74" stroke="${c.robe[1]}" stroke-width="10" stroke-linecap="round" fill="none"/>
      <g transform="rotate(12 108 56)">
        <rect x="104" y="34" width="9" height="12" rx="2.5" fill="#c9d6e2" stroke="#8ea2b8" stroke-width="1.5"/>
        <rect x="102" y="31" width="13" height="5" rx="2.5" fill="#9fb2c4"/>
        <rect x="105.5" y="27" width="6" height="6" rx="2" fill="#b07a3f"/>
        <circle cx="108.5" cy="62" r="17" fill="rgba(220,238,255,.5)" stroke="#8ea2b8" stroke-width="2.5"/>
        <path d="M93,66 a16,16 0 0 0 31,0 q-8,-6 -15.5,-2 q-8,4 -15.5,2" fill="${c.accent}"/>
        <path d="M96,60 Q104,56 112,60 T124,60" fill="none" stroke="#f2ffd9" stroke-width="2" opacity=".9"/>
        <circle cx="103" cy="66" r="2.4" fill="#fff" opacity=".9" class="drg-bubble"/>
        <circle cx="112" cy="70" r="1.8" fill="#fff" opacity=".7" class="drg-bubble drg-bubble--slow"/>
        <path d="M98,52 a17,17 0 0 1 8,-6" fill="none" stroke="#fff" stroke-width="2.5" opacity=".7"/>
      </g>
      <circle cx="104" cy="76" r="6.5" fill="#ffdcb8" stroke="#c98b52" stroke-width="2"/>`;
    }
    return `
      <path d="M88,84 Q98,82 104,84" stroke="${c.robe[1]}" stroke-width="10" stroke-linecap="round" fill="none"/>
      <line x1="106" y1="102" x2="112" y2="38" stroke="#7a4a1d" stroke-width="5.5" stroke-linecap="round"/>
      <path d="M107,96 q5,-3 4,-9 M108,82 q5,-3 4,-9 M110,68 q5,-3 4,-9" fill="none" stroke="#b07a3f" stroke-width="2"/>
      <path d="M104,40 A12,12 0 0 1 122,34" fill="none" stroke="#7a4a1d" stroke-width="4.5" stroke-linecap="round"/>
      <circle cx="113" cy="30" r="14" fill="${c.accent}" opacity=".28" class="drg-orb-glow"/>
      <circle cx="113" cy="30" r="9.5" fill="#2b1a4d" stroke="#8a6f3c" stroke-width="2"/>
      <path d="M114,22 L108,32 h4 L110,40 L118,29 h-4 Z" fill="${c.accent}"/>
      <circle class="drg-spark drg-spark--1" cx="100" cy="22" r="2" fill="${c.accent}"/>
      <circle class="drg-spark drg-spark--2" cx="126" cy="24" r="1.6" fill="#fff"/>
      <circle class="drg-spark drg-spark--3" cx="122" cy="42" r="1.8" fill="${c.accent}"/>
      <circle cx="106" cy="98" r="6.5" fill="#ffdcb8" stroke="#c98b52" stroke-width="2"/>`;
  }

  function heroHeadgear(cls, c) {
    if (cls === 'sword') {
      return `
      <path d="M46,30 Q58,44 54,50 Q64,40 66,30 Z M66,28 Q72,40 82,44 Q84,32 80,26 Z M58,26 Q62,34 70,36 Q70,28 66,24 Z" fill="${c.hair}" stroke="${OUTLINE}" stroke-width="2"/>
      <rect x="42" y="34" width="56" height="10" rx="5" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2.5"/>
      <rect x="42" y="36" width="56" height="3" fill="#fff" opacity=".25"/>
      <circle cx="70" cy="39" r="4.5" fill="${c.accent}" stroke="#8a6f3c" stroke-width="1.8"/>
      <path d="M98,39 l12,-8 -4,10 6,6 -13,-2 Z" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2"/>`;
    }
    if (cls === 'bow') {
      return `
      <path d="M44,42 Q48,50 46,56 Q52,48 54,42 Z" fill="${c.hair}" stroke="${OUTLINE}" stroke-width="1.8"/>
      <path d="M40,46 Q38,12 70,10 Q102,12 100,46 Q96,26 70,24 Q44,26 40,46 Z"
            fill="${c.cape}" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M42,44 Q44,40 48,42 Q50,38 55,40 Q57,36 62,38 Q66,34 70,37 Q74,34 78,38 Q83,36 85,40 Q90,38 92,42 Q96,40 98,44"
            fill="none" stroke="#dbeeff" stroke-width="3.5" stroke-linecap="round"/>
      <path d="M94,26 q14,-10 20,-4 q-8,2 -10,10 q-4,-4 -10,-6 Z" fill="${c.accent}" stroke="${OUTLINE}" stroke-width="2"/>
      <path d="M46,20 Q58,12 70,12" fill="none" stroke="#5ba3e8" stroke-width="2.5" opacity=".7"/>`;
    }
    if (cls === 'alchemy') {
      return `
      <path d="M46,34 Q52,44 50,50 Q58,42 60,34 Z M80,32 Q84,42 90,46 Q90,36 86,30 Z" fill="${c.hair}" stroke="${OUTLINE}" stroke-width="2"/>
      <path d="M40,36 Q40,14 62,10 Q92,6 100,24 Q102,32 96,36 Q70,28 40,36 Z" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M46,30 Q48,18 62,14" fill="none" stroke="#fff" stroke-width="2.5" opacity=".3"/>
      <circle cx="97" cy="20" r="5" fill="${c.accent}" stroke="${OUTLINE}" stroke-width="2"/>
      <!-- kính bảo hộ trên trán -->
      <rect x="44" y="34" width="52" height="7" rx="3.5" fill="#5b3a24" stroke="${OUTLINE}" stroke-width="2"/>
      <circle cx="58" cy="37" r="7.5" fill="#bfe8ff" stroke="#b07a3f" stroke-width="3"/>
      <circle cx="80" cy="37" r="7.5" fill="#bfe8ff" stroke="#b07a3f" stroke-width="3"/>
      <path d="M54,34 a7,7 0 0 1 5,-3 M76,34 a7,7 0 0 1 5,-3" stroke="#fff" stroke-width="2" fill="none" opacity=".85"/>`;
    }
    return `
      <path d="M44,40 Q46,50 44,56 Q52,48 54,40 Z M88,38 Q92,48 96,52 Q96,42 92,36 Z" fill="${c.hair}" stroke="${OUTLINE}" stroke-width="2"/>
      <path d="M46,28 Q60,-22 96,-10 Q82,-4 90,28 Z" fill="${c.robe[1]}" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M52,16 Q60,-8 78,-8" fill="none" stroke="#fff" stroke-width="2.5" opacity=".25"/>
      <rect x="60" y="4" width="9" height="9" rx="2" fill="${c.capeIn}" stroke="${OUTLINE}" stroke-width="1.5"/>
      <path d="M60,6 h9 M62,4 v9 M67,4 v9" stroke="${OUTLINE}" stroke-width="1" opacity=".6"/>
      <ellipse cx="68" cy="30" rx="34" ry="10" fill="${c.robe[1]}" stroke="${OUTLINE}" stroke-width="2.5"/>
      <path d="M36,30 Q68,40 100,30" fill="none" stroke="${c.trim}" stroke-width="3" opacity=".6"/>
      <rect x="52" y="16" width="32" height="8" rx="4" fill="${c.accent}" stroke="#8a6f3c" stroke-width="2"/>
      <rect x="64" y="14.5" width="9" height="11" rx="2" fill="#8a6f3c"/>
      <circle cx="95" cy="-9" r="4.5" fill="${c.accent}" class="drg-hat-star"/>
      <circle cx="47" cy="24" r="2.5" fill="${c.accent}" class="drg-hat-star" style="animation-delay:-.9s"/>`;
  }

  function heroSVG(cls) {
    const c = CLASSES[cls];
    const gid = `drgH-${cls}`;
    return `
<svg viewBox="0 0 140 170" xmlns="http://www.w3.org/2000/svg" class="drg-hero-svg" aria-hidden="true">
  <defs>
    <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${c.robe[0]}"/><stop offset="1" stop-color="${c.robe[1]}"/>
    </linearGradient>
    <linearGradient id="${gid}-metal" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#f2f6fa"/><stop offset=".5" stop-color="#c3cfdd"/><stop offset="1" stop-color="#8ea2b8"/>
    </linearGradient>
    <radialGradient id="${gid}-sh" cx=".5" cy=".5" r=".5">
      <stop offset="0" stop-color="rgba(10,5,20,.5)"/><stop offset="1" stop-color="rgba(10,5,20,0)"/>
    </radialGradient>
  </defs>
  <ellipse cx="70" cy="162" rx="36" ry="8" fill="url(#${gid}-sh)"/>

  <!-- áo choàng 2 lớp -->
  <g class="drg-cape">
    <path d="M54,72 C32,84 22,120 26,150 L36,143 L46,151 L56,143 L64,150 L64,76 Z"
          fill="${c.cape}" stroke="${OUTLINE}" stroke-width="2.5"/>
    <path d="M56,78 C42,90 34,118 36,142 L46,148 L56,140 L62,146 L61,80 Z"
          fill="${c.capeIn}" opacity=".55"/>
    <path d="M50,82 C42,96 38,116 38,132" fill="none" stroke="${OUTLINE}" stroke-width="1.8" opacity=".3"/>
  </g>

  <!-- tay sau + găng -->
  <path d="M52,88 Q40,96 36,104" stroke="${c.robe[1]}" stroke-width="10" stroke-linecap="round" fill="none"/>
  <circle cx="34" cy="107" r="6.5" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2"/>

  <!-- ủng 2 tông màu -->
  <path d="M50,142 h18 v11 q0,7 -9,7 h-13 q-7,0 -5,-9 l3,-9 Z" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2.5"/>
  <rect x="49" y="142" width="20" height="6" rx="3" fill="${c.capeIn}" stroke="${OUTLINE}" stroke-width="1.8"/>
  <circle cx="60" cy="153" r="2.6" fill="${c.accent}" stroke="#8a6f3c" stroke-width="1.2"/>
  <path d="M72,142 h18 l3,9 q2,9 -5,9 h-13 q-9,0 -9,-7 v-11 Z" fill="${c.trim}" stroke="${OUTLINE}" stroke-width="2.5"/>
  <rect x="71" y="142" width="20" height="6" rx="3" fill="${c.capeIn}" stroke="${OUTLINE}" stroke-width="1.8"/>
  <circle cx="80" cy="153" r="2.6" fill="${c.accent}" stroke="#8a6f3c" stroke-width="1.2"/>

  <!-- áo giáp/áo choàng thân -->
  <path d="M70,74 C48,76 40,108 36,146 Q70,158 104,146 C100,108 92,76 70,74 Z"
        fill="url(#${gid})" stroke="${OUTLINE}" stroke-width="3"/>
  <path d="M36,146 Q70,158 104,146 L102,137 Q70,148 38,137 Z" fill="${c.trim}"/>
  <path d="M52,140 l4,-4 4,4 -4,4 Z M66,143 l4,-4 4,4 -4,4 Z M80,141 l4,-4 4,4 -4,4 Z" fill="${c.accent}" opacity=".9"/>
  <path d="M56,96 C54,112 55,128 58,140 M84,96 C86,112 85,128 82,140" fill="none" stroke="${OUTLINE}" stroke-width="2.2" opacity=".18"/>
  <path d="M92,84 C98,102 100,124 100,140" fill="none" stroke="#000" stroke-width="7" opacity=".07"/>
  <path d="M48,80 Q44,96 42,116" fill="none" stroke="#fff" stroke-width="3" opacity=".25"/>
  <circle cx="70" cy="95" r="9.5" fill="${c.accent}" stroke="${OUTLINE}" stroke-width="2.2"/>
  <circle cx="70" cy="95" r="9.5" fill="none" stroke="#fff" stroke-width="1.4" opacity=".5" stroke-dasharray="3 4"/>
  ${heroEmblem(cls, c)}

  <!-- thắt lưng + túi + lọ thuốc -->
  <rect x="44" y="112" width="52" height="9" rx="4.5" fill="#3a2b4d" stroke="${OUTLINE}" stroke-width="2"/>
  <rect x="63" y="109.5" width="14" height="14" rx="3" fill="${c.accent}" stroke="#8a6f3c" stroke-width="2"/>
  <rect x="66.5" y="113" width="7" height="7" rx="1.5" fill="#8a6f3c"/>
  <rect x="49" y="120" width="11" height="10" rx="3" fill="#7a4a1d" stroke="${OUTLINE}" stroke-width="1.8"/>
  <path d="M49,123 h11" stroke="${OUTLINE}" stroke-width="1.2"/>
  <g><rect x="84" y="120" width="6.5" height="9" rx="3" fill="${c.accent}" stroke="${OUTLINE}" stroke-width="1.5"/>
  <rect x="85.2" y="117.5" width="4" height="3" fill="#c9d6e2"/></g>

  <!-- giáp vai -->
  <ellipse cx="50" cy="82" rx="11" ry="8.5" fill="${c.robe[1]}" stroke="${OUTLINE}" stroke-width="2.5"/>
  <path d="M42,79 a11,7 0 0 1 10,-4" fill="none" stroke="#fff" stroke-width="2" opacity=".4"/>
  <ellipse cx="90" cy="82" rx="11" ry="8.5" fill="${c.robe[1]}" stroke="${OUTLINE}" stroke-width="2.5"/>
  <path d="M82,79 a11,7 0 0 1 10,-4" fill="none" stroke="#fff" stroke-width="2" opacity=".4"/>

  <!-- tay trước + vũ khí -->
  ${heroWeapon(cls, c, gid)}

  <!-- đầu -->
  <circle cx="45" cy="56" r="5" fill="#ffdcb8" stroke="#c98b52" stroke-width="1.8"/>
  <circle cx="70" cy="52" r="27" fill="#ffdcb8" stroke="#c98b52" stroke-width="2.5"/>
  <path d="M48,66 Q70,78 92,66" fill="none" stroke="#e8b088" stroke-width="3" opacity=".5"/>
  <!-- mắt to có tròng màu -->
  <ellipse cx="60" cy="54" rx="6" ry="7.2" fill="#fff" stroke="${OUTLINE}" stroke-width="1.6"/>
  <circle cx="60.5" cy="55" r="4" fill="${c.eye}"/>
  <circle cx="60.5" cy="55" r="1.9" fill="#1c1226"/>
  <circle cx="59" cy="52.6" r="1.5" fill="#fff"/>
  <circle cx="62.3" cy="57" r="0.8" fill="#fff" opacity=".9"/>
  <path d="M53,48 Q60,44 67,48" fill="none" stroke="${OUTLINE}" stroke-width="2.4" stroke-linecap="round"/>
  <ellipse cx="82" cy="54" rx="6" ry="7.2" fill="#fff" stroke="${OUTLINE}" stroke-width="1.6"/>
  <circle cx="82.5" cy="55" r="4" fill="${c.eye}"/>
  <circle cx="82.5" cy="55" r="1.9" fill="#1c1226"/>
  <circle cx="81" cy="52.6" r="1.5" fill="#fff"/>
  <circle cx="84.3" cy="57" r="0.8" fill="#fff" opacity=".9"/>
  <path d="M75,48 Q82,44 89,48" fill="none" stroke="${OUTLINE}" stroke-width="2.4" stroke-linecap="round"/>
  <path d="M52,42 L64,40 M78,40 L90,42" stroke="${c.hair}" stroke-width="3.5" stroke-linecap="round"/>
  <ellipse cx="51" cy="62" rx="4.8" ry="2.8" fill="#ff9d9d" opacity=".6"/>
  <ellipse cx="89" cy="62" rx="4.8" ry="2.8" fill="#ff9d9d" opacity=".6"/>
  <path d="M64,66 Q70,72 76,66 Q70,70 64,66 Z" fill="#a3503c"/>
  ${heroHeadgear(cls, c)}
</svg>`;
  }

  /* ============================== SVG hiệu ứng nhỏ ============================== */

  function moleculeSVG(variant) {
    if (variant === 0) {
      return `<svg viewBox="0 0 90 90"><g stroke="#9fdcff" stroke-width="2.5" fill="none">
        <line x1="24" y1="30" x2="46" y2="46"/><line x1="46" y1="46" x2="70" y2="34"/><line x1="46" y1="46" x2="44" y2="72"/></g>
        <circle cx="24" cy="30" r="9" fill="#e05252"/><circle cx="70" cy="34" r="7" fill="#f5f5f5"/>
        <circle cx="46" cy="46" r="11" fill="#4a90d9"/><circle cx="44" cy="72" r="7" fill="#f5f5f5"/></svg>`;
    }
    if (variant === 1) {
      return `<svg viewBox="0 0 90 90"><polygon points="45,12 74,29 74,61 45,78 16,61 16,29"
        fill="none" stroke="#c9a7ff" stroke-width="3"/><circle cx="45" cy="45" r="17" fill="none" stroke="#c9a7ff" stroke-width="2.5"/></svg>`;
    }
    return `<svg viewBox="0 0 90 90"><g stroke="#7ee65c" stroke-width="2.5" fill="none">
      <line x1="20" y1="46" x2="45" y2="46"/><line x1="45" y1="46" x2="68" y2="30"/><line x1="45" y1="46" x2="68" y2="62"/></g>
      <circle cx="20" cy="46" r="8" fill="#3b3b3b"/><circle cx="45" cy="46" r="10" fill="#3b3b3b"/>
      <circle cx="68" cy="30" r="6.5" fill="#e05252"/><circle cx="68" cy="62" r="6.5" fill="#e05252"/></svg>`;
  }

  function shelfSVG() {
    return `<svg viewBox="0 0 150 80" class="drg-shelf-svg">
      <rect x="4" y="58" width="142" height="9" rx="3" fill="#5b3a24" stroke="#2e1c10" stroke-width="2.5"/>
      <path d="M10,58 L4,74 M140,58 L146,74" stroke="#2e1c10" stroke-width="4" stroke-linecap="round"/>
      <g><path d="M28,54 h16 l4,-14 h-24 Z" fill="rgba(160,220,255,.35)" stroke="#8ea2b8" stroke-width="2"/>
        <path d="M30,54 h12 l2,-7 h-16 Z" fill="#63b3f5" class="drg-shelf-glow"/>
        <rect x="32" y="32" width="8" height="9" fill="rgba(200,220,235,.5)" stroke="#8ea2b8" stroke-width="1.5"/></g>
      <g><circle cx="75" cy="46" r="12" fill="rgba(255,180,120,.3)" stroke="#8ea2b8" stroke-width="2"/>
        <path d="M64,50 a12,12 0 0 0 22,0 q-6,-4 -11,-1 q-6,3 -11,1" fill="#ff8a5c" class="drg-shelf-glow" style="animation-delay:-1.1s"/>
        <rect x="71" y="28" width="8" height="8" fill="rgba(200,220,235,.5)" stroke="#8ea2b8" stroke-width="1.5"/></g>
      <g><rect x="106" y="30" width="9" height="26" rx="4" fill="rgba(180,255,160,.35)" stroke="#8ea2b8" stroke-width="2"/>
        <rect x="106" y="42" width="9" height="14" rx="4" fill="#7ee65c" class="drg-shelf-glow" style="animation-delay:-.6s"/></g>
    </svg>`;
  }

  /* ============================== Particle engine (canvas) ============================== */

  function createParticles(canvas) {
    const ctx = canvas.getContext('2d');
    let W = 0;
    let H = 0;
    const ps = [];
    let emberBoost = 1;

    function resize() {
      W = canvas.width = canvas.clientWidth;
      H = canvas.height = canvas.clientHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    function spawnEmber() {
      ps.push({
        x: Math.random() * W,
        y: H + 8,
        vx: (Math.random() - 0.5) * 0.5,
        vy: -(0.35 + Math.random() * 0.95),
        r: 1 + Math.random() * 2.6,
        life: 1,
        decay: 0.0028 + Math.random() * 0.004,
        gravity: 0,
        sway: Math.random() * Math.PI * 2,
        color: Math.random() < 0.8 ? '255,150,60' : '255,220,130',
      });
    }

    function burst(x, y, rgb, count, power) {
      for (let i = 0; i < count; i++) {
        const ang = Math.random() * Math.PI * 2;
        const spd = power * (0.35 + Math.random());
        ps.push({
          x, y,
          vx: Math.cos(ang) * spd,
          vy: Math.sin(ang) * spd - power * 0.35,
          r: 1.5 + Math.random() * 3.4,
          life: 1,
          decay: 0.016 + Math.random() * 0.02,
          gravity: 0.09,
          sway: 0,
          color: rgb,
        });
      }
    }

    /** Bong bóng khí bay lên (hiệu ứng phản ứng hóa học). */
    function bubbles(x, y, rgb, count) {
      for (let i = 0; i < count; i++) {
        ps.push({
          x: x + (Math.random() - 0.5) * 46,
          y: y + (Math.random() - 0.5) * 10,
          vx: (Math.random() - 0.5) * 0.6,
          vy: -(0.8 + Math.random() * 1.6),
          r: 1.5 + Math.random() * 3,
          life: 1,
          decay: 0.012 + Math.random() * 0.015,
          gravity: -0.01,
          sway: Math.random() * Math.PI * 2,
          color: rgb,
        });
      }
    }

    let lastEmber = 0;
    function loop(now) {
      ctx.clearRect(0, 0, W, H);
      ctx.globalCompositeOperation = 'lighter';
      if (now - lastEmber > 130 / emberBoost) {
        lastEmber = now;
        spawnEmber();
      }
      for (let i = ps.length - 1; i >= 0; i--) {
        const p = ps[i];
        p.life -= p.decay;
        if (p.life <= 0) {
          ps.splice(i, 1);
          continue;
        }
        p.sway += 0.03;
        p.x += p.vx + (p.gravity <= 0 ? Math.sin(p.sway) * 0.35 : 0);
        p.y += p.vy;
        p.vy += p.gravity;
        const a = Math.max(0, p.life);
        ctx.fillStyle = `rgba(${p.color},${(a * 0.28).toFixed(3)})`;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r * 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = `rgba(${p.color},${a.toFixed(3)})`;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }
      requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);

    return {
      burst,
      bubbles,
      setEmberBoost(v) { emberBoost = v; },
    };
  }

  /* ============================== Âm thanh WebAudio ============================== */

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

    function tone(freq, time, dur, type, gain, slideTo) {
      const a = ac();
      if (!a || muted) return;
      const osc = a.createOscillator();
      const g = a.createGain();
      osc.type = type;
      osc.frequency.setValueAtTime(freq, a.currentTime + time);
      if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, a.currentTime + time + dur);
      g.gain.setValueAtTime(0.0001, a.currentTime + time);
      g.gain.exponentialRampToValueAtTime(gain, a.currentTime + time + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + time + dur);
      osc.connect(g).connect(a.destination);
      osc.start(a.currentTime + time);
      osc.stop(a.currentTime + time + dur + 0.05);
    }

    function noise(time, dur, gain, freq) {
      const a = ac();
      if (!a || muted) return;
      const len = Math.floor(a.sampleRate * dur);
      const buf = a.createBuffer(1, len, a.sampleRate);
      const data = buf.getChannelData(0);
      for (let i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / len);
      const src = a.createBufferSource();
      src.buffer = buf;
      const g = a.createGain();
      g.gain.setValueAtTime(gain, a.currentTime + time);
      const f = a.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = freq || 900;
      src.connect(f).connect(g).connect(a.destination);
      src.start(a.currentTime + time);
    }

    return {
      attack() { tone(500, 0, 0.14, 'triangle', 0.12, 900); },
      hit() { noise(0, 0.22, 0.16); tone(150, 0, 0.18, 'square', 0.12, 70); },
      crit() { noise(0, 0.3, 0.2); tone(220, 0, 0.12, 'square', 0.16); tone(440, 0.06, 0.18, 'square', 0.14, 880); },
      lightning() { noise(0, 0.35, 0.22, 2400); tone(1400, 0, 0.25, 'sawtooth', 0.08, 120); },
      roar() { tone(90, 0, 0.7, 'sawtooth', 0.18, 45); noise(0.05, 0.5, 0.12); },
      brew() { for (let i = 0; i < 6; i++) tone(280 + Math.random() * 260, i * 0.09, 0.1, 'sine', 0.09); noise(0.1, 0.5, 0.06, 1600); },
      acid() { noise(0, 0.3, 0.14, 1400); tone(700, 0, 0.25, 'sine', 0.1, 180); },
      ultimate() { [330, 415, 494, 660, 830].forEach((f, i) => tone(f, i * 0.09, 0.22, 'triangle', 0.13)); noise(0.5, 0.4, 0.2); },
      win() { [523, 659, 784, 1047, 1319].forEach((f, i) => tone(f, i * 0.13, 0.32, 'triangle', 0.14)); },
      lose() { [392, 330, 262, 196].forEach((f, i) => tone(f, i * 0.22, 0.35, 'triangle', 0.13)); },
      toggle() { muted = !muted; return muted; },
    };
  })();

  /* ============================== Trạng thái ============================== */

  const state = {
    bossHp: 300,
    bossMax: 300,
    hearts: 5,
    combo: 0,
    heroes: [],
    finished: false,
    enraged: false,
    autoTimer: null,
    acting: false,
  };

  let stageEl = null;
  let fxEl = null;
  let bossEl = null;
  let caulEl = null;
  let particles = null;

  /* ============================== Toạ độ ============================== */

  function stagePos(el, fx, fy) {
    const r = el.getBoundingClientRect();
    const s = stageEl.getBoundingClientRect();
    return { x: r.left - s.left + r.width * fx, y: r.top - s.top + r.height * fy };
  }

  function bossChest() { return stagePos(bossEl, 0.52, 0.63); }
  function bossMouth() { return stagePos(bossEl, 0.18, 0.26); }
  function caulTop() { return stagePos(caulEl, 0.5, 0.42); }
  function heroPos(h) { return stagePos(h.el.querySelector('.drg-hero__sprite'), 0.5, 0.5); }

  /* ============================== Render ============================== */

  function renderBossHp(animate) {
    const bar = document.getElementById('drgBossBar');
    if (!bar) return;
    const pct = Math.max(0, (state.bossHp / state.bossMax) * 100);
    const fill = bar.querySelector('.drg-bossbar__fill');
    const ghost = bar.querySelector('.drg-bossbar__ghost');
    const label = bar.querySelector('.drg-bossbar__value');
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
    if (label) label.textContent = `${Math.max(0, state.bossHp)} / ${state.bossMax}`;
  }

  function renderHearts() {
    const wrap = document.getElementById('drgHearts');
    if (!wrap) return;
    const c = cfg();
    wrap.innerHTML = '';
    for (let i = 0; i < c.hearts; i++) {
      const s = document.createElement('span');
      s.className = 'drg-heart' + (i < state.hearts ? '' : ' is-empty');
      s.textContent = i < state.hearts ? '❤️' : '🖤';
      wrap.appendChild(s);
    }
  }

  function renderCombo() {
    const wrap = document.getElementById('drgCombo');
    if (!wrap) return;
    const c = cfg();
    wrap.innerHTML = '';
    for (let i = 0; i < c.comboNeed; i++) {
      const s = document.createElement('span');
      s.className = 'drg-combo-cell' + (i < state.combo ? ' is-filled' : '');
      wrap.appendChild(s);
    }
    wrap.classList.toggle('is-full', state.combo >= c.comboNeed);
  }

  function renderHeroes() {
    const wrap = stageEl.querySelector('[data-heroes]');
    wrap.innerHTML = '';
    state.heroes = FAKE_HEROES.map((h, i) => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'drg-hero';
      el.title = `${h.name} — ${CLASSES[h.cls].label} (bấm = trả lời đúng)`;
      el.innerHTML = `
        <span class="drg-hero__sprite">${heroSVG(h.cls)}</span>
        <span class="drg-hero__plate">${h.name}</span>`;
      const hero = { ...h, id: i, el };
      el.addEventListener('click', () => answerCorrect(hero));
      wrap.appendChild(el);
      return hero;
    });
  }

  /* ============================== FX helpers ============================== */

  function flashClass(el, cls, ms) {
    if (!el) return;
    el.classList.add(cls);
    setTimeout(() => el.classList.remove(cls), ms);
  }

  function shake(strong) {
    const cls = strong ? 'is-shaking-hard' : 'is-shaking';
    stageEl.classList.remove(cls);
    void stageEl.offsetWidth;
    stageEl.classList.add(cls);
    setTimeout(() => stageEl.classList.remove(cls), strong ? 500 : 350);
  }

  function screenFlash(color) {
    const f = document.createElement('div');
    f.className = 'drg-flash';
    if (color) f.style.background = color;
    fxEl.appendChild(f);
    setTimeout(() => f.remove(), 320);
  }

  function floatText(x, y, text, cls, color) {
    const el = document.createElement('div');
    el.className = `drg-float ${cls || ''}`;
    el.textContent = text;
    el.style.left = `${x}px`;
    el.style.top = `${y}px`;
    if (color) el.style.color = color;
    el.style.setProperty('--rot', `${(Math.random() - 0.5) * 16}deg`);
    fxEl.appendChild(el);
    setTimeout(() => el.remove(), 1400);
  }

  function shockRing(x, y, color) {
    const r = document.createElement('span');
    r.className = 'drg-ring';
    r.style.left = `${x}px`;
    r.style.top = `${y}px`;
    r.style.borderColor = color;
    fxEl.appendChild(r);
    setTimeout(() => r.remove(), 600);
  }

  function announce(text, cls, ms) {
    const el = document.getElementById('drgAnnounce');
    if (!el) return;
    el.textContent = text;
    el.className = `drg-announce ${cls || ''}`;
    void el.offsetWidth;
    el.classList.add('is-showing');
    setTimeout(() => el.classList.remove('is-showing'), ms || 1500);
  }

  /** Ký hiệu nguyên tố / phân tử văng ra khi đánh trúng. */
  function elementChips(x, y, count) {
    for (let i = 0; i < count; i++) {
      const e = ELEMENT_CHIPS[Math.floor(Math.random() * ELEMENT_CHIPS.length)];
      const chip = document.createElement('span');
      chip.className = 'drg-chip';
      chip.textContent = e.t;
      chip.style.left = `${x}px`;
      chip.style.top = `${y}px`;
      chip.style.background = e.bg;
      chip.style.setProperty('--dx', `${(Math.random() - 0.5) * 150}px`);
      chip.style.setProperty('--dy', `${-30 - Math.random() * 90}px`);
      chip.style.setProperty('--rot', `${(Math.random() - 0.5) * 70}deg`);
      chip.style.animationDelay = `${i * 60}ms`;
      fxEl.appendChild(chip);
      setTimeout(() => chip.remove(), 1400 + i * 60);
    }
  }

  /** Vòng benzen lan ra khi chí mạng (phản ứng mạnh). */
  function benzeneRing(x, y) {
    const b = document.createElement('div');
    b.className = 'drg-benzene';
    b.style.left = `${x}px`;
    b.style.top = `${y}px`;
    b.innerHTML = `<svg viewBox="0 0 100 100">
      <polygon points="50,8 88,29 88,71 50,92 12,71 12,29" fill="none" stroke="#ffd166" stroke-width="5"/>
      <circle cx="50" cy="50" r="22" fill="none" stroke="#ffd166" stroke-width="4"/>
    </svg>`;
    fxEl.appendChild(b);
    setTimeout(() => b.remove(), 750);
  }

  /* ============================== Skill FX theo class ============================== */

  function fxSlash(hero, done) {
    const target = bossChest();
    const from = heroPos(hero);
    const sprite = hero.el.querySelector('.drg-hero__sprite');
    sprite.style.transition = 'translate .3s cubic-bezier(.3,1.2,.5,1)';
    sprite.style.translate = `${target.x - from.x - 80}px ${target.y - from.y}px`;
    setTimeout(() => {
      const slash = document.createElement('div');
      slash.className = 'drg-slash';
      slash.style.left = `${target.x}px`;
      slash.style.top = `${target.y}px`;
      fxEl.appendChild(slash);
      setTimeout(() => slash.remove(), 450);
      done(target);
      setTimeout(() => {
        sprite.style.translate = '0px 0px';
        setTimeout(() => { sprite.style.transition = ''; }, 350);
      }, 160);
    }, 300);
  }

  function fxArrow(hero, done) {
    const from = heroPos(hero);
    const to = bossChest();
    const arrow = document.createElement('div');
    arrow.className = 'drg-arrow';
    const ang = (Math.atan2(to.y - from.y, to.x - from.x) * 180) / Math.PI;
    arrow.style.setProperty('--ang', `${ang}deg`);
    fxEl.appendChild(arrow);
    const start = performance.now();
    const dur = 340;
    (function step(now) {
      const t = Math.min(1, (now - start) / dur);
      const x = from.x + (to.x - from.x) * t;
      const y = from.y + (to.y - from.y) * t - Math.sin(t * Math.PI) * 30;
      arrow.style.transform = `translate(${x}px, ${y}px) rotate(var(--ang))`;
      if (t % 0.2 < 0.08) particles.burst(x, y, CLASSES.bow.rgb, 1, 1.5);
      if (t < 1) requestAnimationFrame(step);
      else { arrow.remove(); done(to); }
    })(performance.now());
  }

  function fxLightning(hero, done) {
    const to = bossChest();
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('class', 'drg-lightning');
    const pts = [];
    let x = to.x + (Math.random() - 0.5) * 60;
    for (let y = -10; y < to.y; y += to.y / 7) {
      pts.push(`${x + (Math.random() - 0.5) * 46},${y}`);
      x = to.x + (Math.random() - 0.5) * 34;
    }
    pts.push(`${to.x},${to.y}`);
    const glow = document.createElementNS(svgNS, 'polyline');
    glow.setAttribute('points', pts.join(' '));
    glow.setAttribute('class', 'drg-lightning__glow');
    const core = document.createElementNS(svgNS, 'polyline');
    core.setAttribute('points', pts.join(' '));
    core.setAttribute('class', 'drg-lightning__core');
    svg.appendChild(glow);
    svg.appendChild(core);
    fxEl.appendChild(svg);
    sound.lightning();
    screenFlash('rgba(255,240,180,.5)');
    setTimeout(() => { svg.remove(); done(to); }, 260);
  }

  function fxPotion(hero, done) {
    const from = heroPos(hero);
    const to = bossChest();
    const p = document.createElement('div');
    p.className = 'drg-potion';
    p.innerHTML = `<svg viewBox="0 0 40 40"><rect x="16.5" y="3" width="7" height="8" rx="2" fill="#c9d6e2"/>
      <path d="M14,10 Q4,28 20,31 Q36,28 26,10 Z" fill="#8ef060"/>
      <circle cx="17" cy="24" r="2.2" fill="#fff" opacity=".85"/></svg>`;
    fxEl.appendChild(p);
    const start = performance.now();
    const dur = 480;
    const peak = Math.min(from.y, to.y) - 110;
    (function step(now) {
      const t = Math.min(1, (now - start) / dur);
      const it = 1 - t;
      const x = it * it * from.x + 2 * it * t * ((from.x + to.x) / 2) + t * t * to.x;
      const y = it * it * from.y + 2 * it * t * peak + t * t * to.y;
      p.style.transform = `translate(${x - 20}px, ${y - 20}px) rotate(${t * 640}deg)`;
      if (t < 1) requestAnimationFrame(step);
      else {
        p.remove();
        const cloud = document.createElement('div');
        cloud.className = 'drg-poison';
        cloud.style.left = `${to.x}px`;
        cloud.style.top = `${to.y}px`;
        cloud.innerHTML = '<span></span><span></span><span></span><span></span>';
        fxEl.appendChild(cloud);
        particles.bubbles(to.x, to.y, '150,240,120', 14);
        setTimeout(() => cloud.remove(), 1400);
        done(to);
      }
    })(performance.now());
  }

  const SKILL_FX = { sword: fxSlash, bow: fxArrow, thunder: fxLightning, alchemy: fxPotion };

  /* ============================== Gameplay ============================== */

  function dealDamage(amount, pos, rgb, isCrit) {
    if (state.finished) return;
    sound[isCrit ? 'crit' : 'hit']();
    shake(isCrit);
    particles.burst(pos.x, pos.y, rgb, isCrit ? 46 : 26, isCrit ? 7 : 4.5);
    shockRing(pos.x, pos.y, isCrit ? '#ffd166' : '#fff');
    flashClass(bossEl, 'is-hit', 480);
    floatText(pos.x, pos.y - 60, `-${amount}`, isCrit ? 'drg-float--crit' : 'drg-float--dmg');
    elementChips(pos.x, pos.y, isCrit ? 4 : 2);
    if (isCrit) {
      benzeneRing(pos.x, pos.y);
      floatText(pos.x, pos.y - 112, 'PHẢN ỨNG NỔ!', 'drg-float--critlabel');
    }

    state.bossHp = Math.max(0, state.bossHp - amount);
    renderBossHp(true);
    checkEnrage();
    checkEnd();
  }

  function answerCorrect(hero) {
    if (state.finished || state.acting) return;
    state.acting = true;
    const h = hero || state.heroes[Math.floor(Math.random() * state.heroes.length)];
    const c = cfg();
    const isCrit = Math.random() * 100 < c.critPct;
    const dmg = isCrit ? c.dmg * 2 : c.dmg;

    sound.attack();
    flashClass(h.el, 'is-casting', 500);

    SKILL_FX[h.cls](h, (pos) => {
      state.acting = false;
      if (state.finished) return;
      dealDamage(dmg, pos, CLASSES[h.cls].rgb, isCrit);
      if (state.finished) return;
      state.combo += 1;
      renderCombo();
      if (state.combo >= c.comboNeed) {
        setTimeout(ultimate, 420);
      }
    });
  }

  /* ---- Đòn boss: điều chế axit trong vạc rồi bắn vào đội hình ---- */

  function launchAcid(from, to, delay, onHit) {
    setTimeout(() => {
      const glob = document.createElement('div');
      glob.className = 'drg-acid';
      glob.innerHTML = `<svg viewBox="0 0 34 34">
        <path d="M17,3 C24,12 29,18 29,23 a12,11 0 0 1 -24,0 C5,18 10,12 17,3 Z" fill="#8ef060" stroke="#2e7d32" stroke-width="2"/>
        <circle cx="13" cy="22" r="2.4" fill="#e4ffb0"/></svg>`;
      fxEl.appendChild(glob);
      const start = performance.now();
      const dur = 520;
      const peak = Math.min(from.y, to.y) - 130;
      (function step(now) {
        const t = Math.min(1, (now - start) / dur);
        const it = 1 - t;
        const x = it * it * from.x + 2 * it * t * ((from.x + to.x) / 2) + t * t * to.x;
        const y = it * it * from.y + 2 * it * t * peak + t * t * to.y;
        glob.style.transform = `translate(${x - 17}px, ${y - 17}px) rotate(${t * 200}deg)`;
        if (t < 1) requestAnimationFrame(step);
        else {
          glob.remove();
          // giọt axit bắn toé + bọt khí sủi
          const splash = document.createElement('div');
          splash.className = 'drg-splash';
          splash.style.left = `${to.x}px`;
          splash.style.top = `${to.y}px`;
          for (let i = 0; i < 6; i++) {
            const d = document.createElement('span');
            d.style.setProperty('--dx', `${(Math.random() - 0.5) * 90}px`);
            d.style.setProperty('--dy', `${-16 - Math.random() * 46}px`);
            splash.appendChild(d);
          }
          fxEl.appendChild(splash);
          setTimeout(() => splash.remove(), 800);
          particles.burst(to.x, to.y, '130,230,100', 14, 3.5);
          particles.bubbles(to.x, to.y - 6, '180,255,160', 10);
          sound.acid();
          onHit();
        }
      })(performance.now());
    }, delay);
  }

  function answerWrong() {
    if (state.finished || state.acting) return;
    state.acting = true;
    state.combo = 0;
    renderCombo();

    sound.roar();
    bossEl.classList.add('is-attacking');
    caulEl.classList.add('is-brewing');
    announce('🧪 Hắc Long điều chế axit!', 'drg-announce--danger', 1600);

    // Pha 1: phun lửa nấu vạc
    setTimeout(() => {
      const mouth = bossMouth();
      const pot = caulTop();
      const dx = pot.x - mouth.x;
      const dy = pot.y - mouth.y;
      const breath = document.createElement('div');
      breath.className = 'drg-breath';
      breath.style.left = `${mouth.x}px`;
      breath.style.top = `${mouth.y}px`;
      breath.style.setProperty('--len', `${Math.hypot(dx, dy) + 40}px`);
      breath.style.setProperty('--ang', `${(Math.atan2(dy, dx) * 180) / Math.PI}deg`);
      fxEl.appendChild(breath);
      particles.burst(pot.x, pot.y - 10, '255,150,60', 16, 3.5);
      setTimeout(() => breath.remove(), 900);
    }, 350);

    // Pha 2: vạc sôi trào
    setTimeout(() => {
      sound.brew();
      caulEl.classList.add('is-erupting');
      const pot = caulTop();
      particles.bubbles(pot.x, pot.y - 6, '180,255,160', 26);
      particles.burst(pot.x, pot.y - 12, '130,230,100', 20, 4.5);
      screenFlash('rgba(120,230,90,.22)');
      shake(false);
      elementChips(pot.x, pot.y - 24, 3);
    }, 1000);

    // Pha 3: bắn axit vào từng anh hùng
    setTimeout(() => {
      const pot = caulTop();
      let hits = 0;
      state.heroes.forEach((h, i) => {
        launchAcid(pot, heroPos(h), i * 130, () => {
          flashClass(h.el, 'is-acid', 900);
          if (++hits === state.heroes.length) {
            bossEl.classList.remove('is-attacking');
            caulEl.classList.remove('is-brewing', 'is-erupting');
            state.acting = false;
            state.hearts = Math.max(0, state.hearts - 1);
            renderHearts();
            const hw = stagePos(document.getElementById('drgHearts'), 0.5, 0);
            floatText(hw.x, hw.y, '-1 ❤️', 'drg-float--heart');
            checkEnd();
          }
        });
      });
    }, 1450);
  }

  /* ---- Ultimate: chuỗi phản ứng nhiệt màu (flame test) ---- */

  function ultimate() {
    if (state.finished) return;
    const c = cfg();
    state.combo = 0;
    renderCombo();
    state.acting = true;

    sound.ultimate();
    announce('🧪 BÃO PHẢN ỨNG NHIỆT MÀU!', 'drg-announce--ultimate', 2400);
    stageEl.classList.add('is-ultimate');

    const chest = bossChest();
    const total = Math.round(c.dmg * state.heroes.length * 1.5);
    let landed = 0;

    FLAME_TESTS.forEach((ft, i) => {
      setTimeout(() => {
        const tx = chest.x + (Math.random() - 0.5) * 150;
        const ty = chest.y + (Math.random() - 0.5) * 80;
        const flask = document.createElement('div');
        flask.className = 'drg-flaskfall';
        flask.innerHTML = `<svg viewBox="0 0 40 48">
          <rect x="16" y="2" width="8" height="10" rx="2" fill="#c9d6e2" stroke="#8ea2b8" stroke-width="1.5"/>
          <path d="M14,12 L6,38 a6,6 0 0 0 6,8 h16 a6,6 0 0 0 6,-8 L26,12 Z" fill="rgba(230,240,250,.4)" stroke="#8ea2b8" stroke-width="2"/>
          <path d="M11,28 L8,38 a5,5 0 0 0 5,6 h14 a5,5 0 0 0 5,-6 L29,28 Q20,33 11,28 Z" fill="${ft.color}"/>
        </svg>`;
        fxEl.appendChild(flask);
        const fromX = tx + 100 + Math.random() * 100;
        const start = performance.now();
        const dur = 320;
        (function step(now) {
          const t = Math.min(1, (now - start) / dur);
          const x = fromX + (tx - fromX) * t;
          const y = -50 + (ty + 50) * t;
          flask.style.transform = `translate(${x - 20}px, ${y - 24}px) rotate(${140 + t * 220}deg)`;
          if (t < 1) requestAnimationFrame(step);
          else {
            flask.remove();
            particles.burst(tx, ty, ft.rgb, 32, 6.2);
            particles.bubbles(tx, ty, ft.rgb, 8);
            shockRing(tx, ty, ft.color);
            floatText(tx, ty - 46, ft.sym, 'drg-float--element', ft.color);
            sound.hit();
            shake(true);
            flashClass(bossEl, 'is-hit', 300);
            if (++landed === FLAME_TESTS.length) {
              state.bossHp = Math.max(0, state.bossHp - total);
              renderBossHp(true);
              floatText(chest.x, chest.y - 100, `-${total}`, 'drg-float--ultimate');
              stageEl.classList.remove('is-ultimate');
              state.acting = false;
              checkEnrage();
              checkEnd();
            }
          }
        })(performance.now());
      }, i * 170);
    });
  }

  function checkEnrage() {
    if (state.enraged || state.finished) return;
    if (state.bossHp > 0 && state.bossHp / state.bossMax <= 0.4) {
      state.enraged = true;
      bossEl.classList.add('is-enraged');
      stageEl.classList.add('is-enraged');
      particles.setEmberBoost(3);
      sound.roar();
      shake(true);
      screenFlash('rgba(255,40,20,.3)');
      announce('🔥 HẮC LONG NỔI GIẬN! 🔥', 'drg-announce--danger', 2000);
    }
  }

  function checkEnd() {
    if (state.finished) return;
    if (state.bossHp <= 0) return win();
    if (state.hearts <= 0) return lose();
  }

  /** Khi auto demo: không hiện overlay kết quả, tự chơi lại vòng mới. */
  function finishRound(won) {
    const wasAuto = !!state.autoTimer;
    stopAuto();
    if (wasAuto) {
      announce(won ? '🏆 HẠ GỤC HẮC LONG!' : '💀 Cả đội gục ngã…', won ? 'drg-announce--ultimate' : 'drg-announce--danger', 2000);
      setTimeout(() => {
        resetGame();
        startAuto();
      }, 2400);
      return;
    }
    setTimeout(() => showOverlay(won), won ? 900 : 700);
  }

  function win() {
    state.finished = true;
    sound.win();
    bossEl.classList.add('is-dead');
    const chest = bossChest();
    [0, 180, 360, 560].forEach((d, i) => {
      setTimeout(() => {
        particles.burst(chest.x + (Math.random() - 0.5) * 120, chest.y + (Math.random() - 0.5) * 90, '255,190,90', 34, 6.5);
        shake(i === 3);
      }, d);
    });
    state.heroes.forEach((h) => h.el.classList.add('is-winner'));
    finishRound(true);
  }

  function lose() {
    state.finished = true;
    sound.lose();
    state.heroes.forEach((h) => h.el.classList.add('is-ko'));
    bossEl.classList.add('is-gloating');
    finishRound(false);
  }

  function showOverlay(won) {
    const overlay = document.getElementById('drgOverlay');
    if (!overlay) return;
    overlay.hidden = false;
    overlay.classList.toggle('is-win', won);
    overlay.querySelector('.drg-overlay__icon').textContent = won ? '🏆' : '💀';
    overlay.querySelector('.drg-overlay__title').textContent = won ? 'HẠ GỤC HẮC LONG!' : 'CẢ ĐỘI GỤC NGÃ…';
    overlay.querySelector('.drg-overlay__sub').textContent = won
      ? 'Cả lớp nhận rương báu vật + huy hiệu Thợ Săn Rồng 🐲'
      : 'Hắc Long quá mạnh. Ôn bài và thử lại nhé!';
    const rain = overlay.querySelector('.drg-overlay__rain');
    rain.innerHTML = '';
    const icons = won ? ['🪙', '💎', '⭐', '🏅'] : ['💨'];
    for (let i = 0; i < (won ? 34 : 10); i++) {
      const s = document.createElement('span');
      s.textContent = icons[i % icons.length];
      s.style.left = `${Math.random() * 100}%`;
      s.style.animationDelay = `${Math.random() * 1.6}s`;
      s.style.animationDuration = `${1.8 + Math.random() * 1.8}s`;
      rain.appendChild(s);
    }
  }

  /* ============================== Reset & auto demo ============================== */

  function resetGame() {
    const c = cfg();
    state.bossHp = c.bossHp;
    state.bossMax = c.bossHp;
    state.hearts = c.hearts;
    state.combo = 0;
    state.finished = false;
    state.enraged = false;
    state.acting = false;
    bossEl.classList.remove('is-enraged', 'is-dead', 'is-gloating', 'is-attacking', 'is-hit');
    caulEl.classList.remove('is-brewing', 'is-erupting');
    stageEl.classList.remove('is-enraged', 'is-ultimate');
    particles.setEmberBoost(1);
    const overlay = document.getElementById('drgOverlay');
    if (overlay) overlay.hidden = true;
    fxEl.innerHTML = '';
    renderHeroes();
    renderBossHp(false);
    renderHearts();
    renderCombo();
  }

  function fullReset() {
    stopAuto();
    resetGame();
  }

  function autoTick() {
    if (state.finished) return;
    if (state.acting) return;
    Math.random() < 0.74 ? answerCorrect() : answerWrong();
  }

  function autoInterval() {
    const speed = Number(document.getElementById('drg_speed')?.value) || 1;
    return 1500 / speed;
  }

  function startAuto() {
    stopAuto();
    state.autoTimer = setInterval(autoTick, autoInterval());
    const btn = document.querySelector('[data-drg="auto"]');
    if (btn) { btn.classList.add('is-on'); btn.textContent = '⏸ Dừng demo'; }
  }

  function stopAuto() {
    if (state.autoTimer) clearInterval(state.autoTimer);
    state.autoTimer = null;
    const btn = document.querySelector('[data-drg="auto"]');
    if (btn) { btn.classList.remove('is-on'); btn.textContent = '▶ Tự động demo'; }
  }

  /* ============================== Trang trí hóa học cho sân khấu ============================== */

  function buildDecor() {
    // vạc điều chế
    caulEl = document.createElement('div');
    caulEl.className = 'drg-cauldron';
    caulEl.innerHTML = cauldronSVG();
    stageEl.insertBefore(caulEl, fxEl);

    // kệ bình thí nghiệm
    const shelf = document.createElement('div');
    shelf.className = 'drg-shelf';
    shelf.innerHTML = shelfSVG();
    stageEl.insertBefore(shelf, fxEl);

    // phân tử trôi lơ lửng
    for (let i = 0; i < 3; i++) {
      const m = document.createElement('div');
      m.className = `drg-molecule drg-molecule--${i + 1}`;
      m.innerHTML = moleculeSVG(i);
      stageEl.insertBefore(m, fxEl);
    }
  }

  /* ============================== Init ============================== */

  function init(root) {
    stageEl = root.querySelector('#drgStage');
    fxEl = root.querySelector('#drgFx');
    bossEl = root.querySelector('#drgBoss');
    const canvas = root.querySelector('#drgParticles');
    if (!stageEl || !fxEl || !bossEl || !canvas) return;

    bossEl.innerHTML = dragonSVG();
    buildDecor();
    particles = createParticles(canvas);
    resetGame();

    root.querySelectorAll('[data-drg]').forEach((btn) => {
      const act = btn.dataset.drg;
      btn.addEventListener('click', () => {
        if (act === 'correct') answerCorrect();
        else if (act === 'wrong') answerWrong();
        else if (act === 'auto') (state.autoTimer ? stopAuto() : startAuto());
        else if (act === 'reset' || act === 'replay') fullReset();
        else if (act === 'sound') btn.textContent = sound.toggle() ? '🔇 Âm thanh: tắt' : '🔊 Âm thanh: bật';
      });
    });

    document.getElementById('drg_speed')?.addEventListener('change', () => {
      if (state.autoTimer) startAuto();
    });
    ['drg_boss_hp', 'drg_hearts', 'drg_combo_need'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', fullReset);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dragonHuntDemo');
    if (root) init(root);
  });

  return { init };
})();
