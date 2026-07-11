/* HTDSound — game audio engine (Web Audio API, không dùng file audio)
 *
 * API:
 *   HTDSound.init()          — tạo AudioContext (idempotent, tự gọi ở pointerdown đầu tiên)
 *   HTDSound.play(name)      — SFX: tap|pop|correct|wrong|tick|urgent|whoosh|fanfare|join|countdown-go|flip
 *   HTDSound.startMusic() / stopMusic() — nhạc nền chiptune phòng chờ
 *   HTDSound.toggleMuted() / isMuted()
 *
 * Mute lưu localStorage 'htd_sound_muted'; master GainNode nên unmute tức thì.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'htd_sound_muted';
  var ctx = null;
  var master = null;
  var musicGain = null;
  var muted = false;
  var musicTimer = null;
  var musicStep = 0;
  var musicNextTime = 0;

  try { muted = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) { /* private mode */ }

  function ensureCtx() {
    if (!window.AudioContext && !window.webkitAudioContext) return null;
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      master = ctx.createGain();
      master.gain.value = muted ? 0 : 1;
      master.connect(ctx.destination);
      musicGain = ctx.createGain();
      musicGain.gain.value = 0.12;
      musicGain.connect(master);
    }
    if (ctx.state === 'suspended') ctx.resume().catch(function () {});
    return ctx;
  }

  /* ── primitive: 1 nốt oscillator với envelope ───────────────── */
  function tone(opts) {
    if (!ctx) return;
    var t0 = ctx.currentTime + (opts.at || 0);
    var dur = opts.dur || 0.1;
    var osc = ctx.createOscillator();
    var g = ctx.createGain();
    osc.type = opts.type || 'sine';
    osc.frequency.setValueAtTime(opts.freq, t0);
    if (opts.freqTo) osc.frequency.exponentialRampToValueAtTime(Math.max(1, opts.freqTo), t0 + dur);
    var peak = opts.gain != null ? opts.gain : 0.15;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(peak, t0 + Math.min(0.012, dur * 0.2));
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    var dest = opts.dest || master;
    if (opts.filterFreq) {
      var f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = opts.filterFreq;
      osc.connect(f); f.connect(g);
    } else {
      osc.connect(g);
    }
    g.connect(dest);
    osc.start(t0);
    osc.stop(t0 + dur + 0.05);
  }

  /* ── primitive: noise burst (whoosh, hi-hat) ─────────────────── */
  var noiseBuf = null;
  function getNoiseBuf() {
    if (!noiseBuf) {
      noiseBuf = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
      var d = noiseBuf.getChannelData(0);
      for (var i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
    }
    return noiseBuf;
  }
  function noise(opts) {
    if (!ctx) return;
    var t0 = ctx.currentTime + (opts.at || 0);
    var dur = opts.dur || 0.2;
    var src = ctx.createBufferSource();
    src.buffer = getNoiseBuf();
    var bp = ctx.createBiquadFilter();
    bp.type = opts.filterType || 'bandpass';
    bp.frequency.setValueAtTime(opts.freq || 800, t0);
    if (opts.freqTo) bp.frequency.exponentialRampToValueAtTime(opts.freqTo, t0 + dur);
    bp.Q.value = opts.q || 1;
    var g = ctx.createGain();
    var peak = opts.gain != null ? opts.gain : 0.1;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(peak, t0 + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    src.connect(bp); bp.connect(g); g.connect(opts.dest || master);
    src.start(t0);
    src.stop(t0 + dur + 0.05);
  }

  /* ── SFX recipes ─────────────────────────────────────────────── */
  var NOTES = { C4: 261.63, E4: 329.63, F4: 349.23, G4: 392, A4: 440, B4: 493.88,
    C5: 523.25, D5: 587.33, E5: 659.25, F5: 698.46, G5: 783.99, A5: 880, C6: 1046.5 };

  var SFX = {
    tap: function () {
      tone({ type: 'sine', freq: 880, freqTo: 660, dur: 0.06, gain: 0.12 });
    },
    pop: function () {
      tone({ type: 'sine', freq: 520, freqTo: 180, dur: 0.09, gain: 0.18 });
    },
    tick: function () {
      tone({ type: 'square', freq: 1000, dur: 0.03, gain: 0.05, filterFreq: 2200 });
    },
    urgent: function () {
      tone({ type: 'square', freq: 1200, dur: 0.05, gain: 0.07, filterFreq: 2600 });
      tone({ type: 'square', freq: 900, dur: 0.05, gain: 0.07, at: 0.08, filterFreq: 2600 });
    },
    correct: function () {
      [NOTES.C5, NOTES.E5, NOTES.G5, NOTES.C6].forEach(function (f, i) {
        tone({ type: 'square', freq: f, dur: 0.12, gain: 0.09, at: i * 0.07, filterFreq: 3800 });
        tone({ type: 'triangle', freq: f * 2, dur: 0.1, gain: 0.04, at: i * 0.07 });
      });
    },
    wrong: function () {
      tone({ type: 'sawtooth', freq: 220, freqTo: 150, dur: 0.32, gain: 0.13, filterFreq: 420 });
      tone({ type: 'sawtooth', freq: 233, freqTo: 158, dur: 0.32, gain: 0.09, filterFreq: 420 });
    },
    whoosh: function () {
      noise({ freq: 300, freqTo: 3000, dur: 0.22, gain: 0.07, q: 1.4 });
    },
    flip: function () {
      noise({ freq: 500, freqTo: 2400, dur: 0.16, gain: 0.06, q: 2 });
      tone({ type: 'sine', freq: 500, freqTo: 900, dur: 0.14, gain: 0.05 });
    },
    join: function () {
      tone({ type: 'triangle', freq: NOTES.G4, dur: 0.1, gain: 0.14 });
      tone({ type: 'triangle', freq: NOTES.B4, dur: 0.14, gain: 0.14, at: 0.09 });
    },
    'countdown-go': function () {
      tone({ type: 'square', freq: 660, dur: 0.09, gain: 0.09, filterFreq: 2400 });
      tone({ type: 'square', freq: 880, dur: 0.18, gain: 0.11, at: 0.12, filterFreq: 3000 });
    },
    fanfare: function () {
      // 4 hợp âm stab + arpeggio kết — ~1.6s
      var chords = [
        [NOTES.C4, NOTES.E4, NOTES.G4],
        [NOTES.F4, NOTES.A4, NOTES.C5],
        [NOTES.G4, NOTES.B4, NOTES.D5],
        [NOTES.C5, NOTES.E5, NOTES.G5],
      ];
      chords.forEach(function (chord, i) {
        chord.forEach(function (f) {
          tone({ type: 'square', freq: f, dur: 0.22, gain: 0.055, at: i * 0.24, filterFreq: 3200 });
        });
      });
      [NOTES.C5, NOTES.E5, NOTES.G5, NOTES.C6, NOTES.G5, NOTES.C6].forEach(function (f, i) {
        tone({ type: 'triangle', freq: f, dur: 0.16, gain: 0.1, at: 1.0 + i * 0.09 });
      });
      noise({ freq: 6000, dur: 0.3, gain: 0.04, at: 1.0, filterType: 'highpass' });
    },
  };

  /* ── Nhạc nền phòng chờ: chiptune loop 8 bar, 8th-note steps ── */
  var TEMPO = 112;
  var STEP = 60 / TEMPO / 2;          // 8th note
  var BASS = [ // 8 bar × 2 nốt/bar (nửa bar một nốt) — C Am F G tiến trình ×2
    130.81, 130.81, 110.00, 110.00, 87.31, 87.31, 98.00, 98.00,
    130.81, 130.81, 110.00, 110.00, 87.31, 87.31, 98.00, 123.47,
  ];
  var LEAD = [ // 64 steps (8 bar × 8) — 0 = nghỉ; giai điệu pentatonic vui
    NOTES.E5, 0, NOTES.G5, 0, NOTES.C5, 0, NOTES.D5, NOTES.E5,
    0, NOTES.C5, 0, 0, NOTES.A4, 0, NOTES.C5, 0,
    NOTES.A4, 0, NOTES.C5, 0, NOTES.E5, 0, NOTES.D5, NOTES.C5,
    0, NOTES.D5, 0, 0, NOTES.G4, 0, 0, 0,
    NOTES.E5, 0, NOTES.G5, 0, NOTES.A5, 0, NOTES.G5, NOTES.E5,
    0, NOTES.D5, 0, 0, NOTES.C5, 0, NOTES.D5, 0,
    NOTES.E5, 0, NOTES.D5, NOTES.C5, NOTES.A4, 0, NOTES.G4, 0,
    NOTES.C5, 0, 0, NOTES.D5, NOTES.E5, 0, 0, 0,
  ];

  function scheduleMusic() {
    if (!ctx) return;
    var horizon = ctx.currentTime + 0.12;
    while (musicNextTime < horizon) {
      var s = musicStep % 64;
      var at = musicNextTime - ctx.currentTime;
      if (at < 0) at = 0;
      if (s % 4 === 0) { // bass mỗi nửa bar
        tone({ type: 'triangle', freq: BASS[Math.floor(s / 4)], dur: STEP * 3.2, gain: 0.5, at: at, dest: musicGain });
      }
      if (LEAD[s]) {
        tone({ type: 'square', freq: LEAD[s], dur: STEP * 0.9, gain: 0.22, at: at, filterFreq: 2800, dest: musicGain });
      }
      if (s % 2 === 0) { // hi-hat nhẹ
        noise({ freq: 7000, dur: 0.03, gain: s % 8 === 4 ? 0.09 : 0.05, at: at, filterType: 'highpass', dest: musicGain });
      }
      musicNextTime += STEP;
      musicStep++;
    }
  }

  function startMusic() {
    if (musicTimer) return;
    if (!ensureCtx()) return;
    musicStep = 0;
    musicNextTime = ctx.currentTime + 0.05;
    scheduleMusic();
    musicTimer = setInterval(scheduleMusic, 25);
  }

  function stopMusic() {
    if (musicTimer) { clearInterval(musicTimer); musicTimer = null; }
  }

  /* ── mute toggle ─────────────────────────────────────────────── */
  function applyMuteUI() {
    var btn = document.getElementById('soundToggle');
    if (!btn) return;
    btn.classList.toggle('muted', muted);
    var use = btn.querySelector('use');
    if (use) {
      use.setAttribute('href', muted ? '#i-speaker-off' : '#i-speaker');
    }
  }

  function toggleMuted() {
    muted = !muted;
    try { localStorage.setItem(STORAGE_KEY, muted ? '1' : '0'); } catch (e) {}
    if (ensureCtx()) {
      master.gain.setTargetAtTime(muted ? 0 : 1, ctx.currentTime, 0.01);
    }
    applyMuteUI();
    if (!muted) SFX.pop();
  }

  function play(name) {
    if (muted) return;
    if (!ensureCtx()) return;
    var fn = SFX[name];
    if (fn) fn();
  }

  function init() { ensureCtx(); }

  window.HTDSound = {
    init: init,
    play: play,
    startMusic: startMusic,
    stopMusic: stopMusic,
    toggleMuted: toggleMuted,
    isMuted: function () { return muted; },
  };

  // Autoplay policy: tạo/resume context ở cử chỉ đầu tiên
  document.addEventListener('pointerdown', function first() {
    ensureCtx();
  }, { once: true, capture: true });

  document.addEventListener('DOMContentLoaded', function () {
    applyMuteUI();
    var btn = document.getElementById('soundToggle');
    if (btn) btn.addEventListener('click', toggleMuted);
  });
})();
