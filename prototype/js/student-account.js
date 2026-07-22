/**
 * Tài khoản học sinh: xem hồ sơ, đổi tên hiển thị / ảnh đại diện / mật khẩu.
 *
 * Mọi thay đổi đều bắt nhập lại mật khẩu hiện tại — nhập sai 5 lần thì backend
 * khóa tài khoản, lúc đó ta đá học sinh về màn đăng nhập.
 */
window.StudentAccount = (function () {
  const state = { profile: null, loaded: false };

  const $ = (id) => document.getElementById(id);

  function toast(message, icon) {
    if (typeof window.showCartoonToast === 'function') {
      window.showCartoonToast(message, icon || '✅');
    } else {
      alert(message);
    }
  }

  function isLoggedIn() {
    return !!state.profile;
  }

  /** Backend trả 403 khi tài khoản bị khóa giữa chừng → về màn đăng nhập. */
  function handleError(err) {
    const message = (err && err.message) || 'Có lỗi xảy ra, thử lại nhé.';
    if (/khóa|ngừng sử dụng/i.test(message)) {
      state.profile = null;
      renderHomeChip();
      toast(message, '🔒');
      setTimeout(() => { window.location.href = HTDApi.studentLoginUrl(); }, 1500);
      return;
    }
    toast(message, '⚠️');
  }

  async function refresh() {
    state.profile = await HTDApi.studentMe();
    state.loaded = true;
    renderHomeChip();
    return state.profile;
  }

  /** Chip ở góc màn hình chính: tên + avatar, hoặc nút đăng nhập. */
  function renderHomeChip() {
    const chip = $('homeAccountChip');
    if (!chip) return;

    if (!isLoggedIn()) {
      chip.innerHTML =
        `<button type="button" class="account-chip account-chip--guest" onclick="StudentAccount.goLogin()">`
        + `<span class="account-chip__avatar">👤</span>`
        + `<span class="account-chip__name">Đăng nhập</span>`
        + `</button>`;
      return;
    }

    const p = state.profile;
    const avatar = p.avatar_url
      ? `<img class="account-chip__avatar" src="${p.avatar_url}" alt="">`
      : `<span class="account-chip__avatar">${p.initials || '?'}</span>`;

    chip.innerHTML =
      `<button type="button" class="account-chip" onclick="StudentAccount.open()">`
      + avatar
      + `<span class="account-chip__name">${escapeHtml(p.display_name)}</span>`
      + `</button>`;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function render() {
    if (!isLoggedIn()) return;
    const p = state.profile;

    $('accDisplayName').value = p.display_name || '';
    $('accUsername').textContent = p.username || '—';
    $('accClass').textContent = p.class_name || 'Chưa xếp lớp';
    $('accCode').textContent = p.student_code || '—';

    const img = $('accAvatarImg');
    const initials = $('accAvatarInitials');
    if (p.avatar_url) {
      img.src = p.avatar_url;
      img.style.display = 'block';
      initials.style.display = 'none';
    } else {
      img.style.display = 'none';
      initials.style.display = 'block';
      initials.textContent = p.initials || '?';
    }
  }

  async function open() {
    if (!state.loaded) await refresh();
    if (!isLoggedIn()) {
      goLogin();
      return;
    }
    if (typeof window.showScreen === 'function') window.showScreen('account');
    render();
  }

  function goLogin() {
    window.location.href = HTDApi.studentLoginUrl();
  }

  async function saveName() {
    const displayName = $('accDisplayName').value.trim();
    const currentPassword = $('accNamePassword').value;

    if (!displayName) return toast('Tên hiển thị không được để trống.', '⚠️');
    if (!currentPassword) return toast('Nhập mật khẩu hiện tại để xác nhận.', '🔑');

    try {
      state.profile = await HTDApi.studentUpdateProfile({ displayName, currentPassword });
      $('accNamePassword').value = '';
      render();
      renderHomeChip();
      toast('Đã đổi tên hiển thị.', '✅');
    } catch (err) {
      handleError(err);
    }
  }

  async function savePassword() {
    const currentPassword = $('accCurrentPassword').value;
    const password = $('accNewPassword').value;
    const passwordConfirmation = $('accNewPassword2').value;

    if (!currentPassword || !password) return toast('Nhập đủ mật khẩu cũ và mới.', '🔑');
    if (password.length < 6) return toast('Mật khẩu mới cần ít nhất 6 ký tự.', '⚠️');
    if (password !== passwordConfirmation) return toast('Hai ô mật khẩu mới chưa khớp.', '⚠️');

    try {
      state.profile = await HTDApi.studentUpdatePassword({ currentPassword, password, passwordConfirmation });
      ['accCurrentPassword', 'accNewPassword', 'accNewPassword2'].forEach((id) => { $(id).value = ''; });
      toast('Đã đổi mật khẩu.', '🔑');
    } catch (err) {
      handleError(err);
    }
  }

  function pickAvatar() {
    $('accAvatarInput')?.click();
  }

  async function onAvatarSelected(event) {
    const file = event.target.files && event.target.files[0];
    event.target.value = '';
    if (!file) return;

    const currentPassword = $('accAvatarPassword').value;
    if (!currentPassword) return toast('Nhập mật khẩu hiện tại trước khi đổi ảnh.', '🔑');

    try {
      state.profile = await HTDApi.studentUploadAvatar({ file, currentPassword });
      $('accAvatarPassword').value = '';
      render();
      renderHomeChip();
      toast('Đã đổi ảnh đại diện.', '🖼️');
    } catch (err) {
      handleError(err);
    }
  }

  async function logout() {
    try {
      await HTDApi.studentLogout();
    } finally {
      state.profile = null;
      goLogin();
    }
  }

  document.addEventListener('DOMContentLoaded', () => { refresh(); });

  return {
    refresh,
    open,
    render,
    renderHomeChip,
    goLogin,
    saveName,
    savePassword,
    pickAvatar,
    onAvatarSelected,
    logout,
    isLoggedIn,
    get profile() { return state.profile; },
  };
})();
