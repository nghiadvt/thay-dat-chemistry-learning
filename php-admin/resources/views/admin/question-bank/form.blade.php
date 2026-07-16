@extends('layouts.admin')

@section('title', ($item ? 'Sửa' : 'Thêm').' câu hỏi bộ — Hóa Thầy Đạt')
@section('page-title', ($item ? 'Sửa' : 'Thêm').' câu hỏi bộ')
@section('body-class', 'admin-body--question-editor')

@php
    $defaultOptions = ['', '', '', ''];
    $initialOptions = old('options');
    if (! is_array($initialOptions)) {
        $initialOptions = $item?->options ?? $defaultOptions;
    }
    if (count($initialOptions) < 2) {
        $initialOptions = $defaultOptions;
    }
    $correctIndex = (int) old('correct_index', $item?->correct_index ?? 0);
    $initialContent = old('content', $item?->content ?? '');
    $initialExplanation = old('explanation', $item?->explanation ?? '');
    $showExplanation = filled($initialExplanation);
    $initialTemplate = old('template_json');
    if (is_string($initialTemplate)) {
        $initialTemplate = json_decode($initialTemplate, true);
    }
    if (! is_array($initialTemplate)) {
        $initialTemplate = $item?->template ?? [];
    }
    $initialCorrectAnswer = old('correct_answer_json');
    if (is_string($initialCorrectAnswer)) {
        $initialCorrectAnswer = json_decode($initialCorrectAnswer, true);
    }
    if (! is_array($initialCorrectAnswer)) {
        $initialCorrectAnswer = $item?->correct_answer ?? ['coef' => [], 'blank' => []];
    }
    $initialInputMode = old('input_mode', $item?->input_mode ?? 'balance');
    $selectedTagIds = old('tag_ids', $selectedTagIds ?? []);
    $qeCss = public_path('htd-admin/css/question-editor.css');
    $qeCssV = file_exists($qeCss) ? filemtime($qeCss) : time();
@endphp

@push('head')
<link rel="stylesheet" href="{{ asset('htd-admin/css/question-editor.css') }}?v={{ $qeCssV }}">
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qeCssV }}">
@endpush

@section('content')
<div class="qe-app">
    <form id="questionForm" method="POST" action="{{ $item ? route('admin.question-bank.update', $item) : route('admin.question-bank.store') }}">
        @csrf
        @if ($item) @method('PUT') @endif

        <header class="qe-header">
            <div>
                <a href="{{ route('admin.question-bank.index') }}" class="qe-back-link">← Quay lại bộ câu hỏi</a>
                <h1>{{ $item ? 'Sửa câu hỏi trong bộ' : 'Thêm câu hỏi vào bộ' }}</h1>
                <p>Câu hỏi lưu ở đây có thể tái sử dụng khi tạo quiz.</p>
            </div>
            <div class="qe-header-actions">
                <button type="submit" class="btn btn-primary">{{ $item ? 'Cập nhật' : 'Lưu vào bộ' }}</button>
            </div>
        </header>

        <div class="qe-layout">
            <div class="qe-main">
                <div class="qe-panel">
                    <span class="qe-panel-label">Câu hỏi</span>
                    <div class="qe-editor-wrap">
                        <div id="contentEditor"></div>
                    </div>
                    <textarea id="content" name="content" hidden>{{ $initialContent }}</textarea>
                    @error('content')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="qe-explanation-toggle" @if($showExplanation) hidden @endif>
                    <button type="button" id="btnAddExplanation">+ Thêm giải thích (tuỳ chọn)</button>
                </div>

                <div id="explanationSection" class="qe-explanation-section qe-panel" @unless($showExplanation) hidden @endunless>
                    <span class="qe-panel-label">Giải thích đáp án</span>
                    <div class="qe-editor-wrap">
                        <div id="explanationEditor"></div>
                    </div>
                    <textarea id="explanation" name="explanation" hidden>{{ $initialExplanation }}</textarea>
                </div>

                <div id="section-structured" class="qe-panel qe-structured-panel type-section-main">
                    <span class="qe-panel-label">Phương trình / ô điền</span>

                    <div class="qe-quick-equation">
                        <label for="quickEquationInput" class="qe-quick-label">Nhập nhanh</label>
                        <div class="qe-quick-row">
                            <input type="text" id="quickEquationInput" class="qe-quick-eq-input"
                                placeholder="VD: H2 + O2 → H2O  ·  Fe + ___ → Fe2O3">
                            <input type="text" id="quickAnswerInput" class="qe-quick-ans-input"
                                placeholder="Đáp án: 2,1,2  hoặc  O2">
                            <button type="button" id="btnQuickApply" class="btn btn-primary btn-sm">Áp dụng</button>
                        </div>
                        <p class="qe-template-hint">
                            Cân bằng hệ số: gõ chất + đáp án số (2,1,2).
                            Điền thiếu: dùng <code>___</code> cho ô trống + đáp án công thức,
                            hoặc gõ kèm luôn đáp án trong ngoặc: <code>Fe + [O2] → Fe2O3</code>.
                            Số nhỏ (chỉ số): gõ số trong ngoặc sau nguyên tố, VD <code>C[6]H[12]O[6]</code>.
                        </p>
                    </div>

                    <div id="eqTemplatePreview" class="qe-eq-preview qe-eq-preview--interactive" aria-live="polite"></div>

                    <div class="qe-template-toolbar">
                        <button type="button" id="btnAddCoef" class="btn btn-secondary btn-sm">+ Hệ số</button>
                        <button type="button" id="btnAddSub" class="btn btn-secondary btn-sm">+ Số nhỏ</button>
                        <button type="button" id="btnAddBlank" class="btn btn-secondary btn-sm">+ Ô điền</button>
                        <button type="button" id="btnAddChem" class="btn btn-secondary btn-sm">+ Chất</button>
                        @foreach ([' + ', ' → ', ' = ', ' ↑ ', ' ↓ '] as $sym)
                            <button type="button" class="btn btn-secondary btn-sm" data-txt-symbol="{{ $sym }}">{{ trim($sym) ?: $sym }}</button>
                        @endforeach
                        <button type="button" id="btnApplyPreset" class="btn btn-secondary btn-sm qe-template-preset-btn">Mẫu theo chế độ</button>
                    </div>

                    <p class="qe-parts-list-label">Chi tiết từng phần</p>
                    <div id="templatePartsList" class="qe-template-parts"></div>

                    <input type="hidden" name="template_json" id="template_json" value="">
                    <input type="hidden" name="correct_answer_json" id="correct_answer_json" value="">
                </div>
            </div>

            <aside class="qe-sidebar">
                @include('admin.partials.tag-select', [
                    'mode' => 'multi',
                    'tags' => $tags,
                    'selected' => $selectedTagIds,
                    'label' => 'Chủ đề (tag)',
                ])

                <div class="form-group">
                    <label for="answer_type">Loại câu hỏi</label>
                    <select id="answer_type" name="answer_type" required>
                        @foreach (['mc' => 'Trắc nghiệm', 'essay' => 'Tự luận', 'structured' => 'Phương trình / ô điền'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('answer_type', $item?->answer_type ?? 'mc') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="section-mc" class="type-section">
                    <div class="form-group">
                        <label>Các đáp án</label>
                        <div id="optionsList" class="qe-options-list"></div>
                        <button type="button" id="btnAddOption" class="qe-add-option">+ Thêm đáp án</button>
                    </div>
                    <div class="form-group">
                        <label for="correct_index_select">Đáp án đúng</label>
                        <select id="correct_index_select" aria-label="Chọn đáp án đúng"></select>
                    </div>
                </div>

                <div id="section-essay" class="type-section">
                    <div class="form-group">
                        <label for="correct_answer_normalized">Đáp án mẫu</label>
                        <textarea id="correct_answer_normalized" name="correct_answer_normalized" rows="4">{{ old('correct_answer_normalized', $item?->correct_answer_normalized) }}</textarea>
                    </div>
                </div>

                <div id="section-structured-meta" class="type-section">
                    <div class="form-group">
                        <label for="input_mode">Chế độ phương trình</label>
                        <select id="input_mode" name="input_mode">
                            @foreach (['balance' => 'Cân bằng hệ số', 'blank' => 'Điền chỗ thiếu', 'blank_balance' => 'Cân bằng + điền thiếu', 'product' => 'Điền sản phẩm', 'subscript' => 'Điền chỉ số'] as $val => $label)
                                <option value="{{ $val }}" @selected($initialInputMode === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="qe-sidebar-divider type-section-divider"></div>

                <div class="form-group">
                    <label for="points">Điểm mặc định</label>
                    <input type="number" id="points" name="points" min="1" max="100" value="{{ old('points', $item?->points ?? 1) }}">
                </div>

                <div class="form-group">
                    <label for="time_limit_seconds">Thời gian mặc định (giây)</label>
                    <input type="number" id="time_limit_seconds" name="time_limit_seconds" min="5" max="300" value="{{ old('time_limit_seconds', $item?->time_limit_seconds ?? 30) }}">
                </div>
            </aside>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
window.QUESTION_EDITOR_BOOT = {
    content: @json($initialContent),
    explanation: @json($initialExplanation),
    options: @json(array_values($initialOptions)),
    correctIndex: {{ $correctIndex }},
    uploadUrl: @json(url('/api/question-content-images')),
    inputMode: @json($initialInputMode),
    template: @json($initialTemplate),
    correctAnswer: @json($initialCorrectAnswer),
};
</script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
@php
    $qeJs = public_path('htd-admin/js/question-editor.js');
    $eqJs = public_path('htd-admin/js/equation-ui.js');
    $qtbJs = public_path('htd-admin/js/question-template-builder.js');
@endphp
<script src="@vasset('htd-admin/js/equation-ui.js')"></script>
<script src="@vasset('htd-admin/js/question-template-builder.js')"></script>
<script src="@vasset('htd-admin/js/question-editor.js')"></script>
@endpush
