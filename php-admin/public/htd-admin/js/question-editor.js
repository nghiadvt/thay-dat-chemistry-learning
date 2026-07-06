(function () {
  'use strict';

  const MAX_OPTIONS = 6;
  const MIN_OPTIONS = 2;

  const form = document.getElementById('questionForm');
  if (!form) return;

  const boot = window.QUESTION_EDITOR_BOOT || {};
  const typeSelect = document.getElementById('answer_type');
  const optionsList = document.getElementById('optionsList');
  const addOptionBtn = document.getElementById('btnAddOption');
  const correctSelect = document.getElementById('correct_index_select');
  const previewBtn = document.getElementById('btnPreview');
  const explanationToggle = document.getElementById('btnAddExplanation');
  const explanationSection = document.getElementById('explanationSection');
  const contentField = document.getElementById('content');
  const explanationField = document.getElementById('explanation');

  const sections = {
    mc: document.getElementById('section-mc'),
    essay: document.getElementById('section-essay'),
  };

  let contentEditor = null;
  let explanationEditor = null;

  class QuestionUploadAdapter {
    constructor(loader, uploadUrl, csrfToken) {
      this.loader = loader;
      this.uploadUrl = uploadUrl;
      this.csrfToken = csrfToken;
    }

    upload() {
      return this.loader.file.then((file) => new Promise((resolve, reject) => {
        const data = new FormData();
        data.append('upload', file);

        fetch(this.uploadUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': this.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
          credentials: 'same-origin',
          body: data,
        })
          .then((res) => res.json().then((body) => ({ res, body })))
          .then(({ res, body }) => {
            if (res.ok && body.url) {
              resolve({ default: body.url });
              return;
            }
            reject(body.error || body.message || 'Upload ảnh thất bại');
          })
          .catch(reject);
      }));
    }

    abort() {}
  }

  function createUploadAdapterPlugin(uploadUrl, csrfToken) {
    return function (editor) {
      editor.plugins.get('FileRepository').createUploadAdapter = (loader) =>
        new QuestionUploadAdapter(loader, uploadUrl, csrfToken);
    };
  }

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function editorConfig(placeholder, extraPlugins) {
    return {
      toolbar: [
        'heading', '|',
        'bold', 'italic', '|',
        'bulletedList', 'numberedList', '|',
        'link', 'imageUpload', 'mediaEmbed', 'blockQuote', 'insertTable', '|',
        'undo', 'redo',
      ],
      placeholder,
      extraPlugins: extraPlugins || [],
    };
  }

  function initEditors() {
    if (typeof ClassicEditor === 'undefined') {
      console.error('CKEditor 5 chưa tải.');
      return;
    }

    const uploadUrl = boot.uploadUrl || '/api/question-content-images';
    const uploadPlugin = createUploadAdapterPlugin(uploadUrl, getCsrfToken());

    ClassicEditor.create(
      document.querySelector('#contentEditor'),
      editorConfig('Nhập nội dung câu hỏi…', [uploadPlugin])
    ).then((editor) => {
      contentEditor = editor;
      if (boot.content) editor.setData(boot.content);
    }).catch(console.error);

    if (explanationSection && !explanationSection.hidden) {
      initExplanationEditor();
    }
  }

  function initExplanationEditor() {
    if (explanationEditor || typeof ClassicEditor === 'undefined') return;

    const uploadUrl = boot.uploadUrl || '/api/question-content-images';
    const uploadPlugin = createUploadAdapterPlugin(uploadUrl, getCsrfToken());

    ClassicEditor.create(
      document.querySelector('#explanationEditor'),
      {
        toolbar: ['bold', 'italic', 'link', 'imageUpload', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
        placeholder: 'Giải thích đáp án (hiển thị sau khi học sinh trả lời)…',
        extraPlugins: [uploadPlugin],
      }
    ).then((editor) => {
      explanationEditor = editor;
      if (boot.explanation) editor.setData(boot.explanation);
    }).catch(console.error);
  }

  function syncTypeSections() {
    const type = typeSelect.value;
    Object.entries(sections).forEach(([key, el]) => {
      if (el) el.classList.toggle('active', key === type);
    });
  }

  function optionLabel(index) {
    return 'Đáp án ' + (index + 1);
  }

  function getOptionRows() {
    return Array.from(optionsList.querySelectorAll('.qe-option-row'));
  }

  function getSelectedCorrectIndex() {
    const checked = optionsList.querySelector('input[name="correct_index"]:checked');
    return checked ? parseInt(checked.value, 10) : 0;
  }

  function syncCorrectSelect() {
    if (!correctSelect) return;
    const rows = getOptionRows();
    const selected = getSelectedCorrectIndex();
    correctSelect.innerHTML = '';
    rows.forEach((row, index) => {
      const opt = document.createElement('option');
      opt.value = String(index);
      opt.textContent = optionLabel(index);
      if (index === selected) opt.selected = true;
      correctSelect.appendChild(opt);
    });
    if (rows.length && selected >= rows.length) {
      const firstRadio = rows[0].querySelector('input[type="radio"]');
      if (firstRadio) firstRadio.checked = true;
      correctSelect.value = '0';
    }
  }

  function syncOptionStyles() {
    const selected = getSelectedCorrectIndex();
    getOptionRows().forEach((row, index) => {
      row.classList.toggle('is-correct', index === selected);
    });
  }

  function reindexOptions() {
    getOptionRows().forEach((row, index) => {
      row.dataset.index = String(index);
      const radio = row.querySelector('input[type="radio"]');
      const text = row.querySelector('input[type="text"]');
      if (radio) {
        radio.value = String(index);
        radio.id = 'option_radio_' + index;
      }
      if (text) {
        text.name = 'options[' + index + ']';
        text.id = 'option_text_' + index;
        text.placeholder = optionLabel(index);
      }
      const removeBtn = row.querySelector('.qe-option-remove');
      if (removeBtn) {
        removeBtn.hidden = getOptionRows().length <= MIN_OPTIONS;
      }
    });
    syncCorrectSelect();
    syncOptionStyles();
    if (addOptionBtn) {
      addOptionBtn.disabled = getOptionRows().length >= MAX_OPTIONS;
    }
  }

  function createOptionRow(value, index, isCorrect) {
    const row = document.createElement('div');
    row.className = 'qe-option-row';
    row.dataset.index = String(index);

    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'correct_index';
    radio.value = String(index);
    radio.id = 'option_radio_' + index;
    radio.checked = isCorrect;
    radio.title = 'Đánh dấu đáp án đúng';

    const text = document.createElement('input');
    text.type = 'text';
    text.name = 'options[' + index + ']';
    text.id = 'option_text_' + index;
    text.value = value || '';
    text.placeholder = optionLabel(index);
    text.maxLength = 500;

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'qe-option-remove';
    removeBtn.innerHTML = '&times;';
    removeBtn.title = 'Xóa đáp án';
    removeBtn.addEventListener('click', () => {
      if (getOptionRows().length <= MIN_OPTIONS) return;
      const wasCorrect = radio.checked;
      row.remove();
      reindexOptions();
      if (wasCorrect) {
        const first = optionsList.querySelector('input[name="correct_index"]');
        if (first) first.checked = true;
        reindexOptions();
      }
    });

    radio.addEventListener('change', () => {
      syncCorrectSelect();
      syncOptionStyles();
    });

    row.append(radio, text, removeBtn);
    return row;
  }

  function initOptions() {
    if (!optionsList) return;
    const options = Array.isArray(boot.options) ? boot.options : ['', '', '', ''];
    const correctIndex = Number.isFinite(boot.correctIndex) ? boot.correctIndex : 0;

    optionsList.innerHTML = '';
    options.forEach((value, index) => {
      optionsList.appendChild(createOptionRow(value, index, index === correctIndex));
    });
    reindexOptions();
  }

  function collectDraftQuestion() {
    const options = getOptionRows().map((row) => {
      const input = row.querySelector('input[type="text"]');
      return input ? input.value.trim() : '';
    });

    let explanation = '';
    if (explanationEditor) {
      explanation = explanationEditor.getData();
    } else if (explanationField && explanationSection && !explanationSection.hidden) {
      explanation = explanationField.value;
    }

    return {
      content: contentEditor ? contentEditor.getData() : contentField.value,
      answer_type: typeSelect.value,
      options,
      correct_index: getSelectedCorrectIndex(),
      correct_answer_normalized: document.getElementById('correct_answer_normalized')?.value?.trim() || '',
      explanation,
      points: parseInt(document.getElementById('points')?.value, 10) || 1,
      time_limit_seconds: parseInt(document.getElementById('time_limit_seconds')?.value, 10) || 30,
      sort_order: parseInt(document.getElementById('sort_order')?.value, 10) || 0,
    };
  }

  function openPreview() {
    if (!window.HTDQuizPreview) {
      console.error('HTDQuizPreview chưa tải.');
      return;
    }
    window.HTDQuizPreview.openQuestions({
      title: 'Xem trước câu hỏi',
      subtitle: 'Chuyển tab để xem góc học sinh hoặc barem giáo viên',
      questions: [collectDraftQuestion()],
      mode: 'student',
    });
  }

  if (addOptionBtn) {
    addOptionBtn.addEventListener('click', () => {
      if (getOptionRows().length >= MAX_OPTIONS) return;
      const index = getOptionRows().length;
      optionsList.appendChild(createOptionRow('', index, false));
      reindexOptions();
    });
  }

  if (correctSelect) {
    correctSelect.addEventListener('change', () => {
      const index = parseInt(correctSelect.value, 10);
      const radio = optionsList.querySelector('input[name="correct_index"][value="' + index + '"]');
      if (radio) {
        radio.checked = true;
        syncOptionStyles();
      }
    });
  }

  if (typeSelect) {
    typeSelect.addEventListener('change', syncTypeSections);
    syncTypeSections();
  }

  if (explanationToggle && explanationSection) {
    explanationToggle.addEventListener('click', () => {
      explanationSection.hidden = false;
      explanationToggle.hidden = true;
      initExplanationEditor();
    });
  }

  if (previewBtn) previewBtn.addEventListener('click', openPreview);

  form.addEventListener('submit', () => {
    if (contentEditor) contentField.value = contentEditor.getData();
    if (explanationEditor) {
      explanationField.value = explanationEditor.getData();
    } else if (!explanationSection || explanationSection.hidden) {
      explanationField.value = '';
    }
  });

  initOptions();
  initEditors();
})();
