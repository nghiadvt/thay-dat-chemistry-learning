(function () {
  'use strict';

  var MODAL_MAX_W = 640;
  var MODAL_MAX_H = 480;
  var EDGE_HIT_PX = 10;
  var MIN_RECT_PX = 8;
  var PALETTE_MAX_COLORS = 6;
  var PALETTE_TARGET_SAMPLES = 8000;

  // Pixel được coi là "nền" nếu, sau khi hòa (blend) với nền trắng theo alpha của nó,
  // màu kết quả gần trắng trong ngưỡng cho phép. Cách này xử lý đúng cả 2 trường hợp:
  // pixel trong suốt hoàn toàn (alpha=0) và pixel mờ dần ở viền (anti-alias/bóng nhạt,
  // alpha thấp nhưng chưa =0) — trước đây chỉ pixel alpha=0 mới được coi là nền, khiến
  // viền mờ (thường gặp ở cạnh có đổ bóng, hay bị lệch về 1 phía như cạnh dưới) không
  // được nhận diện là nền và không bị cắt.
  function isBackgroundPixel(data, idx, tolerance) {
    var a = data[idx + 3] / 255;
    var threshold = 255 - tolerance;
    var r = data[idx] * a + 255 * (1 - a);
    var g = data[idx + 1] * a + 255 * (1 - a);
    var b = data[idx + 2] * a + 255 * (1 - a);
    return r >= threshold && g >= threshold && b >= threshold;
  }

  function detectTrimBounds(data, width, height, tolerance) {
    function rowHasContent(y) {
      var rowStart = y * width * 4;
      for (var x = 0; x < width; x++) {
        if (!isBackgroundPixel(data, rowStart + x * 4, tolerance)) return true;
      }
      return false;
    }

    function colHasContent(x, top, bottom) {
      for (var y = top; y <= bottom; y++) {
        if (!isBackgroundPixel(data, (y * width + x) * 4, tolerance)) return true;
      }
      return false;
    }

    var top = 0;
    while (top < height && !rowHasContent(top)) top++;
    if (top === height) return null;

    var bottom = height - 1;
    while (bottom > top && !rowHasContent(bottom)) bottom--;

    var left = 0;
    while (left < width && !colHasContent(left, top, bottom)) left++;

    var right = width - 1;
    while (right > left && !colHasContent(right, top, bottom)) right--;

    return { x: left, y: top, w: right - left + 1, h: bottom - top + 1 };
  }

  function drawFullCanvas(img) {
    var w = img.naturalWidth || img.width;
    var h = img.naturalHeight || img.height;
    var canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
    return canvas;
  }

  function cropCanvas(srcCanvas, bbox) {
    var out = document.createElement('canvas');
    out.width = bbox.w;
    out.height = bbox.h;
    out.getContext('2d').drawImage(srcCanvas, bbox.x, bbox.y, bbox.w, bbox.h, 0, 0, bbox.w, bbox.h);
    return out;
  }

  function trimImage(img, tolerance) {
    var src = drawFullCanvas(img);
    var w = src.width;
    var h = src.height;

    var bbox = null;
    try {
      var imageData = src.getContext('2d').getImageData(0, 0, w, h);
      bbox = detectTrimBounds(imageData.data, w, h, tolerance);
    } catch (e) {
      bbox = null;
    }

    if (!bbox) bbox = { x: 0, y: 0, w: w, h: h };
    var trimmed = !(bbox.x === 0 && bbox.y === 0 && bbox.w === w && bbox.h === h);
    var out = trimmed ? cropCanvas(src, bbox) : src;

    return {
      canvas: out,
      dataUrl: out.toDataURL('image/png'),
      originalW: w,
      originalH: h,
      trimmedW: bbox.w,
      trimmedH: bbox.h,
      trimmed: trimmed,
      manual: false,
      bbox: bbox,
    };
  }

  function manualCropImage(img, bbox) {
    var src = drawFullCanvas(img);
    var out = cropCanvas(src, bbox);
    return {
      canvas: out,
      dataUrl: out.toDataURL('image/png'),
      originalW: src.width,
      originalH: src.height,
      trimmedW: bbox.w,
      trimmedH: bbox.h,
      trimmed: true,
      manual: true,
      bbox: bbox,
    };
  }

  function hexToRgb(hex) {
    hex = hex.replace('#', '');
    return {
      r: parseInt(hex.substring(0, 2), 16),
      g: parseInt(hex.substring(2, 4), 16),
      b: parseInt(hex.substring(4, 6), 16),
    };
  }

  // Quét loang (flood fill) từ 4 cạnh ảnh vào trong: chỉ những pixel gần-trắng nào
  // *nối liền* tới viền ảnh qua các pixel gần-trắng khác mới bị coi là nền. Gặp pixel
  // khác màu trắng (ngoài ngưỡng) thì dừng lan ở đó, không quét tiếp vào trong — nhờ vậy
  // các mảng màu trắng nằm lọt bên trong hình (được bao quanh bởi màu khác) không bị
  // xóa theo, dù vẫn cùng ngưỡng màu với nền. Trước đây kiểm tra từng pixel độc lập nên
  // xóa luôn cả trắng ở giữa ảnh, không phân biệt được với trắng ở nền.
  // `bounds` (tùy chọn) = {x0,y0,x1,y1}: giới hạn vùng loang trong 1 hình chữ nhật con
  // thay vì cả canvas — dùng để loang riêng trong 1 "vùng khoanh", xuất phát từ viền của
  // chính hình chữ nhật đó (không phải viền toàn ảnh). Không truyền bounds = loang từ viền
  // toàn ảnh như trước.
  function computeBorderBackgroundMask(data, width, height, tolerance, bounds) {
    var x0 = bounds ? bounds.x0 : 0;
    var y0 = bounds ? bounds.y0 : 0;
    var x1 = bounds ? bounds.x1 : width;
    var y1 = bounds ? bounds.y1 : height;

    var total = width * height;
    var mask = new Uint8Array(total);
    var visited = new Uint8Array(total);
    var queue = new Int32Array(total);
    var qTail = 0;

    function tryEnqueue(x, y) {
      if (x < x0 || x >= x1 || y < y0 || y >= y1) return;
      var p = y * width + x;
      if (visited[p]) return;
      visited[p] = 1;
      if (!isBackgroundPixel(data, p * 4, tolerance)) return;
      mask[p] = 1;
      queue[qTail++] = p;
    }

    for (var x = x0; x < x1; x++) {
      tryEnqueue(x, y0);
      tryEnqueue(x, y1 - 1);
    }
    for (var y = y0; y < y1; y++) {
      tryEnqueue(x0, y);
      tryEnqueue(x1 - 1, y);
    }

    var qHead = 0;
    while (qHead < qTail) {
      var p = queue[qHead++];
      var px = p % width;
      var py = (p / width) | 0;
      tryEnqueue(px - 1, py);
      tryEnqueue(px + 1, py);
      tryEnqueue(px, py - 1);
      tryEnqueue(px, py + 1);
      tryEnqueue(px - 1, py - 1);
      tryEnqueue(px + 1, py - 1);
      tryEnqueue(px - 1, py + 1);
      tryEnqueue(px + 1, py + 1);
    }

    return mask;
  }

  // Mask nền cho cả ảnh: mặc định duyệt-từng-pixel (xóa mọi pixel gần trắng, kể cả lọt
  // giữa hình) — đúng như hành vi gốc trước khi có loang-từ-viền. Với mỗi "vùng khoanh"
  // (regions, tọa độ theo đúng canvas đang xử lý), ghi đè phần mask bên trong vùng đó bằng
  // kết quả loang-từ-viền-của-vùng — chỉ nơi được khoanh mới được bảo vệ trắng lọt bên
  // trong, còn lại (không khoanh) vẫn xóa trắng vô điều kiện như mặc định.
  function computeBackgroundMask(data, width, height, tolerance, regions) {
    var total = width * height;
    var mask = new Uint8Array(total);
    for (var p = 0; p < total; p++) {
      mask[p] = isBackgroundPixel(data, p * 4, tolerance) ? 1 : 0;
    }

    (regions || []).forEach(function (region) {
      var x0 = Math.max(0, Math.round(region.x));
      var y0 = Math.max(0, Math.round(region.y));
      var x1 = Math.min(width, Math.round(region.x + region.w));
      var y1 = Math.min(height, Math.round(region.y + region.h));
      if (x1 <= x0 || y1 <= y0) return;

      var floodMask = computeBorderBackgroundMask(data, width, height, tolerance, { x0: x0, y0: y0, x1: x1, y1: y1 });
      for (var y = y0; y < y1; y++) {
        for (var x = x0; x < x1; x++) {
          var idx = y * width + x;
          mask[idx] = floodMask[idx];
        }
      }
    });

    return mask;
  }

  // Vẽ các nét "vách ngăn" (brush strokes) trực tiếp lên canvas, ngăn màu loang lan qua
  // khi 2 vùng trắng lẽ ra tách biệt lại bị nối bởi 1 khe hở nhỏ (viền mờ/anti-alias).
  // originX/originY/scale dùng để quy đổi tọa độ điểm vẽ (lưu theo ảnh gốc chưa cắt) sang
  // đúng hệ tọa độ của canvas đang vẽ (canvas xem trước thu nhỏ, hoặc canvas đã cắt kích
  // thước thật).
  function paintStrokesOnCanvas(canvas, strokes, originX, originY, scale) {
    if (!strokes || !strokes.length) return canvas;
    var ctx = canvas.getContext('2d');

    strokes.forEach(function (stroke) {
      if (!stroke.points || !stroke.points.length) return;
      var lineWidth = Math.max(1, stroke.size * scale);

      if (stroke.points.length === 1) {
        var pt = stroke.points[0];
        ctx.beginPath();
        ctx.arc((pt.x - originX) * scale, (pt.y - originY) * scale, lineWidth / 2, 0, Math.PI * 2);
        ctx.fillStyle = stroke.color;
        ctx.fill();
        return;
      }

      ctx.save();
      ctx.strokeStyle = stroke.color;
      ctx.lineWidth = lineWidth;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      stroke.points.forEach(function (p, i) {
        var x = (p.x - originX) * scale;
        var y = (p.y - originY) * scale;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();
      ctx.restore();
    });

    return canvas;
  }

  // Bản "result-shaped" của paintStrokesOnCanvas: không sửa canvas gốc (copy sang canvas
  // mới trước khi vẽ), dùng trong pipeline xử lý thật (computeItemResult) — nơi
  // item.trimResult.canvas phải giữ nguyên để còn dùng lại cho các lần tính toán khác.
  // Không cần dataUrl vì đây chỉ là kết quả trung gian, không hiển thị trực tiếp.
  function paintBrushStrokes(baseResult, strokes, originX, originY, scale) {
    if (!strokes || !strokes.length) return baseResult;

    var w = baseResult.canvas.width;
    var h = baseResult.canvas.height;
    var out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    out.getContext('2d').drawImage(baseResult.canvas, 0, 0);
    paintStrokesOnCanvas(out, strokes, originX, originY, scale);

    return {
      canvas: out,
      originalW: baseResult.originalW,
      originalH: baseResult.originalH,
      trimmedW: baseResult.trimmedW,
      trimmedH: baseResult.trimmedH,
      trimmed: baseResult.trimmed,
      manual: baseResult.manual,
      bbox: baseResult.bbox,
    };
  }

  // mode: 'keep' (không đổi) | 'remove' (xóa nền, giữ màu gốc) | 'recolor' (xóa nền + đổi màu nội dung)
  // regions: (tùy chọn) các hình chữ nhật "khoanh vùng" — theo tọa độ của baseResult.canvas
  // — nơi dùng loang-từ-viền thay vì duyệt-từng-pixel mặc định.
  function applyBackgroundMode(baseResult, tolerance, mode, hexColor, regions) {
    if (mode === 'keep') return baseResult;

    var w = baseResult.canvas.width;
    var h = baseResult.canvas.height;
    var imageData = baseResult.canvas.getContext('2d').getImageData(0, 0, w, h);
    var data = imageData.data;
    var rgb = mode === 'recolor' ? hexToRgb(hexColor) : null;
    var mask = computeBackgroundMask(data, w, h, tolerance, regions);

    for (var p = 0; p < mask.length; p++) {
      var i = p * 4;
      if (mask[p]) {
        data[i + 3] = 0;
      } else if (rgb) {
        data[i] = rgb.r;
        data[i + 1] = rgb.g;
        data[i + 2] = rgb.b;
      }
    }

    var out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    out.getContext('2d').putImageData(imageData, 0, 0);

    return {
      canvas: out,
      dataUrl: out.toDataURL('image/png'),
      originalW: baseResult.originalW,
      originalH: baseResult.originalH,
      trimmedW: baseResult.trimmedW,
      trimmedH: baseResult.trimmedH,
      trimmed: baseResult.trimmed,
      manual: baseResult.manual,
      bbox: baseResult.bbox,
      bgMode: mode,
    };
  }

  // Đổi kích thước ảnh theo % (100 = giữ nguyên). Dùng làm bước xử lý thật (không phải
  // chỉ lúc xuất file), nên ảnh xem trước cũng phản ánh đúng kích thước mới.
  function applyResize(baseResult, percent) {
    if (!percent || percent === 100) return baseResult;

    var w = Math.max(1, Math.round(baseResult.canvas.width * percent / 100));
    var h = Math.max(1, Math.round(baseResult.canvas.height * percent / 100));
    var out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    out.getContext('2d').drawImage(baseResult.canvas, 0, 0, w, h);

    return {
      canvas: out,
      dataUrl: out.toDataURL('image/png'),
      originalW: baseResult.originalW,
      originalH: baseResult.originalH,
      trimmedW: w,
      trimmedH: h,
      trimmed: baseResult.trimmed,
      manual: baseResult.manual,
      bbox: baseResult.bbox,
      bgMode: baseResult.bgMode,
      resized: true,
    };
  }

  // Blur hộp 3x3 đơn giản — dùng làm nền cho unsharp mask bên dưới.
  function boxBlur3x3(data, width, height) {
    var out = new Uint8ClampedArray(data.length);
    for (var y = 0; y < height; y++) {
      for (var x = 0; x < width; x++) {
        var rSum = 0, gSum = 0, bSum = 0, count = 0;
        for (var dy = -1; dy <= 1; dy++) {
          var ny = y + dy;
          if (ny < 0 || ny >= height) continue;
          for (var dx = -1; dx <= 1; dx++) {
            var nx = x + dx;
            if (nx < 0 || nx >= width) continue;
            var idx = (ny * width + nx) * 4;
            rSum += data[idx];
            gSum += data[idx + 1];
            bSum += data[idx + 2];
            count++;
          }
        }
        var outIdx = (y * width + x) * 4;
        out[outIdx] = rSum / count;
        out[outIdx + 1] = gSum / count;
        out[outIdx + 2] = bSum / count;
      }
    }
    return out;
  }

  // Làm nét kiểu "unsharp mask": pixel mới = gốc + strength*(gốc - ảnh đã blur), chỉ áp
  // lên RGB (giữ alpha nguyên) — giúp bù lại độ mờ do phóng to/resize hoặc ảnh gốc mờ sẵn.
  // amount: 0 (không làm nét) .. 100 (nét nhất).
  function applySharpen(baseResult, amount) {
    if (!amount) return baseResult;

    var w = baseResult.canvas.width;
    var h = baseResult.canvas.height;
    var imageData = baseResult.canvas.getContext('2d').getImageData(0, 0, w, h);
    var data = imageData.data;
    var blurred = boxBlur3x3(data, w, h);
    var strength = (amount / 100) * 2;

    for (var i = 0; i < data.length; i += 4) {
      data[i] = data[i] + strength * (data[i] - blurred[i]);
      data[i + 1] = data[i + 1] + strength * (data[i + 1] - blurred[i + 1]);
      data[i + 2] = data[i + 2] + strength * (data[i + 2] - blurred[i + 2]);
    }

    var out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    out.getContext('2d').putImageData(imageData, 0, 0);

    return {
      canvas: out,
      dataUrl: out.toDataURL('image/png'),
      originalW: baseResult.originalW,
      originalH: baseResult.originalH,
      trimmedW: baseResult.trimmedW,
      trimmedH: baseResult.trimmedH,
      trimmed: baseResult.trimmed,
      manual: baseResult.manual,
      bbox: baseResult.bbox,
      bgMode: baseResult.bgMode,
      sharpened: true,
    };
  }

  // Pipeline đầy đủ cho 1 ảnh: Cắt viền (đã có sẵn ở item.trimResult) → Vẽ vách ngăn (brush)
  // → Xử lý nền/màu (theo vùng khoanh) → Đổi kích thước → Làm nét. Làm nét luôn ở bước
  // cuối để bù lại độ mờ do resize gây ra. bgRegions/brushStrokes lưu theo tọa độ ảnh gốc
  // chưa cắt nên phải trừ đi gốc vùng cắt (bbox.x/y) để quy về tọa độ của trimResult.canvas.
  function computeItemResult(item) {
    var bbox = item.trimResult.bbox;
    var painted = paintBrushStrokes(item.trimResult, item.brushStrokes, bbox.x, bbox.y, 1);
    var regions = (item.bgRegions || []).map(function (r) {
      return { x: r.x - bbox.x, y: r.y - bbox.y, w: r.w, h: r.h };
    });
    var bgResult = applyBackgroundMode(painted, item.bgTolerance, item.bgMode, item.recolorColor, regions);
    var resizedResult = applyResize(bgResult, item.resizePercent);
    return applySharpen(resizedResult, item.sharpenAmount);
  }

  function extractPalette(canvas, tolerance) {
    var w = canvas.width;
    var h = canvas.height;
    var data;
    try {
      data = canvas.getContext('2d').getImageData(0, 0, w, h).data;
    } catch (e) {
      return [];
    }

    var totalPixels = w * h;
    var stride = Math.max(1, Math.round(totalPixels / PALETTE_TARGET_SAMPLES));
    var counts = {};

    for (var p = 0; p < totalPixels; p += stride) {
      var idx = p * 4;
      if (isBackgroundPixel(data, idx, tolerance)) continue;
      var r = (data[idx] >> 4) << 4;
      var g = (data[idx + 1] >> 4) << 4;
      var b = (data[idx + 2] >> 4) << 4;
      var key = r + ',' + g + ',' + b;
      counts[key] = (counts[key] || 0) + 1;
    }

    return Object.keys(counts)
      .map(function (key) {
        var parts = key.split(',').map(Number);
        return { r: parts[0], g: parts[1], b: parts[2], count: counts[key] };
      })
      .sort(function (a, b) { return b.count - a.count; })
      .slice(0, PALETTE_MAX_COLORS)
      .map(function (c) {
        return '#' + [c.r, c.g, c.b].map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('');
      });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildDownloadFilename(originalName) {
    return originalName.replace(/\.[^.]+$/, '') + '-trimmed.png';
  }

  // ---- Bảng chọn màu: preset có sẵn (dùng chung TAG_PRESET_COLORS của app) + lịch sử
  // "màu tùy chỉnh" người dùng từng chọn, lưu qua localStorage để lần chọn màu sau (cho
  // ảnh khác, hoặc trong modal chỉnh tay) cũng thấy lại ngay, khỏi phải pha màu lại từ đầu.
  var CUSTOM_COLORS_STORAGE_KEY = 'htd_image_trimmer_custom_colors';
  var CUSTOM_COLORS_MAX = 12;
  var GRAYSCALE_COLORS = ['#000000', '#404040', '#737373', '#a3a3a3', '#d4d4d4', '#ffffff'];

  function loadCustomColors() {
    try {
      var raw = window.localStorage.getItem(CUSTOM_COLORS_STORAGE_KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function saveCustomColors(colors) {
    try { window.localStorage.setItem(CUSTOM_COLORS_STORAGE_KEY, JSON.stringify(colors)); } catch (e) {}
  }

  function createColorPickerRegistry() {
    var customColors = loadCustomColors();
    var instances = [];

    function addCustomColor(hex) {
      hex = hex.toLowerCase();
      customColors = customColors.filter(function (c) { return c !== hex; });
      customColors.unshift(hex);
      if (customColors.length > CUSTOM_COLORS_MAX) customColors = customColors.slice(0, CUSTOM_COLORS_MAX);
      saveCustomColors(customColors);
      instances.forEach(function (inst) { inst.renderCustomGrid(); });
    }

    function createColorPicker(container) {
      if (!container) return null;
      var presetColors = GRAYSCALE_COLORS.concat(window.TAG_PRESET_COLORS || []);

      container.innerHTML =
        '<button type="button" class="it-colorpicker-trigger">' +
          '<span class="it-colorpicker-swatch"></span>' +
          '<span class="it-colorpicker-trigger-label">Chọn màu</span>' +
        '</button>' +
        '<div class="it-colorpicker-panel" hidden>' +
          '<div class="it-colorpicker-section-label">Màu có sẵn</div>' +
          '<div class="it-colorpicker-grid it-colorpicker-grid--preset"></div>' +
          '<div class="it-colorpicker-section-label">Màu tùy chỉnh</div>' +
          '<div class="it-colorpicker-grid it-colorpicker-grid--custom"></div>' +
          '<input type="color" class="it-colorpicker-native-input">' +
        '</div>';

      var trigger = container.querySelector('.it-colorpicker-trigger');
      var swatch = container.querySelector('.it-colorpicker-swatch');
      var panel = container.querySelector('.it-colorpicker-panel');
      var presetGrid = container.querySelector('.it-colorpicker-grid--preset');
      var customGrid = container.querySelector('.it-colorpicker-grid--custom');
      var nativeInput = container.querySelector('.it-colorpicker-native-input');

      var currentValue = '#ff0000';
      var onPickCallback = null;

      function updateActiveState() {
        var all = container.querySelectorAll('.it-colorpicker-swatch-btn');
        Array.prototype.forEach.call(all, function (btn) {
          var btnHex = btn.getAttribute('data-hex');
          btn.classList.toggle('is-active', !!btnHex && btnHex.toLowerCase() === currentValue.toLowerCase());
        });
      }

      function addSwatchButton(grid, hex) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'it-colorpicker-swatch-btn';
        btn.setAttribute('data-hex', hex);
        btn.style.background = hex;
        btn.title = hex;
        btn.addEventListener('click', function () { selectColor(hex); });
        grid.appendChild(btn);
      }

      function addPickerButton(grid) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'it-colorpicker-swatch-btn it-colorpicker-swatch-btn--add';
        btn.title = 'Thêm màu tùy chỉnh';
        btn.textContent = '+';
        btn.addEventListener('click', function () { nativeInput.click(); });
        grid.appendChild(btn);
      }

      function renderPresetGrid() {
        presetGrid.innerHTML = '';
        presetColors.forEach(function (hex) { addSwatchButton(presetGrid, hex); });
        updateActiveState();
      }

      function renderCustomGrid() {
        customGrid.innerHTML = '';
        customColors.forEach(function (hex) { addSwatchButton(customGrid, hex); });
        addPickerButton(customGrid);
        updateActiveState();
      }

      function selectColor(hex) {
        currentValue = hex;
        swatch.style.background = hex;
        updateActiveState();
        closePanel();
        if (onPickCallback) onPickCallback(hex);
      }

      function openPanel() { panel.hidden = false; }
      function closePanel() { panel.hidden = true; }

      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        panel.hidden ? openPanel() : closePanel();
      });

      nativeInput.addEventListener('change', function () {
        addCustomColor(nativeInput.value);
        selectColor(nativeInput.value);
      });

      document.addEventListener('click', function (e) {
        if (!container.contains(e.target)) closePanel();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePanel();
      });

      renderPresetGrid();
      renderCustomGrid();

      var instance = {
        setValue: function (hex) {
          currentValue = hex;
          swatch.style.background = hex;
          updateActiveState();
        },
        getValue: function () { return currentValue; },
        onPick: function (cb) { onPickCallback = cb; },
        renderCustomGrid: renderCustomGrid,
        setHidden: function (hidden) { container.hidden = hidden; },
      };
      instances.push(instance);
      return instance;
    }

    return { createColorPicker: createColorPicker };
  }

  function hitTestEdge(px, py, rect, hitPad) {
    var candidates = [];
    var right = rect.x + rect.w;
    var bottom = rect.y + rect.h;

    if (py >= rect.y - hitPad && py <= rect.y + hitPad && px >= rect.x - hitPad && px <= right + hitPad) {
      candidates.push({ edge: 'top', dist: Math.abs(py - rect.y) });
    }
    if (py >= bottom - hitPad && py <= bottom + hitPad && px >= rect.x - hitPad && px <= right + hitPad) {
      candidates.push({ edge: 'bottom', dist: Math.abs(py - bottom) });
    }
    if (px >= rect.x - hitPad && px <= rect.x + hitPad && py >= rect.y - hitPad && py <= bottom + hitPad) {
      candidates.push({ edge: 'left', dist: Math.abs(px - rect.x) });
    }
    if (px >= right - hitPad && px <= right + hitPad && py >= rect.y - hitPad && py <= bottom + hitPad) {
      candidates.push({ edge: 'right', dist: Math.abs(px - right) });
    }

    if (!candidates.length) return null;
    candidates.sort(function (a, b) { return a.dist - b.dist; });
    return candidates[0].edge;
  }

  function computeRectForDrag(startRect, edge, px, py, canvasW, canvasH) {
    var rect = { x: startRect.x, y: startRect.y, w: startRect.w, h: startRect.h };
    var right = startRect.x + startRect.w;
    var bottom = startRect.y + startRect.h;

    if (edge === 'top') {
      var newTop = Math.min(Math.max(0, py), bottom - MIN_RECT_PX);
      rect.y = newTop;
      rect.h = bottom - newTop;
    } else if (edge === 'bottom') {
      var newBottom = Math.max(Math.min(canvasH, py), startRect.y + MIN_RECT_PX);
      rect.h = newBottom - startRect.y;
    } else if (edge === 'left') {
      var newLeft = Math.min(Math.max(0, px), right - MIN_RECT_PX);
      rect.x = newLeft;
      rect.w = right - newLeft;
    } else if (edge === 'right') {
      var newRight = Math.max(Math.min(canvasW, px), startRect.x + MIN_RECT_PX);
      rect.w = newRight - startRect.x;
    }

    return rect;
  }

  // Chuẩn hóa 2 điểm kéo (bất kỳ thứ tự) thành hình chữ nhật {x,y,w,h}, kẹp trong phạm vi
  // canvas — dùng khi kéo tạo "vùng khoanh" mới (khác với computeRectForDrag ở trên, vốn
  // kéo 1 CẠNH của rect có sẵn chứ không tạo rect mới từ 2 góc).
  function normalizeRectFromPoints(a, b, canvasW, canvasH) {
    var x0 = Math.min(Math.max(0, a.x), canvasW);
    var y0 = Math.min(Math.max(0, a.y), canvasH);
    var x1 = Math.min(Math.max(0, b.x), canvasW);
    var y1 = Math.min(Math.max(0, b.y), canvasH);
    return {
      x: Math.min(x0, x1),
      y: Math.min(y0, y1),
      w: Math.abs(x1 - x0),
      h: Math.abs(y1 - y0),
    };
  }

  function init(opts) {
    opts = opts || {};
    var fileInput = document.querySelector(opts.fileInputEl);
    if (!fileInput) return;

    var uploadZone = document.querySelector(opts.uploadZoneEl);

    var lightbox = document.querySelector(opts.lightboxEl);
    var lightboxImg = document.querySelector(opts.lightboxImgEl);
    var lightboxBackdrop = document.querySelector(opts.lightboxBackdropEl);
    var lightboxCloseBtn = document.querySelector(opts.lightboxCloseBtnEl);
    var lightboxPrevBtn = document.querySelector(opts.lightboxPrevBtnEl);
    var lightboxNextBtn = document.querySelector(opts.lightboxNextBtnEl);
    var lightboxCaption = document.querySelector(opts.lightboxCaptionEl);

    var toleranceInput = document.querySelector(opts.toleranceInputEl);
    var toleranceValueEl = document.querySelector(opts.toleranceValueEl);

    var bgModeGroup = document.querySelector(opts.bgModeGroupEl);
    var bgModeControls = document.querySelector(opts.bgModeControlsEl);
    var bgToleranceInput = document.querySelector(opts.bgToleranceInputEl);
    var bgToleranceValueEl = document.querySelector(opts.bgToleranceValueEl);
    var bgModeRadios = bgModeGroup ? Array.prototype.slice.call(bgModeGroup.querySelectorAll('input[type=radio]')) : [];

    var reprocessBtn = document.querySelector(opts.reprocessBtnEl);
    var downloadAllBtn = document.querySelector(opts.downloadAllBtnEl);
    var clearAllBtn = document.querySelector(opts.clearAllBtnEl);
    var progressHint = document.querySelector(opts.progressHintEl);
    var resultsCard = document.querySelector(opts.resultsCardEl);
    var resultList = document.querySelector(opts.resultListEl);
    var resultCountEl = document.querySelector(opts.resultCountEl);
    var emptyHint = document.querySelector(opts.emptyHintEl);

    var editModal = document.querySelector(opts.editModalEl);
    var editCanvas = document.querySelector(opts.editCanvasEl);
    var editBackdrop = document.querySelector(opts.editBackdropEl);
    var editCloseBtn = document.querySelector(opts.editCloseBtnEl);
    var editCancelBtn = document.querySelector(opts.editCancelBtnEl);
    var editResetBtn = document.querySelector(opts.editResetBtnEl);
    var editApplyBtn = document.querySelector(opts.editApplyBtnEl);
    var editBgModeGroup = document.querySelector(opts.editBgModeGroupEl);
    var editBgModeControls = document.querySelector(opts.editBgModeControlsEl);
    var editBgToleranceInput = document.querySelector(opts.editBgToleranceInputEl);
    var editBgModeRadios = editBgModeGroup ? Array.prototype.slice.call(editBgModeGroup.querySelectorAll('input[type=radio]')) : [];
    var editModeHint = document.querySelector(opts.editModeHintEl);
    var editModeButtons = editModal ? Array.prototype.slice.call(editModal.querySelectorAll('[data-edit-mode]')) : [];
    var editRegionControls = document.querySelector(opts.editRegionControlsEl);
    var editClearRegionsBtn = document.querySelector(opts.editClearRegionsBtnEl);
    var editDrawControls = document.querySelector(opts.editDrawControlsEl);
    var editDrawSizeInput = document.querySelector(opts.editDrawSizeInputEl);
    var editDrawSizeValueEl = document.querySelector(opts.editDrawSizeValueEl);
    var editUndoStrokeBtn = document.querySelector(opts.editUndoStrokeBtnEl);
    var editClearStrokesBtn = document.querySelector(opts.editClearStrokesBtnEl);

    var colorPickerRegistry = createColorPickerRegistry();
    var recolorPicker = colorPickerRegistry.createColorPicker(document.querySelector(opts.recolorColorEl));
    var editRecolorPicker = colorPickerRegistry.createColorPicker(document.querySelector(opts.editRecolorColorEl));
    var editDrawColorPicker = colorPickerRegistry.createColorPicker(document.querySelector(opts.editDrawColorEl));

    var downloadModal = document.querySelector(opts.downloadModalEl);
    var downloadList = document.querySelector(opts.downloadListEl);
    var downloadSelectAll = document.querySelector(opts.downloadSelectAllEl);
    var downloadCountEl = document.querySelector(opts.downloadCountEl);
    var downloadConfirmBtn = document.querySelector(opts.downloadConfirmBtnEl);
    var downloadCancelBtn = document.querySelector(opts.downloadCancelBtnEl);
    var downloadCloseBtn = document.querySelector(opts.downloadCloseBtnEl);
    var downloadBackdrop = document.querySelector(opts.downloadBackdropEl);

    var selectAllCheckbox = document.querySelector(opts.selectAllEl);
    var selectedCountEl = document.querySelector(opts.selectedCountEl);
    var applyBgBtn = document.querySelector(opts.applyBgBtnEl);
    var applyBgCountEl = document.querySelector(opts.applyBgCountEl);
    var deleteSelectedBtn = document.querySelector(opts.deleteSelectedBtnEl);

    var resizeScaleInput = document.querySelector(opts.resizeScaleInputEl);
    var resizeScaleValueEl = document.querySelector(opts.resizeScaleValueEl);
    var sharpenAmountInput = document.querySelector(opts.sharpenAmountInputEl);
    var sharpenAmountValueEl = document.querySelector(opts.sharpenAmountValueEl);
    var applyResizeBtn = document.querySelector(opts.applyResizeBtnEl);
    var applyResizeCountEl = document.querySelector(opts.applyResizeCountEl);

    // ---- Workspace kiểu canvas (menu công cụ trái + ảnh giữa + filmstrip dưới) ----
    var workspace = document.querySelector(opts.workspaceEl);
    var emptyState = document.querySelector(opts.emptyStateEl);
    var sidebar = document.querySelector(opts.sidebarEl);
    var sidebarToggle = document.querySelector(opts.sidebarToggleEl);
    var toolGroups = document.querySelector(opts.toolGroupsEl);
    var stage = document.querySelector(opts.stageEl);
    var stageImg = document.querySelector(opts.stageImgEl);
    var stageMeta = document.querySelector(opts.stageMetaEl);
    var stageFlag = document.querySelector(opts.stageFlagEl);
    var stageError = document.querySelector(opts.stageErrorEl);
    var filmstrip = document.querySelector(opts.filmstripEl);
    var addMoreBtn = document.querySelector(opts.addMoreBtnEl);
    var compareBtn = document.querySelector(opts.compareBtnEl);
    var zoomBtn = document.querySelector(opts.zoomBtnEl);
    var manualEditBtn = document.querySelector(opts.manualEditBtnEl);
    var downloadActiveBtn = document.querySelector(opts.downloadActiveBtnEl);
    var deleteActiveBtn = document.querySelector(opts.deleteActiveBtnEl);

    var items = [];
    var seq = 0;
    var DEFAULT_BG_TOLERANCE = 12;
    var DEFAULT_BRUSH_SIZE = 6;
    var DEFAULT_BRUSH_COLOR = '#000000';
    var activeId = null;
    var isComparing = false;

    function currentTolerance() {
      return parseInt(toleranceInput.value, 10) || 0;
    }

    function getPendingBgMode() {
      var checked = bgModeRadios.filter(function (r) { return r.checked; })[0];
      return checked ? checked.value : 'keep';
    }

    function updateMainBgControlsVisibility() {
      var mode = getPendingBgMode();
      bgModeControls.hidden = mode === 'keep';
      recolorPicker.setHidden(mode !== 'recolor');
    }

    function waitForImageLoad(item) {
      if (item.loaded) return Promise.resolve();
      return new Promise(function (resolve) {
        item.img.addEventListener('load', function () {
          item.loaded = true;
          item.originalW = item.img.naturalWidth;
          item.originalH = item.img.naturalHeight;
          resolve();
        }, { once: true });
        item.img.addEventListener('error', function () {
          item.loaded = true;
          item.error = true;
          resolve();
        }, { once: true });
      });
    }

    function createItem(file) {
      var objectUrl = URL.createObjectURL(file);
      var img = new Image();
      img.src = objectUrl;
      return {
        id: 'it' + (++seq),
        file: file,
        objectUrl: objectUrl,
        img: img,
        loaded: false,
        error: false,
        selected: true,
        manualOverride: false,
        trimResult: null,
        result: null,
        palette: null,
        bgMode: 'keep',
        bgTolerance: DEFAULT_BG_TOLERANCE,
        recolorColor: '#ff0000',
        resizePercent: 100,
        sharpenAmount: 0,
        bgRegions: [],
        brushStrokes: [],
      };
    }

    function setProgress(text) {
      if (!progressHint) return;
      progressHint.hidden = !text;
      progressHint.textContent = text || '';
    }

    function refreshSelectionUI() {
      var total = items.length;
      var selectedCount = items.filter(function (it) { return it.selected; }).length;

      reprocessBtn.disabled = selectedCount === 0;
      applyBgBtn.disabled = selectedCount === 0;
      applyResizeBtn.disabled = selectedCount === 0;
      deleteSelectedBtn.disabled = selectedCount === 0;
      downloadAllBtn.disabled = total === 0;
      clearAllBtn.disabled = total === 0;

      applyBgCountEl.textContent = String(selectedCount);
      applyResizeCountEl.textContent = String(selectedCount);
      selectedCountEl.textContent = total > 0 ? (selectedCount + '/' + total + ' ảnh được chọn') : '';
      selectAllCheckbox.checked = total > 0 && selectedCount === total;
      selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < total;
    }

    // (Re)cắt viền + xử lý nền/màu cho đúng danh sách ảnh được truyền vào — dùng cho cả
    // lúc thêm ảnh mới (chỉ ảnh mới) và lúc bấm "Xử lý lại" (chỉ ảnh đang được chọn).
    function trimItems(targetItems) {
      if (!targetItems.length) return Promise.resolve();
      var tolerance = currentTolerance();

      setProgress('Đang chuẩn bị ' + targetItems.length + ' ảnh...');

      return Promise.all(targetItems.map(waitForImageLoad)).then(function () {
        var chain = Promise.resolve();
        targetItems.forEach(function (item, i) {
          chain = chain.then(function () {
            if (item.error) return;
            setProgress('Đang xử lý ' + (i + 1) + '/' + targetItems.length + '...');
            item.trimResult = trimImage(item.img, tolerance);
            item.result = computeItemResult(item);
            item.palette = extractPalette(item.trimResult.canvas, item.bgTolerance);
            return new Promise(function (resolve) { setTimeout(resolve, 0); });
          });
        });
        return chain;
      }).then(function () {
        setProgress('');
        renderResults();
      });
    }

    // "Xử lý lại": chỉ áp ngưỡng cắt mới cho ảnh đang được chọn (checkbox) và chưa bị
    // chỉnh tay — không đụng tới ảnh không được chọn hoặc ảnh đã chỉnh tay riêng.
    function reprocessSelected() {
      var targets = items.filter(function (it) { return it.selected && !it.manualOverride && !it.error; });
      return trimItems(targets);
    }

    // "Áp dụng cho ảnh đã chọn": chỉ đổi nền/màu của các ảnh đang được chọn, không ảnh
    // hưởng tới ảnh khác trong danh sách hay ảnh đang mở trong modal chỉnh tay.
    function applyBgSettingsToSelected() {
      var mode = getPendingBgMode();
      var tolerance = parseInt(bgToleranceInput.value, 10) || 0;
      var color = recolorPicker.getValue();

      var targets = items.filter(function (it) { return it.selected && it.trimResult && !it.error; });
      if (!targets.length) return;

      targets.forEach(function (item) {
        item.bgMode = mode;
        item.bgTolerance = tolerance;
        item.recolorColor = color;
        item.result = computeItemResult(item);
        item.palette = extractPalette(item.trimResult.canvas, tolerance);
      });
      renderResults();
    }

    // "Áp dụng cho ảnh đã chọn" (kích thước & độ nét): chỉ đổi resizePercent/sharpenAmount
    // của ảnh đang được chọn, tương tự applyBgSettingsToSelected().
    function applyResizeSettingsToSelected() {
      var percent = parseInt(resizeScaleInput.value, 10) || 100;
      var sharpen = parseInt(sharpenAmountInput.value, 10) || 0;

      var targets = items.filter(function (it) { return it.selected && it.trimResult && !it.error; });
      if (!targets.length) return;

      targets.forEach(function (item) {
        item.resizePercent = percent;
        item.sharpenAmount = sharpen;
        item.result = computeItemResult(item);
      });
      renderResults();
    }

    function renderResults() {
      resultList.innerHTML = '';

      if (items.length === 0) {
        resultsCard.hidden = true;
        emptyHint.hidden = false;
        refreshSelectionUI();
        renderWorkspace();
        return;
      }

      emptyHint.hidden = true;
      resultsCard.hidden = false;
      resultCountEl.textContent = String(items.length);

      items.forEach(function (item) {
        var li = document.createElement('li');
        li.className = 'it-result-item';

        var checkboxHtml = '<label class="it-result-select"><input type="checkbox" class="it-result-checkbox" data-item-id="' +
          item.id + '"' + (item.selected ? ' checked' : '') + '></label>';

        if (item.error || !item.result) {
          li.innerHTML =
            checkboxHtml +
            '<div class="it-result-info">' +
              '<p class="it-result-filename">' + escapeHtml(item.file.name) + '</p>' +
              '<p class="it-result-dims">Không đọc được ảnh này.</p>' +
            '</div>' +
            '<div class="it-result-actions">' +
              '<button type="button" class="btn btn-secondary btn-sm" data-remove-id="' + item.id + '">Xóa ảnh này</button>' +
            '</div>';
          resultList.appendChild(li);
          return;
        }

        var result = item.result;
        var percent = result.trimmed
          ? Math.round((1 - (result.trimmedW * result.trimmedH) / (result.originalW * result.originalH)) * 100)
          : 0;
        var badgeClass = 'it-result-badge' + (result.trimmed ? '' : ' it-result-badge--unchanged');
        var badgeText = result.manual
          ? 'Đã chỉnh tay'
          : (result.trimmed ? ('-' + percent + '%') : 'Không đổi (không phát hiện khoảng trắng)');
        var paletteHtml = '';
        if (item.palette && item.palette.length) {
          paletteHtml = '<div class="it-palette">' + item.palette.map(function (hex) {
            return '<span class="it-palette-swatch" style="background:' + hex + '" title="' + hex + '"></span>';
          }).join('') + '</div>';
        }

        li.innerHTML =
          checkboxHtml +
          '<div class="it-result-thumbs">' +
            '<figure class="it-result-thumb"><figcaption>Gốc</figcaption><img src="' + item.objectUrl + '" alt="Ảnh gốc" data-item-id="' + item.id + '" data-variant="original">' + paletteHtml + '</figure>' +
            '<div class="it-result-arrow" aria-hidden="true">→</div>' +
            '<figure class="it-result-thumb"><figcaption>Sau khi xử lý</figcaption><img src="' + result.dataUrl + '" alt="Ảnh đã xử lý" data-item-id="' + item.id + '" data-variant="processed"></figure>' +
          '</div>' +
          '<div class="it-result-info">' +
            '<p class="it-result-filename">' + escapeHtml(item.file.name) + '</p>' +
            '<p class="it-result-dims">' + result.originalW + '×' + result.originalH + ' → ' + result.trimmedW + '×' + result.trimmedH +
              ' <span class="' + badgeClass + '">' + badgeText + '</span></p>' +
          '</div>' +
          '<div class="it-result-actions">' +
            '<button type="button" class="btn btn-secondary btn-sm" data-edit-id="' + item.id + '">Chỉnh tay</button>' +
            '<button type="button" class="btn btn-secondary btn-sm" data-download-id="' + item.id + '">Tải xuống</button>' +
            '<button type="button" class="btn btn-secondary btn-sm" data-remove-id="' + item.id + '">Xóa ảnh này</button>' +
          '</div>';

        resultList.appendChild(li);
      });

      refreshSelectionUI();
      renderWorkspace();
    }

    function triggerDownload(item) {
      var a = document.createElement('a');
      a.href = item.result.dataUrl;
      a.download = buildDownloadFilename(item.file.name);
      document.body.appendChild(a);
      a.click();
      a.remove();
    }

    function removeItem(id) {
      var idx = -1;
      for (var i = 0; i < items.length; i++) {
        if (items[i].id === id) { idx = i; break; }
      }
      if (idx === -1) return;
      URL.revokeObjectURL(items[idx].objectUrl);
      items.splice(idx, 1);
      renderResults();
    }

    function deleteSelected() {
      var toRemove = items.filter(function (it) { return it.selected; });
      if (!toRemove.length) return;
      toRemove.forEach(function (it) { URL.revokeObjectURL(it.objectUrl); });
      items = items.filter(function (it) { return !it.selected; });
      renderResults();
    }

    function clearAll() {
      items.forEach(function (item) { URL.revokeObjectURL(item.objectUrl); });
      items = [];
      renderResults();
    }

    // ---- Modal chỉnh tay vùng cắt + khoanh vùng nền + vẽ vách ngăn (xem trước xử lý
    // nền/màu theo đúng logic sẽ áp dụng thật) ----
    // editDraft chỉ áp dụng cho editingItem — thay đổi ở đây KHÔNG được đụng tới
    // bgMode/bgTolerance/recolorColor/bgRegions/brushStrokes của các ảnh khác trong danh
    // sách. Chỉ khi bấm "Áp dụng" thì editDraft mới được commit vào đúng editingItem; bấm
    // "Hủy"/đóng modal thì draft bị bỏ, editingItem giữ nguyên như trước khi mở.
    var editingItem = null;
    var editScale = 1;
    var editRect = null;
    var editBaseCanvas = null;
    var dragEdge = null;
    var dragStartRect = null;
    var editDraft = { bgMode: 'keep', bgTolerance: DEFAULT_BG_TOLERANCE, recolorColor: '#ff0000', bgRegions: [], brushStrokes: [] };

    // Chế độ đang thao tác trên canvas: 'crop' (kéo 4 cạnh, mặc định) | 'region' (khoanh
    // vùng nền) | 'draw' (vẽ vách ngăn).
    var editCanvasMode = 'crop';
    var regionDragStart = null;
    var regionDraftRect = null;
    var currentStroke = null;

    var EDIT_MODE_HINTS = {
      crop: 'Kéo 4 viền nét đứt màu cam để chỉnh vùng giữ lại — phần tối là phần sẽ bị cắt bỏ.',
      region: 'Kéo để khoanh 1 vùng — bên trong vùng khoanh, xóa nền chỉ loang từ viền vào (giữ trắng lọt bên trong); bấm vào vùng có sẵn để xóa vùng đó. Ngoài vùng khoanh vẫn xóa mọi pixel gần trắng như bình thường.',
      draw: 'Vẽ nét lên chỗ 2 vùng trắng lẽ ra tách biệt lại bị dính liền nhau, để ngăn không cho xóa nền loang qua — chọn màu và cỡ nét bên dưới.',
    };

    function updateEditControlsVisibility() {
      editBgModeControls.hidden = editDraft.bgMode === 'keep';
      editRecolorPicker.setHidden(editDraft.bgMode !== 'recolor');
    }

    function setEditCanvasMode(mode) {
      editCanvasMode = mode;
      regionDragStart = null;
      regionDraftRect = null;
      currentStroke = null;
      editModeButtons.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-edit-mode') === mode);
      });
      if (editRegionControls) editRegionControls.hidden = mode !== 'region';
      if (editDrawControls) editDrawControls.hidden = mode !== 'draw';
      if (editModeHint) editModeHint.textContent = EDIT_MODE_HINTS[mode] || '';
      drawEditCanvas();
    }

    function loadEditDraftFromItem(item) {
      editDraft.bgMode = item.bgMode;
      editDraft.bgTolerance = item.bgTolerance;
      editDraft.recolorColor = item.recolorColor;
      editDraft.bgRegions = (item.bgRegions || []).map(function (r) { return { x: r.x, y: r.y, w: r.w, h: r.h }; });
      editDraft.brushStrokes = (item.brushStrokes || []).map(function (s) {
        return { points: s.points.map(function (p) { return { x: p.x, y: p.y }; }), color: s.color, size: s.size };
      });

      editBgModeRadios.forEach(function (r) { r.checked = (r.value === editDraft.bgMode); });
      editBgToleranceInput.value = editDraft.bgTolerance;
      editRecolorPicker.setValue(editDraft.recolorColor);
      updateEditControlsVisibility();
    }

    function rebuildEditBaseCanvas() {
      var w = editCanvas.width;
      var h = editCanvas.height;
      var base = document.createElement('canvas');
      base.width = w;
      base.height = h;
      base.getContext('2d').drawImage(editingItem.img, 0, 0, w, h);

      paintStrokesOnCanvas(base, editDraft.brushStrokes, 0, 0, editScale);

      if (editDraft.bgMode !== 'keep') {
        var scaledRegions = editDraft.bgRegions.map(function (r) {
          return { x: r.x * editScale, y: r.y * editScale, w: r.w * editScale, h: r.h * editScale };
        });
        var preview = applyBackgroundMode(
          { canvas: base, originalW: w, originalH: h, trimmedW: w, trimmedH: h, trimmed: false, manual: false, bbox: null },
          editDraft.bgTolerance,
          editDraft.bgMode,
          editDraft.recolorColor,
          scaledRegions
        );
        base = preview.canvas;
      }
      editBaseCanvas = base;
    }

    var editPreviewRaf = null;
    function scheduleEditPreview() {
      if (editPreviewRaf) return;
      editPreviewRaf = requestAnimationFrame(function () {
        editPreviewRaf = null;
        rebuildEditBaseCanvas();
        drawEditCanvas();
      });
    }

    function openEditModal(item) {
      if (!item.trimResult) return;
      editingItem = item;

      var naturalW = item.trimResult.originalW;
      var naturalH = item.trimResult.originalH;
      editScale = Math.min(1, MODAL_MAX_W / naturalW, MODAL_MAX_H / naturalH);

      editCanvas.width = Math.round(naturalW * editScale);
      editCanvas.height = Math.round(naturalH * editScale);

      var bbox = item.trimResult.bbox || { x: 0, y: 0, w: naturalW, h: naturalH };
      editRect = {
        x: bbox.x * editScale,
        y: bbox.y * editScale,
        w: bbox.w * editScale,
        h: bbox.h * editScale,
      };

      if (editDrawSizeInput) editDrawSizeInput.value = DEFAULT_BRUSH_SIZE;
      if (editDrawSizeValueEl) editDrawSizeValueEl.textContent = String(DEFAULT_BRUSH_SIZE);

      loadEditDraftFromItem(item);
      rebuildEditBaseCanvas();
      setEditCanvasMode('crop');
      drawEditCanvas();
      editModal.hidden = false;
      document.body.classList.add('it-modal-open');
    }

    function closeEditModal() {
      editModal.hidden = true;
      document.body.classList.remove('it-modal-open');
      editingItem = null;
      editRect = null;
      editBaseCanvas = null;
      dragEdge = null;
      dragStartRect = null;
      regionDragStart = null;
      regionDraftRect = null;
      currentStroke = null;
    }

    function drawEditCanvas() {
      if (!editingItem) return;
      var ctx = editCanvas.getContext('2d');
      var w = editCanvas.width;
      var h = editCanvas.height;

      ctx.clearRect(0, 0, w, h);
      ctx.drawImage(editBaseCanvas, 0, 0);

      ctx.fillStyle = 'rgba(15, 23, 42, 0.55)';
      ctx.fillRect(0, 0, w, editRect.y);
      ctx.fillRect(0, editRect.y + editRect.h, w, h - (editRect.y + editRect.h));
      ctx.fillRect(0, editRect.y, editRect.x, editRect.h);
      ctx.fillRect(editRect.x + editRect.w, editRect.y, w - (editRect.x + editRect.w), editRect.h);

      ctx.save();
      ctx.strokeStyle = '#f97316';
      ctx.lineWidth = 2;
      ctx.setLineDash([6, 4]);
      ctx.strokeRect(editRect.x, editRect.y, editRect.w, editRect.h);
      ctx.restore();

      // Vùng khoanh: luôn hiện để giữ ngữ cảnh dù đang ở chế độ nào — chỉ thêm/xóa được
      // khi editCanvasMode === 'region'.
      ctx.save();
      ctx.strokeStyle = '#0ea5e9';
      ctx.fillStyle = 'rgba(14, 165, 233, 0.12)';
      ctx.lineWidth = 1.5;
      ctx.setLineDash([4, 3]);
      editDraft.bgRegions.forEach(function (r) {
        var rx = r.x * editScale, ry = r.y * editScale, rw = r.w * editScale, rh = r.h * editScale;
        ctx.fillRect(rx, ry, rw, rh);
        ctx.strokeRect(rx, ry, rw, rh);
      });
      if (editCanvasMode === 'region' && regionDraftRect) {
        ctx.strokeRect(regionDraftRect.x, regionDraftRect.y, regionDraftRect.w, regionDraftRect.h);
      }
      ctx.restore();

      // Nét vẽ đang dở (chưa nhả chuột): nét đã commit đã được "nướng" thẳng vào
      // editBaseCanvas rồi (qua rebuildEditBaseCanvas), không cần vẽ lại ở đây.
      if (editCanvasMode === 'draw' && currentStroke && currentStroke.points.length) {
        ctx.save();
        ctx.strokeStyle = currentStroke.color;
        ctx.fillStyle = currentStroke.color;
        ctx.lineWidth = Math.max(1, currentStroke.size * editScale);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (currentStroke.points.length === 1) {
          var pt = currentStroke.points[0];
          ctx.beginPath();
          ctx.arc(pt.x * editScale, pt.y * editScale, ctx.lineWidth / 2, 0, Math.PI * 2);
          ctx.fill();
        } else {
          ctx.beginPath();
          currentStroke.points.forEach(function (p, i) {
            var x = p.x * editScale, y = p.y * editScale;
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
          });
          ctx.stroke();
        }
        ctx.restore();
      }
    }

    function getEditCanvasPos(e) {
      var rect = editCanvas.getBoundingClientRect();
      var scaleX = editCanvas.width / rect.width;
      var scaleY = editCanvas.height / rect.height;
      return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
      };
    }

    // ---- Chế độ "Cắt": kéo 4 cạnh editRect (hành vi như cũ) ----
    function onCropPointerDown(e) {
      var pos = getEditCanvasPos(e);
      var edge = hitTestEdge(pos.x, pos.y, editRect, EDGE_HIT_PX);
      if (!edge) return;
      dragEdge = edge;
      dragStartRect = { x: editRect.x, y: editRect.y, w: editRect.w, h: editRect.h };
      editCanvas.setPointerCapture(e.pointerId);
      e.preventDefault();
    }

    function onCropPointerMove(e) {
      if (!dragEdge) return;
      var pos = getEditCanvasPos(e);
      editRect = computeRectForDrag(dragStartRect, dragEdge, pos.x, pos.y, editCanvas.width, editCanvas.height);
      drawEditCanvas();
    }

    function onCropPointerUp() {
      dragEdge = null;
      dragStartRect = null;
    }

    // ---- Chế độ "Khoanh vùng nền": kéo để thêm 1 vùng mới; bấm (không kéo ra hình chữ
    // nhật đủ lớn) vào vùng có sẵn để xóa vùng đó. Lúc đang kéo chỉ vẽ lại khung xem trước
    // (rẻ) — chỉ khi thêm/xóa xong (pointerup) mới tính lại preview xử lý nền (đắt, phải
    // chạy lại loang-từ-viền), giống hệt cách kéo cạnh cắt không đụng preview nền. ----
    function onRegionPointerDown(e) {
      var pos = getEditCanvasPos(e);
      regionDragStart = pos;
      regionDraftRect = { x: pos.x, y: pos.y, w: 0, h: 0 };
      editCanvas.setPointerCapture(e.pointerId);
      e.preventDefault();
    }

    function onRegionPointerMove(e) {
      if (!regionDragStart) return;
      var pos = getEditCanvasPos(e);
      regionDraftRect = normalizeRectFromPoints(regionDragStart, pos, editCanvas.width, editCanvas.height);
      drawEditCanvas();
    }

    function hitTestRegionAt(x, y) {
      for (var i = editDraft.bgRegions.length - 1; i >= 0; i--) {
        var r = editDraft.bgRegions[i];
        var rx = r.x * editScale, ry = r.y * editScale, rw = r.w * editScale, rh = r.h * editScale;
        if (x >= rx && x <= rx + rw && y >= ry && y <= ry + rh) return i;
      }
      return -1;
    }

    function onRegionPointerUp() {
      if (!regionDragStart) return;
      var rect = regionDraftRect;
      regionDragStart = null;
      regionDraftRect = null;

      if (rect && rect.w >= MIN_RECT_PX && rect.h >= MIN_RECT_PX) {
        editDraft.bgRegions.push({
          x: Math.round(rect.x / editScale),
          y: Math.round(rect.y / editScale),
          w: Math.round(rect.w / editScale),
          h: Math.round(rect.h / editScale),
        });
      } else if (rect) {
        var idx = hitTestRegionAt(rect.x, rect.y);
        if (idx !== -1) editDraft.bgRegions.splice(idx, 1);
      }
      scheduleEditPreview();
    }

    // ---- Chế độ "Vẽ": vẽ nét tự do lên ảnh. Lúc đang kéo chỉ vẽ nét trực tiếp cho mượt
    // (không chạy lại loang-từ-viền mỗi lần di chuột) — chỉ khi nhả chuột (commit nét) mới
    // tính lại preview xử lý nền. ----
    function onDrawPointerDown(e) {
      var pos = getEditCanvasPos(e);
      currentStroke = {
        points: [{ x: pos.x / editScale, y: pos.y / editScale }],
        color: editDrawColorPicker.getValue(),
        size: parseInt(editDrawSizeInput.value, 10) || DEFAULT_BRUSH_SIZE,
      };
      editCanvas.setPointerCapture(e.pointerId);
      e.preventDefault();
      drawEditCanvas();
    }

    function onDrawPointerMove(e) {
      if (!currentStroke) return;
      var pos = getEditCanvasPos(e);
      currentStroke.points.push({ x: pos.x / editScale, y: pos.y / editScale });
      drawEditCanvas();
    }

    function onDrawPointerUp() {
      if (!currentStroke) return;
      editDraft.brushStrokes.push(currentStroke);
      currentStroke = null;
      scheduleEditPreview();
    }

    // ---- Dispatcher: route theo editCanvasMode đang chọn ----
    function onEditPointerDown(e) {
      if (editCanvasMode === 'region') return onRegionPointerDown(e);
      if (editCanvasMode === 'draw') return onDrawPointerDown(e);
      return onCropPointerDown(e);
    }
    function onEditPointerMove(e) {
      if (editCanvasMode === 'region') return onRegionPointerMove(e);
      if (editCanvasMode === 'draw') return onDrawPointerMove(e);
      return onCropPointerMove(e);
    }
    function onEditPointerUp() {
      if (editCanvasMode === 'region') return onRegionPointerUp();
      if (editCanvasMode === 'draw') return onDrawPointerUp();
      return onCropPointerUp();
    }

    function applyEdit() {
      if (!editingItem || !editRect) return;
      var bbox = {
        x: Math.round(editRect.x / editScale),
        y: Math.round(editRect.y / editScale),
        w: Math.round(editRect.w / editScale),
        h: Math.round(editRect.h / editScale),
      };
      editingItem.manualOverride = true;
      editingItem.trimResult = manualCropImage(editingItem.img, bbox);
      editingItem.bgMode = editDraft.bgMode;
      editingItem.bgTolerance = editDraft.bgTolerance;
      editingItem.recolorColor = editDraft.recolorColor;
      editingItem.bgRegions = editDraft.bgRegions.map(function (r) { return { x: r.x, y: r.y, w: r.w, h: r.h }; });
      editingItem.brushStrokes = editDraft.brushStrokes.map(function (s) {
        return { points: s.points.map(function (p) { return { x: p.x, y: p.y }; }), color: s.color, size: s.size };
      });
      editingItem.result = computeItemResult(editingItem);
      editingItem.palette = extractPalette(editingItem.trimResult.canvas, editingItem.bgTolerance);
      closeEditModal();
      renderResults();
    }

    function resetEditToAuto() {
      if (!editingItem) return;
      editingItem.manualOverride = false;
      editingItem.trimResult = trimImage(editingItem.img, currentTolerance());
      editingItem.result = computeItemResult(editingItem);
      editingItem.palette = extractPalette(editingItem.trimResult.canvas, editingItem.bgTolerance);

      var bbox = editingItem.trimResult.bbox;
      editRect = {
        x: bbox.x * editScale,
        y: bbox.y * editScale,
        w: bbox.w * editScale,
        h: bbox.h * editScale,
      };
      rebuildEditBaseCanvas();
      drawEditCanvas();
      renderResults();
    }

    editCanvas.addEventListener('pointerdown', onEditPointerDown);
    editCanvas.addEventListener('pointermove', onEditPointerMove);
    editCanvas.addEventListener('pointerup', onEditPointerUp);
    editCanvas.addEventListener('pointercancel', onEditPointerUp);

    editModeButtons.forEach(function (btn) {
      btn.addEventListener('click', function () { setEditCanvasMode(btn.getAttribute('data-edit-mode')); });
    });
    if (editClearRegionsBtn) editClearRegionsBtn.addEventListener('click', function () {
      editDraft.bgRegions = [];
      scheduleEditPreview();
    });
    if (editUndoStrokeBtn) editUndoStrokeBtn.addEventListener('click', function () {
      editDraft.brushStrokes.pop();
      scheduleEditPreview();
    });
    if (editClearStrokesBtn) editClearStrokesBtn.addEventListener('click', function () {
      editDraft.brushStrokes = [];
      scheduleEditPreview();
    });
    if (editDrawSizeInput) editDrawSizeInput.addEventListener('input', function () {
      if (editDrawSizeValueEl) editDrawSizeValueEl.textContent = editDrawSizeInput.value;
    });

    editBackdrop.addEventListener('click', closeEditModal);
    editCloseBtn.addEventListener('click', closeEditModal);
    editCancelBtn.addEventListener('click', closeEditModal);
    editResetBtn.addEventListener('click', resetEditToAuto);
    editApplyBtn.addEventListener('click', applyEdit);

    resultList.addEventListener('click', function (e) {
      var zoomImg = e.target.closest('.it-result-thumb img');
      if (zoomImg) { openLightboxAt(zoomImg.getAttribute('data-item-id'), zoomImg.getAttribute('data-variant')); return; }
      var editBtn = e.target.closest('[data-edit-id]');
      if (editBtn) { openEditModal(itemById(editBtn.getAttribute('data-edit-id'))); return; }
      var downloadBtn = e.target.closest('[data-download-id]');
      if (downloadBtn) { var dlItem = itemById(downloadBtn.getAttribute('data-download-id')); if (dlItem) triggerDownload(dlItem); return; }
      var removeBtn = e.target.closest('[data-remove-id]');
      if (removeBtn) { removeItem(removeBtn.getAttribute('data-remove-id')); return; }
    });

    // Lightbox xem ảnh to: duyệt được qua TẤT CẢ ảnh Gốc + Sau khi xử lý của mọi ảnh
    // trong danh sách (không chỉ ảnh đang mở), theo đúng thứ tự hiển thị trong danh sách.
    var lightboxEntries = [];
    var lightboxIndex = -1;

    function buildLightboxEntries() {
      var entries = [];
      items.forEach(function (item) {
        if (item.error || !item.result) return;
        entries.push({
          itemId: item.id,
          variant: 'original',
          variantLabel: 'Gốc',
          src: item.objectUrl,
          filename: item.file.name,
          w: item.originalW,
          h: item.originalH,
        });
        entries.push({
          itemId: item.id,
          variant: 'processed',
          variantLabel: 'Sau khi xử lý',
          src: item.result.dataUrl,
          filename: item.file.name,
          w: item.result.trimmedW,
          h: item.result.trimmedH,
        });
      });
      return entries;
    }

    function renderLightboxEntry() {
      var entry = lightboxEntries[lightboxIndex];
      if (!entry) return;
      lightboxImg.src = entry.src;
      lightboxImg.alt = entry.filename + ' — ' + entry.variantLabel;
      lightboxCaption.textContent = entry.filename + ' · ' + entry.variantLabel + ' · ' +
        entry.w + '×' + entry.h + 'px  (' + (lightboxIndex + 1) + '/' + lightboxEntries.length + ')';
    }

    function openLightboxAt(itemId, variant) {
      lightboxEntries = buildLightboxEntries();
      if (!lightboxEntries.length) return;

      var idx = -1;
      for (var i = 0; i < lightboxEntries.length; i++) {
        if (lightboxEntries[i].itemId === itemId && lightboxEntries[i].variant === variant) { idx = i; break; }
      }
      lightboxIndex = idx >= 0 ? idx : 0;

      renderLightboxEntry();
      lightbox.hidden = false;
      document.body.classList.add('it-modal-open');
    }

    function lightboxNext() {
      if (!lightboxEntries.length) return;
      lightboxIndex = (lightboxIndex + 1) % lightboxEntries.length;
      renderLightboxEntry();
    }

    function lightboxPrev() {
      if (!lightboxEntries.length) return;
      lightboxIndex = (lightboxIndex - 1 + lightboxEntries.length) % lightboxEntries.length;
      renderLightboxEntry();
    }

    function closeLightbox() {
      lightbox.hidden = true;
      document.body.classList.remove('it-modal-open');
      lightboxImg.src = '';
      lightboxEntries = [];
      lightboxIndex = -1;
    }

    lightboxBackdrop.addEventListener('click', closeLightbox);
    lightboxCloseBtn.addEventListener('click', closeLightbox);
    lightboxPrevBtn.addEventListener('click', lightboxPrev);
    lightboxNextBtn.addEventListener('click', lightboxNext);

    document.addEventListener('keydown', function (e) {
      if (lightbox.hidden) return;
      if (e.key === 'ArrowLeft') lightboxPrev();
      else if (e.key === 'ArrowRight') lightboxNext();
      else if (e.key === 'Escape') closeLightbox();
    });

    resultList.addEventListener('change', function (e) {
      if (!e.target.classList.contains('it-result-checkbox')) return;
      var item = itemById(e.target.getAttribute('data-item-id'));
      if (item) item.selected = e.target.checked;
      refreshSelectionUI();
    });

    selectAllCheckbox.addEventListener('change', function () {
      var checked = selectAllCheckbox.checked;
      items.forEach(function (it) { it.selected = checked; });
      Array.prototype.forEach.call(resultList.querySelectorAll('.it-result-checkbox'), function (cb) { cb.checked = checked; });
      refreshSelectionUI();
    });

    function itemById(id) {
      for (var i = 0; i < items.length; i++) {
        if (items[i].id === id) return items[i];
      }
      return null;
    }

    // ---- Modal chọn ảnh để tải xuống ----
    function openDownloadModal() {
      var validItems = items.filter(function (it) { return it.result && !it.error; });
      if (!validItems.length) return;

      downloadList.innerHTML = validItems.map(function (item) {
        return '<li class="it-download-item">' +
          '<label>' +
            '<input type="checkbox" class="it-download-checkbox" data-item-id="' + item.id + '" checked>' +
            '<img src="' + item.result.dataUrl + '" alt="">' +
            '<span>' + escapeHtml(item.file.name) + '</span>' +
          '</label>' +
        '</li>';
      }).join('');

      downloadSelectAll.checked = true;
      updateDownloadCount();
      downloadModal.hidden = false;
      document.body.classList.add('it-modal-open');
    }

    function closeDownloadModal() {
      downloadModal.hidden = true;
      document.body.classList.remove('it-modal-open');
    }

    function updateDownloadCount() {
      var checked = downloadList.querySelectorAll('.it-download-checkbox:checked').length;
      downloadCountEl.textContent = String(checked);
    }

    downloadList.addEventListener('change', function (e) {
      if (e.target.classList.contains('it-download-checkbox')) updateDownloadCount();
    });

    downloadSelectAll.addEventListener('change', function () {
      var checked = downloadSelectAll.checked;
      Array.prototype.forEach.call(downloadList.querySelectorAll('.it-download-checkbox'), function (cb) {
        cb.checked = checked;
      });
      updateDownloadCount();
    });

    downloadConfirmBtn.addEventListener('click', function () {
      var ids = Array.prototype.map.call(downloadList.querySelectorAll('.it-download-checkbox:checked'), function (cb) {
        return cb.getAttribute('data-item-id');
      });
      var toDownload = items.filter(function (it) { return ids.indexOf(it.id) !== -1; });
      toDownload.forEach(function (item, index) {
        setTimeout(function () { triggerDownload(item); }, index * 350);
      });
      closeDownloadModal();
    });

    downloadBackdrop.addEventListener('click', closeDownloadModal);
    downloadCloseBtn.addEventListener('click', closeDownloadModal);
    downloadCancelBtn.addEventListener('click', closeDownloadModal);

    // ---- Wiring chung ----
    function addFiles(files) {
      files = Array.prototype.filter.call(files || [], function (f) { return f && f.type && f.type.indexOf('image/') === 0; });
      if (!files.length) return;
      var newItems = files.map(createItem);
      items = newItems.concat(items);
      if (newItems.length) activeId = newItems[0].id;
      // Hiện workspace ngay (filmstrip dùng ảnh gốc, stage báo "đang xử lý")
      // thay vì đợi xử lý xong toàn bộ mới hiển thị.
      renderResults();
      trimItems(newItems);
    }

    fileInput.addEventListener('change', function (e) {
      addFiles(e.target.files);
      fileInput.value = '';
    });

    // Ô chọn ảnh hỗ trợ thêm kéo-thả và dán (Ctrl+V) ảnh trực tiếp, giống khung
    // "Ảnh đính kèm" ở Góp ý — cùng đổ vào addFiles() như khi chọn file thường.
    function filesFromDataTransfer(dataTransfer) {
      if (!dataTransfer) return [];
      var fromItems = Array.prototype.filter.call(dataTransfer.items || [], function (item) {
        return item.kind === 'file' && item.type.indexOf('image/') === 0;
      }).map(function (item) { return item.getAsFile(); }).filter(Boolean);
      if (fromItems.length) return fromItems;
      return Array.prototype.filter.call(dataTransfer.files || [], function (f) { return f.type.indexOf('image/') === 0; });
    }

    function normalizePastedFile(file, index) {
      if (file.name && file.name !== 'image.png' && file.name !== 'blob') return file;
      var ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
      return new File([file], 'pasted-' + Date.now() + '-' + index + '.' + ext, { type: file.type });
    }

    if (uploadZone) {
      ['dragenter', 'dragover'].forEach(function (evt) {
        uploadZone.addEventListener(evt, function (e) {
          e.preventDefault();
          uploadZone.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        uploadZone.addEventListener(evt, function (e) {
          e.preventDefault();
          uploadZone.classList.remove('is-dragover');
        });
      });
      uploadZone.addEventListener('drop', function (e) {
        addFiles(filesFromDataTransfer(e.dataTransfer));
      });
    }

    document.addEventListener('paste', function (e) {
      var imageFiles = filesFromDataTransfer(e.clipboardData);
      if (!imageFiles.length) return;
      e.preventDefault();
      addFiles(imageFiles.map(normalizePastedFile));
      if (uploadZone) {
        uploadZone.classList.add('is-pasted');
        setTimeout(function () { uploadZone.classList.remove('is-pasted'); }, 600);
      }
    });

    toleranceInput.addEventListener('input', function () {
      toleranceValueEl.textContent = toleranceInput.value;
    });

    bgToleranceInput.addEventListener('input', function () {
      bgToleranceValueEl.textContent = bgToleranceInput.value;
    });

    resizeScaleInput.addEventListener('input', function () {
      resizeScaleValueEl.textContent = resizeScaleInput.value;
    });

    sharpenAmountInput.addEventListener('input', function () {
      sharpenAmountValueEl.textContent = sharpenAmountInput.value;
    });

    // Panel chính: các control này chỉ là "cài đặt chờ áp dụng" — không tự động đổi
    // ảnh nào cho tới khi bấm "Áp dụng cho ảnh đã chọn".
    bgModeRadios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (radio.checked) updateMainBgControlsVisibility();
      });
    });

    // Modal chỉnh tay: các control này chỉ đổi editDraft + preview riêng cho
    // editingItem, KHÔNG đụng tới ảnh khác trong danh sách.
    editBgModeRadios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (!radio.checked) return;
        editDraft.bgMode = radio.value;
        updateEditControlsVisibility();
        rebuildEditBaseCanvas();
        drawEditCanvas();
      });
    });
    editBgToleranceInput.addEventListener('input', function () {
      editDraft.bgTolerance = parseInt(editBgToleranceInput.value, 10) || 0;
      scheduleEditPreview();
    });
    editRecolorPicker.onPick(function (hex) {
      editDraft.recolorColor = hex;
      rebuildEditBaseCanvas();
      drawEditCanvas();
    });

    reprocessBtn.addEventListener('click', reprocessSelected);
    applyBgBtn.addEventListener('click', applyBgSettingsToSelected);
    applyResizeBtn.addEventListener('click', applyResizeSettingsToSelected);
    deleteSelectedBtn.addEventListener('click', deleteSelected);
    downloadAllBtn.addEventListener('click', openDownloadModal);
    clearAllBtn.addEventListener('click', clearAll);

    // ---- Workspace: render + tương tác (chỉ chạy khi trang dùng layout mới) ----
    function getActiveItem() { return itemById(activeId); }

    function ensureActiveItem() {
      if (!items.length) { activeId = null; return; }
      if (!itemById(activeId)) activeId = items[0].id;
    }

    // Đồng bộ giá trị control trong menu công cụ theo ảnh đang chọn — mỗi ảnh nhớ
    // riêng bgMode/bgTolerance/màu/kích thước/độ nét của nó.
    function syncToolsToActive() {
      var item = getActiveItem();
      if (!item) return;
      bgModeRadios.forEach(function (r) { r.checked = (r.value === item.bgMode); });
      bgToleranceInput.value = item.bgTolerance;
      bgToleranceValueEl.textContent = String(item.bgTolerance);
      recolorPicker.setValue(item.recolorColor);
      resizeScaleInput.value = item.resizePercent;
      resizeScaleValueEl.textContent = String(item.resizePercent);
      sharpenAmountInput.value = item.sharpenAmount;
      sharpenAmountValueEl.textContent = String(item.sharpenAmount);
      updateMainBgControlsVisibility();
    }

    function buildStageMetaHtml(item) {
      if (item.error || !item.result) {
        var pendingLabel = item.error ? 'Không đọc được ảnh' : 'Đang xử lý…';
        return '<span class="it-stage-filename">' + escapeHtml(item.file.name) + '</span>' +
          '<span class="it-result-badge it-result-badge--unchanged">' + pendingLabel + '</span>';
      }
      var result = item.result;
      var percent = result.trimmed
        ? Math.round((1 - (result.trimmedW * result.trimmedH) / (result.originalW * result.originalH)) * 100)
        : 0;
      var badgeClass = 'it-result-badge' + (result.trimmed ? '' : ' it-result-badge--unchanged');
      var badgeText = result.manual
        ? 'Đã chỉnh tay'
        : (result.trimmed ? ('Cắt bớt ' + percent + '%') : 'Không đổi');
      return '<span class="it-stage-filename" title="' + escapeHtml(item.file.name) + '">' + escapeHtml(item.file.name) + '</span>' +
        '<span class="it-stage-dims">' + result.originalW + '×' + result.originalH + ' → ' + result.trimmedW + '×' + result.trimmedH + '</span>' +
        '<span class="' + badgeClass + '">' + badgeText + '</span>';
    }

    function renderStage() {
      if (!workspace) return;
      var item = getActiveItem();
      if (!item) return;

      var broken = item.error || !item.result;
      stageError.hidden = !broken;
      stageError.textContent = item.error ? 'Không đọc được ảnh này.' : 'Đang xử lý ảnh…';
      stageImg.hidden = broken;
      stageMeta.innerHTML = buildStageMetaHtml(item);

      if (manualEditBtn) manualEditBtn.disabled = broken;
      if (downloadActiveBtn) downloadActiveBtn.disabled = broken;
      if (compareBtn) compareBtn.disabled = broken;
      if (zoomBtn) zoomBtn.disabled = broken;
      if (broken) { stageFlag.hidden = true; return; }

      stageImg.src = isComparing ? item.objectUrl : item.result.dataUrl;
      stageFlag.hidden = !isComparing;
    }

    function renderFilmstrip() {
      if (!filmstrip) return;
      filmstrip.innerHTML = items.map(function (item) {
        var src = (item.result && !item.error) ? item.result.dataUrl : item.objectUrl;
        var cls = 'it-film-thumb' +
          (item.id === activeId ? ' is-active' : '') +
          (item.error ? ' is-error' : '');
        return '<div class="' + cls + '" data-film-id="' + item.id + '" role="button" tabindex="0" title="' + escapeHtml(item.file.name) + '">' +
          '<img src="' + src + '" alt="">' +
          '<button type="button" class="it-film-x" data-film-remove="' + item.id + '" title="Xóa ảnh này" aria-label="Xóa ảnh này">×</button>' +
        '</div>';
      }).join('');

      var activeThumb = filmstrip.querySelector('.it-film-thumb.is-active');
      if (activeThumb && activeThumb.scrollIntoView) {
        activeThumb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      }
    }

    function renderWorkspace() {
      if (!workspace) return;
      var has = items.length > 0;
      workspace.hidden = !has;
      if (emptyState) emptyState.hidden = has;
      if (!has) { activeId = null; return; }
      ensureActiveItem();
      syncToolsToActive();
      renderFilmstrip();
      renderStage();
    }

    function setActiveItem(id) {
      if (id === activeId) return;
      activeId = id;
      isComparing = false;
      syncToolsToActive();
      renderFilmstrip();
      renderStage();
    }

    function debounce(fn, ms) {
      var t = null;
      return function () {
        if (t) clearTimeout(t);
        t = setTimeout(fn, ms);
      };
    }

    // Tự áp dụng cho ảnh ĐANG CHỌN khi kéo slider/đổi lựa chọn — thay cho các nút
    // "Áp dụng cho ảnh đã chọn" của giao diện cũ (logic batch vẫn giữ, chỉ ẩn HTML).
    function retrimActive() {
      var item = getActiveItem();
      if (!item || item.error || !item.loaded) return;
      item.manualOverride = false;
      item.trimResult = trimImage(item.img, currentTolerance());
      item.result = computeItemResult(item);
      item.palette = extractPalette(item.trimResult.canvas, item.bgTolerance);
      renderResults();
    }

    function applyBgToActive() {
      var item = getActiveItem();
      if (!item || item.error || !item.trimResult) return;
      item.bgMode = getPendingBgMode();
      item.bgTolerance = parseInt(bgToleranceInput.value, 10) || 0;
      item.recolorColor = recolorPicker.getValue();
      item.result = computeItemResult(item);
      item.palette = extractPalette(item.trimResult.canvas, item.bgTolerance);
      renderResults();
    }

    function applyResizeToActive() {
      var item = getActiveItem();
      if (!item || item.error || !item.trimResult) return;
      item.resizePercent = parseInt(resizeScaleInput.value, 10) || 100;
      item.sharpenAmount = parseInt(sharpenAmountInput.value, 10) || 0;
      item.result = computeItemResult(item);
      renderResults();
    }

    if (workspace) {
      var retrimActiveDebounced = debounce(retrimActive, 350);
      var applyBgToActiveDebounced = debounce(applyBgToActive, 350);
      var applyResizeToActiveDebounced = debounce(applyResizeToActive, 350);

      toleranceInput.addEventListener('input', retrimActiveDebounced);
      bgToleranceInput.addEventListener('input', applyBgToActiveDebounced);
      resizeScaleInput.addEventListener('input', applyResizeToActiveDebounced);
      sharpenAmountInput.addEventListener('input', applyResizeToActiveDebounced);
      bgModeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () { if (radio.checked) applyBgToActive(); });
      });
      recolorPicker.onPick(applyBgToActive);

      filmstrip.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('[data-film-remove]');
        if (removeBtn) { removeItem(removeBtn.getAttribute('data-film-remove')); return; }
        var thumb = e.target.closest('[data-film-id]');
        if (thumb) setActiveItem(thumb.getAttribute('data-film-id'));
      });
      filmstrip.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var thumb = e.target.closest('[data-film-id]');
        if (thumb) { e.preventDefault(); setActiveItem(thumb.getAttribute('data-film-id')); }
      });

      if (addMoreBtn) addMoreBtn.addEventListener('click', function () { fileInput.click(); });
      if (manualEditBtn) manualEditBtn.addEventListener('click', function () {
        var item = getActiveItem();
        if (item && item.trimResult) openEditModal(item);
      });
      if (downloadActiveBtn) downloadActiveBtn.addEventListener('click', function () {
        var item = getActiveItem();
        if (item && item.result) triggerDownload(item);
      });
      if (deleteActiveBtn) deleteActiveBtn.addEventListener('click', function () {
        if (activeId) removeItem(activeId);
      });

      if (zoomBtn) zoomBtn.addEventListener('click', function () {
        if (activeId) openLightboxAt(activeId, 'processed');
      });
      if (stageImg) stageImg.addEventListener('click', function () {
        if (activeId) openLightboxAt(activeId, isComparing ? 'original' : 'processed');
      });

      // "Giữ để so với gốc": nhấn giữ hiển thị ảnh gốc, thả ra quay lại ảnh đã xử lý.
      if (compareBtn) {
        compareBtn.addEventListener('pointerdown', function (e) {
          e.preventDefault();
          isComparing = true;
          renderStage();
        });
        ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (evt) {
          compareBtn.addEventListener(evt, function () {
            if (!isComparing) return;
            isComparing = false;
            renderStage();
          });
        });
      }

      // Kéo-thả ảnh trực tiếp vào vùng xem ảnh (ngoài ô upload ban đầu).
      if (stage) {
        ['dragenter', 'dragover'].forEach(function (evt) {
          stage.addEventListener(evt, function (e) {
            e.preventDefault();
            stage.classList.add('is-dragover');
          });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
          stage.addEventListener(evt, function (e) {
            e.preventDefault();
            stage.classList.remove('is-dragover');
          });
        });
        stage.addEventListener('drop', function (e) {
          addFiles(filesFromDataTransfer(e.dataTransfer));
        });
      }

      // Sidebar: accordion nhóm công cụ + thu gọn thành thanh icon.
      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
          sidebar.classList.toggle('is-collapsed');
        });
      }
      if (toolGroups) {
        toolGroups.addEventListener('click', function (e) {
          var head = e.target.closest('[data-tool-toggle]');
          if (!head) return;
          var group = head.closest('.it-tool-group');
          if (!group) return;

          // Đang thu gọn: bấm icon nào thì mở rộng sidebar và mở đúng nhóm đó.
          if (sidebar.classList.contains('is-collapsed')) {
            sidebar.classList.remove('is-collapsed');
            Array.prototype.forEach.call(toolGroups.querySelectorAll('.it-tool-group'), function (g) {
              g.classList.toggle('is-open', g === group);
            });
            return;
          }

          var wasOpen = group.classList.contains('is-open');
          Array.prototype.forEach.call(toolGroups.querySelectorAll('.it-tool-group'), function (g) {
            g.classList.remove('is-open');
          });
          if (!wasOpen) group.classList.add('is-open');
        });
      }
    }

    if (editDrawColorPicker) editDrawColorPicker.setValue(DEFAULT_BRUSH_COLOR);

    updateMainBgControlsVisibility();
    refreshSelectionUI();
  }

  window.ImageTrimmer = {
    init: init,
    _internal: {
      detectTrimBounds: detectTrimBounds,
      trimImage: trimImage,
      computeBorderBackgroundMask: computeBorderBackgroundMask,
      computeBackgroundMask: computeBackgroundMask,
      paintStrokesOnCanvas: paintStrokesOnCanvas,
      paintBrushStrokes: paintBrushStrokes,
      applyBackgroundMode: applyBackgroundMode,
      applyResize: applyResize,
      applySharpen: applySharpen,
      boxBlur3x3: boxBlur3x3,
      computeItemResult: computeItemResult,
      extractPalette: extractPalette,
      hexToRgb: hexToRgb,
      hitTestEdge: hitTestEdge,
      computeRectForDrag: computeRectForDrag,
      normalizeRectFromPoints: normalizeRectFromPoints,
      loadCustomColors: loadCustomColors,
      saveCustomColors: saveCustomColors,
      createColorPickerRegistry: createColorPickerRegistry,
    },
  };
})();
