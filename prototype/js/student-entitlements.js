/**
 * Quyền truy cập tính năng của học sinh.
 *
 * Chỉ lo phần HIỂN THỊ: gắn tag Pro, hiện banner hạn dùng, chặn mở tính năng
 * bị khóa hẳn. Giới hạn thật do server quyết định trong từng endpoint gameplay —
 * sửa được ở client nên không bao giờ tin client.
 */
window.StudentEntitlements = (function () {
  const state = { features: {}, banner: null, loaded: false };

  // Map từ nút ở màn hình chính sang khóa tính năng trong FeatureRegistry.
  const HOME_FEATURE_MAP = {
    play: 'duck_race',
    elements: 'elements',
    balance: 'balance',
    quiz: 'quiz',
  };

  function toast(message, icon) {
    if (typeof window.showCartoonToast === 'function') {
      window.showCartoonToast(message, icon || 'ℹ️');
    } else {
      alert(message);
    }
  }

  async function load() {
    try {
      const data = await HTDApi.studentEntitlements();
      state.features = (data && data.features) || {};
      state.banner = (data && data.pro_banner) || null;
    } catch {
      // Chưa đăng nhập hoặc lỗi mạng: coi như chỉ có mức miễn phí, không chặn gì.
      state.features = {};
      state.banner = null;
    }
    state.loaded = true;
    renderHome();
    return state.features;
  }

  /** Quyền của một tính năng; trả về null nếu chưa biết (chưa đăng nhập). */
  function access(featureKey) {
    return state.features[featureKey] || null;
  }

  function scopeOf(featureKey) {
    const entry = access(featureKey);
    return entry ? entry.scope || {} : null;
  }

  function isPro(featureKey) {
    const entry = access(featureKey);
    return !!(entry && entry.is_pro);
  }

  function isBlocked(featureKey) {
    const entry = access(featureKey);
    return !!(entry && entry.access_level === 'none');
  }

  /**
   * Gọi trước khi mở một tính năng. Trả về false nếu bị khóa hẳn (đã hiện
   * thông báo liên hệ giáo viên).
   */
  function guard(homeFeature) {
    const key = HOME_FEATURE_MAP[homeFeature] || homeFeature;
    if (!isBlocked(key)) return true;

    const entry = access(key);
    toast(`«${(entry && entry.name) || key}» chưa được mở cho tài khoản của em. Hãy liên hệ thầy cô để mở khóa nhé!`, '🔒');
    return false;
  }

  /** Gắn tag Pro lên các nút chưa được mở full + vẽ banner hạn dùng. */
  function renderHome() {
    Object.keys(HOME_FEATURE_MAP).forEach((homeFeature) => {
      const key = HOME_FEATURE_MAP[homeFeature];
      const button = document.querySelector(`.home-menu-item.hm-${homeFeature}`);
      if (!button) return;

      button.querySelector('.home-menu-pro')?.remove();

      const entry = access(key);
      // Chưa đăng nhập thì không gắn tag — tránh doạ khách vãng lai.
      if (!entry || entry.is_pro) return;

      const tag = document.createElement('span');
      tag.className = 'home-menu-pro';
      tag.textContent = entry.access_level === 'none' ? '🔒' : 'PRO';
      button.appendChild(tag);
    });

    renderBanner();
  }

  function renderBanner() {
    const slot = document.getElementById('homeProBanner');
    if (!slot) return;

    if (!state.banner) {
      slot.innerHTML = '';
      slot.hidden = true;
      return;
    }

    const days = state.banner.days;
    slot.hidden = false;
    slot.innerHTML =
      `<div class="pro-banner">`
      + `<span class="pro-banner__icon">⭐</span>`
      + `<span>Tài khoản Pro «${escapeHtml(state.banner.feature_name)}» còn <strong>${days} ngày</strong></span>`
      + `</div>`;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  document.addEventListener('DOMContentLoaded', () => { load(); });

  return { load, access, scopeOf, isPro, isBlocked, guard, renderHome };
})();
