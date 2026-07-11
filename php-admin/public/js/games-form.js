/**
 * games-form — toggle panel cấu hình Đua vịt theo kiểu chơi được chọn.
 * (Thay inline <script> trong games/form.blade.php.)
 */
(function () {
  const select = document.getElementById('play_mode_id');
  const panel = document.getElementById('duckRaceConfig');
  if (!panel) return;

  const duckModeId = Number(panel.dataset.duckModeId);

  function sync() {
    if (!select) return;
    panel.classList.toggle('drc-hidden', Number(select.value) !== duckModeId);
  }

  select?.addEventListener('change', sync);
  sync();

  if (window.DuckRaceGameConfig) {
    DuckRaceGameConfig.init(panel);
  }
})();
