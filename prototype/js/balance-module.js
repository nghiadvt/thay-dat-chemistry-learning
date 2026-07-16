/* BalanceModule — «⚖️ Cân bằng phương trình»
 *
 * Trò chơi tự luyện không cần mạng, dữ liệu js/balance-data.js:
 * - Bản đồ màn: 3 cấp độ, mở khóa lần lượt trong từng cấp (màn đầu mỗi cấp luôn mở)
 * - Màn chơi: chạm ô hệ số → chỉnh bằng − / + (giữ để tăng nhanh);
 *   cân thăng bằng nghiêng theo tổng nguyên tử 2 vế + bảng đếm từng nguyên tố realtime
 * - Tự nhận diện khi cân bằng đúng; nếu cân nhưng chưa tối giản → nhắc rút gọn
 * - Gợi ý 💡 điền 1 hệ số đúng và khóa ô đó; sao: 0 gợi ý = 3⭐, 1 = 2⭐, ≥2 = 1⭐
 * - Sao từng màn lưu localStorage `htd_balance_stars`
 */
window.BalanceModule = (function () {
  'use strict';

  var STARS_KEY = 'htd_balance_stars';
  var DEFAULT_MAX = 12;
  var TIERS = window.HTD_BALANCE_LEVELS || [];

  var st = null; // trạng thái màn đang chơi

  /* ── Helpers chung ──────────────────────────────────────────── */
  function sfx(name) {
    if (window.HTDSound) HTDSound.play(name);
  }

  function toast(msg, emoji) {
    if (typeof showCartoonToast === 'function') showCartoonToast(msg, emoji || '⚖️');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function gcd2(a, b) { return b ? gcd2(b, a % b) : a; }

  function gcdAll(arr) {
    return arr.reduce(function (g, n) { return gcd2(g, n); }, 0);
  }

  /* Phân tích công thức → {Nguyên tố: số nguyên tử}, hỗ trợ ngoặc: Ca3(PO4)2 */
  function parseFormula(formula) {
    var i = 0;
    function readNum() {
      var s = '';
      while (i < formula.length && formula[i] >= '0' && formula[i] <= '9') { s += formula[i]; i++; }
      return s ? parseInt(s, 10) : 1;
    }
    function merge(into, from, mult) {
      Object.keys(from).forEach(function (el) {
        into[el] = (into[el] || 0) + from[el] * mult;
      });
    }
    function group() {
      var counts = {};
      while (i < formula.length) {
        var c = formula[i];
        if (c === '(') {
          i++;
          var inner = group(); // group() dừng khi gặp ')'
          merge(counts, inner, readNum());
        } else if (c === ')') {
          i++;
          return counts;
        } else if (c >= 'A' && c <= 'Z') {
          var sym = c; i++;
          while (i < formula.length && formula[i] >= 'a' && formula[i] <= 'z') { sym += formula[i]; i++; }
          counts[sym] = (counts[sym] || 0) + readNum();
        } else {
          i++; // ký tự lạ — bỏ qua
        }
      }
      return counts;
    }
    return group();
  }

  /* CTHH hiển thị: chỉ số → <sub> (dataset không có điện tích/hydrat) */
  function fmtFormula(f) {
    return esc(f).replace(/(\d+)/g, '<sub>$1</sub>');
  }

  function starsMap() {
    try { return JSON.parse(localStorage.getItem(STARS_KEY) || '{}') || {}; } catch (e) { return {}; }
  }

  function saveStars(levelId, stars) {
    var map = starsMap();
    if ((map[levelId] || 0) >= stars) return false;
    map[levelId] = stars;
    try { localStorage.setItem(STARS_KEY, JSON.stringify(map)); } catch (e) {}
    return true;
  }

  function tierOf(levelId) {
    for (var t = 0; t < TIERS.length; t++) {
      for (var l = 0; l < TIERS[t].levels.length; l++) {
        if (TIERS[t].levels[l].id === levelId) return { tier: TIERS[t], tIdx: t, lIdx: l };
      }
    }
    return null;
  }

  /* Màn bị khóa khi màn liền trước cùng cấp chưa có sao (màn đầu cấp luôn mở) */
  function isLocked(tIdx, lIdx) {
    if (lIdx === 0) return false;
    var prev = TIERS[tIdx].levels[lIdx - 1];
    return !(starsMap()[prev.id] > 0);
  }

  function nextLevelOf(levelId) {
    var pos = tierOf(levelId);
    if (!pos) return null;
    if (pos.lIdx + 1 < pos.tier.levels.length) return pos.tier.levels[pos.lIdx + 1];
    if (pos.tIdx + 1 < TIERS.length) return TIERS[pos.tIdx + 1].levels[0];
    return null;
  }

  /* ── Màn 1: bản đồ màn chơi ─────────────────────────────────── */
  function open() {
    showScreen('balance-map');
    renderMap();
  }

  function renderMap() {
    var body = document.getElementById('blMapBody');
    if (!body) return;
    var map = starsMap();
    var total = 0;
    var maxTotal = 0;

    var tiersHtml = TIERS.map(function (tier, tIdx) {
      var done = 0;
      var nodes = tier.levels.map(function (lv, lIdx) {
        var stars = map[lv.id] || 0;
        total += stars;
        maxTotal += 3;
        if (stars > 0) done++;
        var locked = isLocked(tIdx, lIdx);
        var starHtml = [1, 2, 3].map(function (i) {
          return '<svg class="icon bl-node-star' + (i <= stars ? ' lit' : '') + '" aria-hidden="true"><use href="#i-star"/></svg>';
        }).join('');
        return '<button type="button" class="bl-node' + (locked ? ' locked' : stars > 0 ? ' done' : '') + '" ' +
          'data-level="' + esc(lv.id) + '" aria-label="Màn ' + (lIdx + 1) + ': ' + esc(lv.title) + '">' +
          '<span class="bl-node-emoji">' + (locked ? '🔒' : lv.emoji) + '</span>' +
          '<span class="bl-node-num">' + (lIdx + 1) + '</span>' +
          '<span class="bl-node-stars">' + starHtml + '</span>' +
          '</button>';
      }).join('');

      return '<section class="bl-tier">' +
        '<div class="bl-tier-head">' +
          '<span class="bl-tier-emoji">' + tier.emoji + '</span>' +
          '<div class="bl-tier-txt"><h3>' + esc(tier.name) + '</h3><p>' + esc(tier.desc) + '</p></div>' +
          '<span class="bl-tier-progress">' + done + '/' + tier.levels.length + '</span>' +
        '</div>' +
        '<div class="bl-node-grid">' + nodes + '</div>' +
        '</section>';
    }).join('');

    body.innerHTML =
      '<div class="bl-map-hero">' +
        '<span class="bl-map-hero-emoji">⚖️</span>' +
        '<p class="bl-map-hero-sub">Chỉnh hệ số cho hai bên cân bằng — như một chiếc cân thật!</p>' +
      '</div>' +
      tiersHtml;

    var totalEl = document.getElementById('blTotalStars');
    if (totalEl) totalEl.textContent = '⭐ ' + total + '/' + maxTotal;

    body.querySelectorAll('.bl-node').forEach(function (node) {
      node.addEventListener('click', function () {
        if (node.classList.contains('locked')) {
          sfx('pop');
          toast('Hoàn thành màn trước để mở khóa nhé!', '🔒');
          return;
        }
        sfx('tap');
        var pos = tierOf(node.dataset.level);
        if (pos) startLevel(pos.tier.levels[pos.lIdx]);
      });
    });
  }

  /* ── Màn 2: chơi ────────────────────────────────────────────── */
  function playBody() {
    return document.getElementById('blPlayBody');
  }

  function startLevel(level) {
    var species = [];
    level.lhs.forEach(function (f) { species.push({ formula: f, side: 'L', counts: parseFormula(f) }); });
    level.rhs.forEach(function (f) { species.push({ formula: f, side: 'R', counts: parseFormula(f) }); });

    // thứ tự nguyên tố theo lần xuất hiện đầu tiên (trái → phải)
    var elements = [];
    species.forEach(function (sp) {
      Object.keys(sp.counts).forEach(function (el) {
        if (elements.indexOf(el) < 0) elements.push(el);
      });
    });

    st = {
      level: level,
      species: species,
      elements: elements,
      coefs: species.map(function () { return 1; }),
      locked: species.map(function () { return false; }),
      max: level.max || DEFAULT_MAX,
      sel: 0,
      hints: 0,
      solved: false,
      simplifyNagged: false,
    };

    showScreen('balance-play');
    renderPlay();
    update();
  }

  function renderPlay() {
    var body = playBody();
    if (!body || !st) return;
    var lv = st.level;
    var pos = tierOf(lv.id);

    var eqHtml = '';
    st.species.forEach(function (sp, i) {
      if (i > 0 && st.species[i - 1].side === sp.side) {
        eqHtml += '<span class="bl-plus">+</span>';
      } else if (sp.side === 'R' && (i === 0 || st.species[i - 1].side === 'L')) {
        eqHtml += '<span class="bl-arrow">' +
          (lv.cond ? '<small>' + esc(lv.cond) + '</small>' : '') +
          '<span aria-hidden="true">⟶</span></span>';
      }
      eqHtml += '<button type="button" class="bl-species" data-i="' + i + '">' +
        '<span class="bl-coef">1</span>' +
        '<span class="bl-formula">' + fmtFormula(sp.formula) + '</span>' +
        '</button>';
    });

    var atomsHtml = st.elements.map(function (el) {
      return '<div class="bl-atom" data-el="' + esc(el) + '">' +
        '<span class="bl-atom-n bl-atom-l">0</span>' +
        '<span class="bl-atom-el">' + esc(el) + '</span>' +
        '<span class="bl-atom-n bl-atom-r">0</span>' +
        '</div>';
    }).join('');

    var starMeter = [1, 2, 3].map(function () {
      return '<svg class="icon bl-meter-star lit" aria-hidden="true"><use href="#i-star"/></svg>';
    }).join('');

    body.innerHTML =
      '<div class="bl-play-top">' +
        '<button type="button" class="bl-exit" id="blExitBtn" aria-label="Về bản đồ màn">✕</button>' +
        '<div class="bl-level-name">' +
          '<strong>' + lv.emoji + ' ' + esc(lv.title) + '</strong>' +
          '<span>' + (pos ? esc(pos.tier.name) + ' · Màn ' + (pos.lIdx + 1) : '') + '</span>' +
        '</div>' +
        '<div class="bl-star-meter" id="blStarMeter" title="Sao sẽ nhận được">' + starMeter + '</div>' +
      '</div>' +
      '<div class="bl-scroll">' +
        '<div class="bl-eq-card">' +
          '<div class="bl-eq-row' + (st.species.length > 4 ? ' compact' : '') + '" id="blEqRow">' + eqHtml + '</div>' +
        '</div>' +
        '<div class="bl-scale" id="blScale" aria-hidden="true">' +
          '<div class="bl-scale-beam" id="blBeam">' +
            '<div class="bl-pan pan-l"><span class="bl-pan-inner" id="blPanL">0</span></div>' +
            '<div class="bl-pan pan-r"><span class="bl-pan-inner" id="blPanR">0</span></div>' +
          '</div>' +
          '<div class="bl-scale-post"></div>' +
        '</div>' +
        '<div class="bl-atoms" id="blAtoms">' + atomsHtml + '</div>' +
        '<div class="bl-controls">' +
          '<button type="button" class="bl-step" id="blMinus" aria-label="Giảm hệ số">−</button>' +
          '<div class="bl-sel" id="blSelDisplay"><span class="bl-sel-coef">1</span><span class="bl-sel-formula"></span></div>' +
          '<button type="button" class="bl-step" id="blPlus" aria-label="Tăng hệ số">+</button>' +
        '</div>' +
        '<div class="bl-toolbar">' +
          '<button type="button" class="bl-tool" id="blResetBtn">↺ Đặt lại</button>' +
          '<button type="button" class="bl-tool bl-tool-hint" id="blHintBtn">💡 Gợi ý</button>' +
        '</div>' +
        '<p class="bl-help">Chạm vào một chất để chọn, rồi dùng − / + chỉnh hệ số (giữ để tua nhanh)</p>' +
      '</div>';

    document.getElementById('blExitBtn').addEventListener('click', function () {
      sfx('tap');
      st = null;
      open();
    });
    body.querySelectorAll('.bl-species').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!st || st.solved) return;
        var i = Number(btn.dataset.i);
        if (st.locked[i]) { sfx('pop'); return; }
        sfx('tap');
        st.sel = i;
        update();
      });
    });
    bindStepper(document.getElementById('blMinus'), -1);
    bindStepper(document.getElementById('blPlus'), +1);
    document.getElementById('blResetBtn').addEventListener('click', resetCoefs);
    document.getElementById('blHintBtn').addEventListener('click', useHint);
  }

  /* Giữ nút − / + để tua nhanh */
  function bindStepper(btn, delta) {
    var holdTimer = null;
    var repeatTimer = null;
    function stop() {
      clearTimeout(holdTimer); holdTimer = null;
      clearInterval(repeatTimer); repeatTimer = null;
    }
    btn.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      changeCoef(delta);
      holdTimer = setTimeout(function () {
        repeatTimer = setInterval(function () { changeCoef(delta); }, 110);
      }, 420);
    });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (ev) {
      btn.addEventListener(ev, stop);
    });
  }

  function changeCoef(delta) {
    if (!st || st.solved) return;
    if (st.locked[st.sel]) return;
    var next = st.coefs[st.sel] + delta;
    if (next < 1 || next > st.max) {
      if (next > st.max) toast('Hệ số tối đa là ' + st.max + ' thôi nhé!', '🙈');
      return;
    }
    st.coefs[st.sel] = next;
    sfx('tick');
    update();
  }

  function resetCoefs() {
    if (!st || st.solved) return;
    sfx('whoosh');
    st.coefs = st.coefs.map(function (c, i) { return st.locked[i] ? c : 1; });
    st.simplifyNagged = false;
    update();
  }

  function useHint() {
    if (!st || st.solved) return;
    var ans = st.level.ans;
    // ưu tiên ô đang chọn nếu nó sai, không thì ô sai đầu tiên chưa khóa
    var idx = -1;
    if (!st.locked[st.sel] && st.coefs[st.sel] !== ans[st.sel]) idx = st.sel;
    if (idx < 0) {
      for (var i = 0; i < ans.length; i++) {
        if (!st.locked[i] && st.coefs[i] !== ans[i]) { idx = i; break; }
      }
    }
    if (idx < 0) { toast('Các hệ số đang đúng hết rồi đó!', '👀'); return; }

    st.hints += 1;
    st.coefs[idx] = ans[idx];
    st.locked[idx] = true;
    if (st.sel === idx) {
      for (var j = 0; j < ans.length; j++) {
        if (!st.locked[j]) { st.sel = j; break; }
      }
    }
    sfx('pop');
    var chip = playBody().querySelector('.bl-species[data-i="' + idx + '"]');
    if (chip && window.HTDFx) HTDFx.burstAtElement(chip, { count: 10 });
    update();
  }

  function earnedStars() {
    return st.hints === 0 ? 3 : st.hints === 1 ? 2 : 1;
  }

  /* Đếm nguyên tử từng vế theo hệ số hiện tại */
  function tally() {
    var perEl = {};
    var totalL = 0;
    var totalR = 0;
    st.elements.forEach(function (el) { perEl[el] = { L: 0, R: 0 }; });
    st.species.forEach(function (sp, i) {
      Object.keys(sp.counts).forEach(function (el) {
        var n = sp.counts[el] * st.coefs[i];
        perEl[el][sp.side] += n;
        if (sp.side === 'L') totalL += n; else totalR += n;
      });
    });
    return { perEl: perEl, totalL: totalL, totalR: totalR };
  }

  /* Cập nhật giao diện theo trạng thái — gọi sau mỗi thay đổi */
  function update() {
    var body = playBody();
    if (!body || !st) return;
    var t = tally();

    // hệ số + trạng thái chọn/khóa trên từng chất
    body.querySelectorAll('.bl-species').forEach(function (btn) {
      var i = Number(btn.dataset.i);
      var coefEl = btn.querySelector('.bl-coef');
      coefEl.textContent = st.coefs[i];
      coefEl.classList.toggle('one', st.coefs[i] === 1);
      btn.classList.toggle('selected', i === st.sel && !st.solved);
      btn.classList.toggle('hinted', st.locked[i]);
    });

    // ô điều khiển
    var selSp = st.species[st.sel];
    var selDisplay = document.getElementById('blSelDisplay');
    if (selDisplay && selSp) {
      selDisplay.querySelector('.bl-sel-coef').textContent = st.coefs[st.sel];
      selDisplay.querySelector('.bl-sel-formula').innerHTML = fmtFormula(selSp.formula);
    }

    // bảng nguyên tố
    var allBalanced = true;
    st.elements.forEach(function (el) {
      var row = body.querySelector('.bl-atom[data-el="' + el + '"]');
      if (!row) return;
      var c = t.perEl[el];
      if (c.L !== c.R) allBalanced = false;
      row.querySelector('.bl-atom-l').textContent = c.L;
      row.querySelector('.bl-atom-r').textContent = c.R;
      row.classList.toggle('ok', c.L === c.R);
    });

    // cân nghiêng theo chênh lệch tổng nguyên tử (vế nặng chìm xuống)
    var beam = document.getElementById('blBeam');
    var scale = document.getElementById('blScale');
    if (beam) {
      var tilt = Math.max(-12, Math.min(12, (t.totalR - t.totalL) * 1.5));
      beam.style.transform = 'rotate(' + tilt + 'deg)';
      beam.querySelectorAll('.bl-pan-inner').forEach(function (pan) {
        pan.style.transform = 'rotate(' + (-tilt) + 'deg)';
      });
      document.getElementById('blPanL').textContent = t.totalL;
      document.getElementById('blPanR').textContent = t.totalR;
      if (scale) scale.classList.toggle('balanced', allBalanced);
    }

    // thước sao (mờ dần khi dùng gợi ý)
    var meter = document.getElementById('blStarMeter');
    if (meter) {
      var earn = earnedStars();
      meter.querySelectorAll('.bl-meter-star').forEach(function (s, i) {
        s.classList.toggle('lit', i < earn);
      });
    }

    if (st.solved) return;

    if (allBalanced) {
      var g = gcdAll(st.coefs);
      if (g > 1) {
        if (!st.simplifyNagged) {
          st.simplifyNagged = true;
          sfx('pop');
          toast('Đã cân bằng! Nhưng hệ số còn rút gọn được — chia hết cho ' + g + ' kìa ✂️', '🤏');
        }
        return;
      }
      win();
    } else {
      st.simplifyNagged = false;
    }
  }

  /* ── Chiến thắng ────────────────────────────────────────────── */
  function win() {
    st.solved = true;
    var body = playBody();
    var stars = earnedStars();
    var isNew = saveStars(st.level.id, stars);

    sfx('correct');
    body.querySelectorAll('.bl-species').forEach(function (btn, i) {
      btn.classList.remove('selected');
      btn.classList.add('won');
      btn.style.animationDelay = (i * 90) + 'ms';
    });
    if (window.HTDFx) {
      var eq = document.getElementById('blEqRow');
      if (eq) HTDFx.burstAtElement(eq, { count: 22 });
    }

    setTimeout(function () {
      if (!st || !st.solved) return;
      sfx('fanfare');
      if (stars >= 2 && window.HTDFx) HTDFx.sparkleRain({ count: 40 });
      showWinOverlay(stars, isNew);
    }, 750);
  }

  function showWinOverlay(stars, isNew) {
    var body = playBody();
    if (!body || body.querySelector('.bl-win-overlay')) return;
    var next = nextLevelOf(st.level.id);

    var starHtml = [1, 2, 3].map(function (i) {
      return '<svg class="icon bl-win-star' + (i <= stars ? ' lit' : '') + '" style="animation-delay:' + (i * 250) + 'ms" aria-hidden="true"><use href="#i-star"/></svg>';
    }).join('');

    var eqText = st.species.map(function (sp, i) {
      var coef = st.coefs[i] > 1 ? st.coefs[i] : '';
      var sep = i === 0 ? '' : (st.species[i - 1].side === sp.side ? ' + ' : ' ⟶ ');
      return sep + coef + fmtFormula(sp.formula);
    }).join('');

    var title = stars >= 3 ? 'Hoàn hảo!' : stars === 2 ? 'Giỏi lắm!' : 'Đã cân bằng!';

    var overlay = document.createElement('div');
    overlay.className = 'bl-win-overlay';
    overlay.innerHTML =
      '<div class="bl-win-card">' +
        '<div class="bl-win-stars">' + starHtml + '</div>' +
        '<h2 class="bl-win-title">' + title + '</h2>' +
        '<p class="bl-win-eq">' + eqText + '</p>' +
        '<p class="bl-win-meta">' +
          (st.hints > 0 ? 'Dùng ' + st.hints + ' gợi ý 💡' : 'Không cần gợi ý nào 💪') +
          (isNew ? '<br>🏆 <strong>Kỷ lục mới của màn này!</strong>' : '') +
        '</p>' +
        '<div class="bl-win-btns">' +
          (next
            ? '<button type="button" class="bl-win-next" id="blWinNext">Màn tiếp theo ▸</button>'
            : '<p class="bl-win-done">🎓 Bạn đã phá đảo toàn bộ thử thách!</p>') +
          '<button type="button" class="bl-win-map" id="blWinMap">Chọn màn</button>' +
        '</div>' +
      '</div>';
    body.appendChild(overlay);

    var nextBtn = document.getElementById('blWinNext');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        sfx('tap');
        startLevel(next);
      });
    }
    document.getElementById('blWinMap').addEventListener('click', function () {
      sfx('tap');
      st = null;
      open();
    });
  }

  return {
    open: open,
    parseFormula: parseFormula, // dùng cho kiểm thử dataset
  };
})();
