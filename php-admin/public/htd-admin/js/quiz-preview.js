/**
 * Quiz & question preview — teacher barem vs student view.
 */
window.HTDQuizPreview = (function () {
  'use strict';

  const MODAL_ID = 'htdQuizPreviewModal';
  let mode = 'teacher';
  let lastPayload = null;

  function typeLabel(answerType) {
    return answerType === 'mc' ? 'Trắc nghiệm' : 'Tự luận';
  }

  function optionLetter(index) {
    return String.fromCharCode(65 + index);
  }

  function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
  }

  function ensureModal() {
    if (document.getElementById(MODAL_ID)) return;

    const modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.className = 'htd-preview-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<button type="button" class="htd-preview-backdrop" aria-label="Đóng"></button>' +
      '<div class="htd-preview-dialog" role="dialog" aria-modal="true">' +
      '  <div class="htd-preview-header">' +
      '    <div>' +
      '      <h3 id="htdPreviewTitle">Xem trước</h3>' +
      '      <p id="htdPreviewSubtitle"></p>' +
      '    </div>' +
      '    <button type="button" class="htd-preview-close" aria-label="Đóng">&times;</button>' +
      '  </div>' +
      '  <div class="htd-preview-mode-tabs">' +
      '    <button type="button" class="htd-preview-mode-tab is-active" data-mode="teacher">Giáo viên (barem)</button>' +
      '    <button type="button" class="htd-preview-mode-tab" data-mode="student">Học sinh</button>' +
      '  </div>' +
      '  <div id="htdPreviewBody" class="htd-preview-body"></div>' +
      '</div>';

    document.body.appendChild(modal);

    modal.querySelector('.htd-preview-backdrop').addEventListener('click', close);
    modal.querySelector('.htd-preview-close').addEventListener('click', close);

    modal.querySelectorAll('.htd-preview-mode-tab').forEach((btn) => {
      btn.addEventListener('click', () => {
        setMode(btn.dataset.mode);
        if (lastPayload) render(lastPayload);
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal && !modal.hidden) close();
    });
  }

  function setMode(next) {
    mode = next === 'student' ? 'student' : 'teacher';
    const modal = document.getElementById(MODAL_ID);
    if (!modal) return;
    modal.querySelectorAll('.htd-preview-mode-tab').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.mode === mode);
    });
  }

  function openModal() {
    const modal = document.getElementById(MODAL_ID);
    if (modal) modal.hidden = false;
  }

  function close() {
    const modal = document.getElementById(MODAL_ID);
    if (modal) modal.hidden = true;
  }

  function setLoading(message) {
    const body = document.getElementById('htdPreviewBody');
    if (body) {
      body.innerHTML = '<div class="htd-preview-loading">' + escapeHtml(message || 'Đang tải…') + '</div>';
    }
  }

  function renderMcOptions(question, isTeacher) {
    const options = Array.isArray(question.options) ? question.options : [];
    if (!options.length) {
      return '<p class="htd-preview-empty">Chưa có đáp án.</p>';
    }

    const correctIndex = Number(question.correct_index) || 0;
    return (
      '<div class="htd-preview-mc-list">' +
      options
        .map((opt, i) => {
          const correct = isTeacher && i === correctIndex;
          return (
            '<div class="htd-preview-mc-opt' + (correct ? ' is-correct' : '') + '">' +
            '<span class="opt-label">' + optionLetter(i) + '</span>' +
            '<span class="opt-text">' + escapeHtml(opt) + (correct ? ' ✓' : '') + '</span>' +
            '</div>'
          );
        })
        .join('') +
      '</div>'
    );
  }

  function renderTeacherBarem(question) {
    let html = '<div class="htd-preview-barem">';

    if (question.answer_type === 'mc') {
      html +=
        '<div class="htd-preview-barem-block"><strong>Đáp án đúng:</strong> ' +
        escapeHtml(optionLetter(question.correct_index || 0)) +
        (question.options?.[question.correct_index] ? ' — ' + escapeHtml(question.options[question.correct_index]) : '') +
        '</div>';
    }

    if (question.answer_type === 'essay' && question.correct_answer_normalized) {
      html +=
        '<div class="htd-preview-barem-block"><strong>Đáp án mẫu:</strong> ' +
        escapeHtml(question.correct_answer_normalized) +
        '</div>';
    }

    if (question.explanation) {
      html +=
        '<h4>Giải thích</h4>' +
        '<div class="htd-preview-explanation">' + question.explanation + '</div>';
    }

    html += '</div>';
    return html;
  }

  function renderQuestion(question, index, isTeacher) {
    const num = question.sort_order != null ? question.sort_order : index + 1;
    const points = question.points != null ? question.points : 1;
    const time = question.time_limit_seconds != null ? question.time_limit_seconds : 30;

    let body = '';
    if (question.answer_type === 'mc') {
      body = renderMcOptions(question, isTeacher);
    } else {
      body =
        '<textarea class="htd-preview-essay-input" readonly placeholder="Học sinh nhập câu trả lời tại đây…"></textarea>';
    }

    const barem = isTeacher ? renderTeacherBarem(question) : '';

    return (
      '<article class="htd-preview-question">' +
      '<div class="htd-preview-q-head">' +
      '<span class="htd-preview-q-num">Câu ' + num + '</span>' +
      '<span class="htd-preview-q-badge">' + typeLabel(question.answer_type) + '</span>' +
      '<span class="htd-preview-q-meta">' + points + ' điểm · ' + time + 's</span>' +
      '</div>' +
      '<div class="htd-preview-q-content">' + (question.content || '<em>Chưa có nội dung</em>') + '</div>' +
      body +
      barem +
      '</article>'
    );
  }

  function render(payload) {
    lastPayload = payload;
    const titleEl = document.getElementById('htdPreviewTitle');
    const subEl = document.getElementById('htdPreviewSubtitle');
    const bodyEl = document.getElementById('htdPreviewBody');

    if (titleEl) titleEl.textContent = payload.title || 'Xem trước';
    if (subEl) {
      subEl.textContent = payload.subtitle || '';
      subEl.hidden = !payload.subtitle;
    }

    const questions = payload.questions || [];
    const isTeacher = mode === 'teacher';

    if (!questions.length) {
      if (bodyEl) bodyEl.innerHTML = '<div class="htd-preview-empty">Chưa có câu hỏi nào.</div>';
      return;
    }

    if (bodyEl) {
      bodyEl.innerHTML = questions
        .map((q, i) => renderQuestion(q, i, isTeacher))
        .join('');
    }
  }

  async function openQuiz(quizId, quizName) {
    ensureModal();
    setMode('teacher');
    setLoading('Đang tải câu hỏi…');
    document.getElementById('htdPreviewTitle').textContent = 'Xem trước quiz';
    openModal();

    try {
      const res = await fetch('/api/quizzes/' + quizId + '/questions', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        throw new Error(json.error || 'Không tải được câu hỏi');
      }
      const questions = (json.data?.questions || []).sort(
        (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)
      );
      render({
        title: quizName || 'Quiz',
        subtitle: questions.length + ' câu hỏi · Barem đầy đủ cho giáo viên',
        questions,
      });
    } catch (err) {
      const body = document.getElementById('htdPreviewBody');
      if (body) {
        body.innerHTML = '<div class="htd-preview-empty">' + escapeHtml(err.message) + '</div>';
      }
    }
  }

  function openQuestions(options) {
    ensureModal();
    if (options.mode) setMode(options.mode);
    render({
      title: options.title || 'Xem trước',
      subtitle: options.subtitle || '',
      questions: options.questions || [],
    });
    openModal();
  }

  function bindQuizButtons(root) {
    (root || document).querySelectorAll('[data-quiz-preview]').forEach((btn) => {
      if (btn.dataset.previewBound) return;
      btn.dataset.previewBound = '1';
      btn.addEventListener('click', () => {
        openQuiz(btn.dataset.quizPreview, btn.dataset.quizName || 'Quiz');
      });
    });
  }

  return {
    openQuiz,
    openQuestions,
    bindQuizButtons,
    close,
    setMode,
  };
})();

document.addEventListener('DOMContentLoaded', () => window.HTDQuizPreview.bindQuizButtons());
