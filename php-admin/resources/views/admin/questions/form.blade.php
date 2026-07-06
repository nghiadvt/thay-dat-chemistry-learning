@extends('layouts.admin')

@section('title', ($question ? 'Sửa' : 'Thêm').' câu hỏi — Hóa Thầy Đạt')
@section('page-title', ($question ? 'Sửa' : 'Thêm').' câu hỏi')

@section('content')
<div class="page-header">
    <h2>{{ $question ? 'Sửa câu hỏi' : 'Thêm câu hỏi' }} — {{ $quiz->name }}</h2>
    <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn btn-secondary">← Quay lại quiz</a>
</div>

<div class="card">
    <form method="POST" action="{{ $question ? route('admin.questions.update', [$quiz, $question]) : route('admin.questions.store', $quiz) }}">
        @csrf
        @if ($question) @method('PUT') @endif

        <div class="form-group">
            <label for="content">Nội dung (HTML) *</label>
            <textarea id="content" name="content" rows="4" required>{{ old('content', $question?->content) }}</textarea>
            <p class="hint">Ví dụ: &lt;p&gt;Câu hỏi?&lt;/p&gt; hoặc &lt;p&gt;Công thức của nước?&lt;/p&gt;</p>
            @error('content')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="answer_type">Loại câu hỏi *</label>
                <select id="answer_type" name="answer_type" required>
                    @foreach (['mc' => 'Trắc nghiệm (mc)', 'formula' => 'Công thức (formula)', 'structured' => 'Có cấu trúc (structured)'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('answer_type', $question?->answer_type ?? 'mc') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('answer_type')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label for="time_limit_seconds">Thời gian (giây)</label>
                    <input type="number" id="time_limit_seconds" name="time_limit_seconds" min="5" max="300" value="{{ old('time_limit_seconds', $question?->time_limit_seconds ?? 30) }}">
                </div>
                <div class="form-group">
                    <label for="sort_order">Thứ tự</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $question?->sort_order ?? 0) }}">
                </div>
            </div>
        </div>

        <div id="section-mc" class="type-section">
            <div class="form-group">
                <label for="options_text">Các đáp án (mỗi dòng một đáp án) *</label>
                <textarea id="options_text" name="options_text" rows="5">{{ old('options_text', $question && $question->options ? implode("\n", $question->options) : "Đáp án A\nĐáp án B\nĐáp án C\nĐáp án D") }}</textarea>
                @error('options')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="correct_index">Đáp án đúng (chỉ số, bắt đầu từ 0) *</label>
                <input type="number" id="correct_index" name="correct_index" min="0" value="{{ old('correct_index', $question?->correct_index ?? 0) }}">
                @error('correct_index')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div id="section-formula" class="type-section">
            <div class="form-group">
                <label for="correct_answer_normalized">Đáp án đúng (chuẩn hóa) *</label>
                <input type="text" id="correct_answer_normalized" name="correct_answer_normalized" value="{{ old('correct_answer_normalized', $question?->correct_answer_normalized) }}" placeholder="H2O">
                @error('correct_answer_normalized')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div id="section-structured" class="type-section">
            <div class="form-group">
                <label for="input_mode">Input mode *</label>
                <input type="text" id="input_mode" name="input_mode" value="{{ old('input_mode', $question?->input_mode ?? 'balance') }}" placeholder="balance">
                @error('input_mode')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="template_json">Template (JSON) *</label>
                <textarea id="template_json" name="template_json" class="code" rows="4">{{ old('template_json', $question?->template ? json_encode($question->template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{"coef":["c0","c1","c2"]}') }}</textarea>
                @error('template')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="correct_answer_json">Đáp án đúng (JSON) *</label>
                <textarea id="correct_answer_json" name="correct_answer_json" class="code" rows="4">{{ old('correct_answer_json', $question?->correct_answer ? json_encode($question->correct_answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{"coef":{"c0":"2","c1":"1","c2":"2"}}') }}</textarea>
                @error('correct_answer')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{{ $question ? 'Cập nhật' : 'Thêm câu hỏi' }}</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const select = document.getElementById('answer_type');
  const sections = {
    mc: document.getElementById('section-mc'),
    formula: document.getElementById('section-formula'),
    structured: document.getElementById('section-structured'),
  };

  function sync() {
    const type = select.value;
    Object.entries(sections).forEach(([key, el]) => {
      el.classList.toggle('active', key === type);
    });
  }

  select.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
