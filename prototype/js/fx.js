/* HTDFx — particle/hiệu ứng canvas cho cảm giác game
 *
 * API:
 *   HTDFx.starBurst(x, y, opts?)        — nổ sao tại toạ độ viewport
 *   HTDFx.burstAtElement(el, opts?)     — nổ sao tại tâm 1 element
 *   HTDFx.sparkleRain(opts?)            — mưa lấp lánh từ đỉnh màn (podium, tổng kết)
 *   HTDFx.floatText(x, y, text, opts?)  — chữ bay lên (+120 điểm…)
 *   HTDFx.shake()                       — rung nhẹ toàn màn (trả lời sai)
 *   HTDFx.icon(name, cls?)              — chuỗi <svg><use> từ sprite (dùng cho innerHTML)
 *
 * Một rAF loop duy nhất, ngủ khi hết particle; cap 80 particle;
 * tắt hẳn khi prefers-reduced-motion.
 */
(function () {
  'use strict';

  var MAX_PARTICLES = 80;
  var COLORS = ['#FFC93C', '#FF5DA2', '#38C6FF', '#8B5CF6', '#58CC02', '#FFD700', '#FF8A5C'];
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var canvas = null;
  var g = null;
  var dpr = 1;
  var parts = [];
  var rafId = null;
  var lastTs = 0;

  function ensureCanvas() {
    if (canvas) return true;
    canvas = document.getElementById('fxCanvas');
    if (!canvas) return false;
    g = canvas.getContext('2d');
    resize();
    window.addEventListener('resize', resize);
    return true;
  }

  function resize() {
    if (!canvas) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.floor(window.innerWidth * dpr);
    canvas.height = Math.floor(window.innerHeight * dpr);
  }

  function wake() {
    if (rafId == null && parts.length) {
      lastTs = 0;
      rafId = requestAnimationFrame(loop);
    }
  }

  function loop(ts) {
    rafId = null;
    if (!lastTs) lastTs = ts;
    var dt = Math.min((ts - lastTs) / 1000, 0.05);
    lastTs = ts;

    g.clearRect(0, 0, canvas.width, canvas.height);
    var alive = [];
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i];
      p.life -= dt;
      if (p.life <= 0) continue;
      p.vy += (p.gravity || 0) * dt;
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      p.rot += (p.spin || 0) * dt;
      drawPart(p);
      alive.push(p);
    }
    parts = alive;
    if (parts.length) {
      rafId = requestAnimationFrame(loop);
    } else {
      g.clearRect(0, 0, canvas.width, canvas.height);
    }
  }

  function drawPart(p) {
    var a = Math.max(0, Math.min(1, p.life / p.maxLife));
    g.save();
    g.translate(p.x * dpr, p.y * dpr);
    g.rotate(p.rot);
    g.globalAlpha = p.kind === 'text' ? Math.min(1, a * 1.6) : a;
    if (p.kind === 'star') {
      drawStar(p.size * dpr * (0.5 + a * 0.5), p.color);
    } else if (p.kind === 'sparkle') {
      g.fillStyle = p.color;
      var s = p.size * dpr * (0.6 + 0.4 * Math.sin(p.life * 12));
      g.beginPath();
      g.arc(0, 0, Math.max(0.5, s), 0, Math.PI * 2);
      g.fill();
    } else if (p.kind === 'text') {
      g.font = '800 ' + Math.round(p.size * dpr) + 'px "Baloo 2", "Be Vietnam Pro", sans-serif';
      g.textAlign = 'center';
      g.lineWidth = 4 * dpr;
      g.strokeStyle = 'rgba(38,35,92,0.85)';
      g.strokeText(p.text, 0, 0);
      g.fillStyle = p.color;
      g.fillText(p.text, 0, 0);
    }
    g.restore();
  }

  function drawStar(r, color) {
    g.fillStyle = color;
    g.beginPath();
    for (var i = 0; i < 10; i++) {
      var rad = i % 2 === 0 ? r : r * 0.45;
      var ang = (Math.PI / 5) * i - Math.PI / 2;
      var px = Math.cos(ang) * rad;
      var py = Math.sin(ang) * rad;
      if (i === 0) g.moveTo(px, py); else g.lineTo(px, py);
    }
    g.closePath();
    g.fill();
  }

  function push(p) {
    if (parts.length >= MAX_PARTICLES) parts.splice(0, parts.length - MAX_PARTICLES + 1);
    parts.push(p);
  }

  /* ── public FX ───────────────────────────────────────────────── */

  function starBurst(x, y, opts) {
    if (reduced || !ensureCanvas()) return;
    opts = opts || {};
    var count = Math.min(opts.count || 18, 40);
    for (var i = 0; i < count; i++) {
      var ang = Math.random() * Math.PI * 2;
      var speed = 120 + Math.random() * 260;
      push({
        kind: 'star',
        x: x, y: y,
        vx: Math.cos(ang) * speed,
        vy: Math.sin(ang) * speed - 90,
        gravity: 620,
        rot: Math.random() * Math.PI,
        spin: (Math.random() - 0.5) * 10,
        size: 5 + Math.random() * 8,
        color: opts.color || COLORS[i % COLORS.length],
        life: 0.7 + Math.random() * 0.5,
        maxLife: 1.1,
      });
    }
    wake();
  }

  function burstAtElement(el, opts) {
    if (!el || reduced) return;
    var r = el.getBoundingClientRect();
    starBurst(r.left + r.width / 2, r.top + r.height / 2, opts);
  }

  function sparkleRain(opts) {
    if (reduced || !ensureCanvas()) return;
    opts = opts || {};
    var count = Math.min(opts.count || 36, 60);
    var w = window.innerWidth;
    for (var i = 0; i < count; i++) {
      push({
        kind: Math.random() < 0.4 ? 'star' : 'sparkle',
        x: Math.random() * w,
        y: -20 - Math.random() * 160,
        vx: (Math.random() - 0.5) * 40,
        vy: 90 + Math.random() * 150,
        gravity: 60,
        rot: Math.random() * Math.PI,
        spin: (Math.random() - 0.5) * 6,
        size: 3 + Math.random() * 6,
        color: COLORS[i % COLORS.length],
        life: 1.6 + Math.random() * 1.4,
        maxLife: 3,
      });
    }
    wake();
  }

  function floatText(x, y, text, opts) {
    if (reduced || !ensureCanvas()) return;
    opts = opts || {};
    push({
      kind: 'text',
      text: text,
      x: x, y: y,
      vx: 0,
      vy: -70,
      gravity: -30,
      rot: 0,
      size: opts.size || 26,
      color: opts.color || '#FFC93C',
      life: 1.1,
      maxLife: 1.1,
    });
    wake();
  }

  function shake() {
    if (reduced) return;
    var app = document.getElementById('app');
    if (!app) return;
    app.classList.remove('fx-shake');
    // force reflow để restart animation
    void app.offsetWidth;
    app.classList.add('fx-shake');
    setTimeout(function () { app.classList.remove('fx-shake'); }, 450);
  }

  function icon(name, cls) {
    return '<svg class="icon' + (cls ? ' ' + cls : '') + '" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
  }

  window.HTDFx = {
    starBurst: starBurst,
    burstAtElement: burstAtElement,
    sparkleRain: sparkleRain,
    floatText: floatText,
    shake: shake,
    icon: icon,
  };
})();
