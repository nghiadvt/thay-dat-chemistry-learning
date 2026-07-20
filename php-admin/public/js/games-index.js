/**
 * Trang danh sách game — modal thao tác nhanh quiz của một game:
 * bấm vào "N quiz" trên card để mở, trong modal có thể xóa quiz (xóa mềm)
 * hoặc chuyển quiz sang game khác, nhằm gỡ hết quiz để xóa được game.
 */
(function () {
  const modal = document.getElementById('gameQuizModal');
  if (!modal) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const body = modal.querySelector('[data-quiz-panel-body]');
  const title = modal.querySelector('#gameQuizModalTitle');
  const manageLink = modal.querySelector('[data-quiz-panel-manage-link]');

  const panelUrl = (gameId) => modal.dataset.panelUrlTemplate.replace('__ID__', gameId);
  const deleteUrl = (quizId) => modal.dataset.quizDeleteUrlTemplate.replace('__ID__', quizId);
  const moveUrl = (quizId) => modal.dataset.quizMoveUrlTemplate.replace('__ID__', quizId);

  let currentGameId = null;
  let lastFocused = null;

  function updateCardCount(gameId, count) {
    const card = document.querySelector(`.game-card[data-game-id="${gameId}"]`);
    const el = card?.querySelector('[data-quiz-count]');
    if (el) el.textContent = count;
  }

  async function loadPanel() {
    body.innerHTML = '<p class="gqp-loading">Đang tải…</p>';
    try {
      const res = await fetch(panelUrl(currentGameId), {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error();
      const data = await res.json();
      body.innerHTML = data.html;
      updateCardCount(currentGameId, data.count);
    } catch {
      body.innerHTML = '<p class="gqp-empty">Không tải được danh sách quiz. Thử lại sau.</p>';
    }
  }

  function open(gameId, gameName) {
    currentGameId = gameId;
    lastFocused = document.activeElement;
    title.textContent = `Quiz của game «${gameName}»`;
    manageLink.href = `${modal.dataset.quizzesIndexUrl}?game_id=${gameId}`;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    loadPanel();
  }

  function close() {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    currentGameId = null;
    lastFocused?.focus?.();
    lastFocused = null;
  }

  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-quiz-panel-open]');
    if (opener) {
      e.preventDefault();
      open(opener.dataset.gameId, opener.dataset.gameName);
    }
  });

  modal.addEventListener('click', (e) => {
    if (e.target.closest('[data-close-quiz-panel]')) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) close();
  });

  async function request(url, method, payload) {
    const res = await fetch(url, {
      method,
      headers: {
        'X-CSRF-TOKEN': csrf,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: payload ? JSON.stringify(payload) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra, thử lại sau.');
    return data;
  }

  async function handleDelete(row, quizName) {
    const confirmed = await window.AdminConfirm.show({
      title: 'Xóa quiz',
      message: `Xóa quiz «${quizName}» khỏi game? Quiz sẽ được lưu trữ (xóa mềm) kèm tên game hiện tại.`,
      confirmText: 'Xóa',
      danger: true,
    });
    if (!confirmed) return;

    try {
      const data = await request(deleteUrl(row.dataset.quizId), 'DELETE');
      window.AdminToast?.show(data.message || 'Đã xóa quiz.', 'success');
      updateCardCount(data.game.id, data.game.quiz_count);
      loadPanel();
    } catch (err) {
      window.AdminToast?.show(err.message, 'error');
    }
  }

  async function handleMove(row, quizName) {
    const select = row.querySelector('[data-quiz-move-select]');
    const toGameId = select?.value;
    if (!toGameId) {
      window.AdminToast?.show('Chọn game muốn chuyển quiz sang trước đã.', 'warning');
      return;
    }
    const toGameName = select.options[select.selectedIndex].textContent;

    const confirmed = await window.AdminConfirm.show({
      title: 'Chuyển quiz',
      message: `Chuyển quiz «${quizName}» sang game «${toGameName}»?`,
      confirmText: 'Chuyển',
    });
    if (!confirmed) return;

    try {
      const data = await request(moveUrl(row.dataset.quizId), 'PATCH', { game_id: Number(toGameId) });
      window.AdminToast?.show(data.message || 'Đã chuyển quiz.', 'success');
      updateCardCount(data.from.id, data.from.quiz_count);
      updateCardCount(data.to.id, data.to.quiz_count);
      loadPanel();
    } catch (err) {
      window.AdminToast?.show(err.message, 'error');
    }
  }

  body.addEventListener('click', (e) => {
    const row = e.target.closest('.gqp-row');
    if (!row) return;

    const deleteBtn = e.target.closest('[data-quiz-delete]');
    if (deleteBtn) {
      handleDelete(row, deleteBtn.dataset.quizName);
      return;
    }

    const moveBtn = e.target.closest('[data-quiz-move]');
    if (moveBtn) handleMove(row, moveBtn.dataset.quizName);
  });
})();
