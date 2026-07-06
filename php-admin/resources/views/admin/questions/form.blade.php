@extends('layouts.admin')

@section('title', ($question ? 'Sửa' : 'Thêm').' câu hỏi — Hóa Thầy Đạt')
@section('page-title', ($question ? 'Sửa' : 'Thêm').' câu hỏi')
@section('body-class', 'admin-body--question-editor')

@php
    $defaultOptions = ['', '', '', ''];
    $initialOptions = old('options');
    if (! is_array($initialOptions)) {
        $initialOptions = $question?->options ?? $defaultOptions;
    }
    if (count($initialOptions) < 2) {
        $initialOptions = $defaultOptions;
    }
    $correctIndex = (int) old('correct_index', $question?->correct_index ?? 0);
    $initialContent = old('content', $question?->content ?? '');
    $initialExplanation = old('explanation', $question?->explanation ?? '');
    $showExplanation = filled($initialExplanation);
    $qeCss = public_path('htd-admin/css/question-editor.css');
    $qeCssV = file_exists($qeCss) ? filemtime($qeCss) : time();
@endphp

@push('head')
<link rel="stylesheet" href="{{ asset('htd-admin/css/question-editor.css') }}?v={{ $qeCssV }}">
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qeCssV }}">
@endpush

@section('content')
<div class="qe-app">
    <form id="questionForm" method="POST" action="{{ $question ? route('admin.questions.update', [$quiz, $question]) : route('admin.questions.store', $quiz) }}">
        @csrf
        @if ($question) @method('PUT') @endif

        <header class="qe-header">
            <div>
                <a href="{{ route('admin.quizzes.show', $quiz) }}" class="qe-back-link">← Quay lại quiz</a>
                <h1>{{ $question ? 'Sửa câu hỏi' : 'Thêm câu hỏi mới' }}</h1>
                <p>Thêm câu hỏi vào &ldquo;{{ $quiz->name }}&rdquo;</p>
            </div>
            <div class="qe-header-actions">
                <button type="button" id="btnPreview" class="btn btn-secondary">Xem trước</button>
                <button type="submit" class="btn btn-primary">{{ $question ? 'Cập nhật' : 'Lưu câu hỏi' }}</button>
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
            </div>

            <aside class="qe-sidebar">
                <div class="form-group">
                    <label for="answer_type">Loại câu hỏi</label>
                    <select id="answer_type" name="answer_type" required>
                        @foreach (['mc' => 'Trắc nghiệm', 'essay' => 'Tự luận'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('answer_type', $question?->answer_type ?? 'mc') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('answer_type')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div id="section-mc" class="type-section">
                    <div class="form-group">
                        <label>Các đáp án</label>
                        <div id="optionsList" class="qe-options-list"></div>
                        <button type="button" id="btnAddOption" class="qe-add-option">+ Thêm đáp án</button>
                        @error('options')<div class="field-error">{{ $message }}</div>@enderror
                        @error('correct_index')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="correct_index_select">Đáp án đúng</label>
                        <select id="correct_index_select" aria-label="Chọn đáp án đúng"></select>
                    </div>
                </div>

                <div id="section-essay" class="type-section">
                    <div class="form-group">
                        <label for="correct_answer_normalized">Đáp án mẫu</label>
                        <textarea id="correct_answer_normalized" name="correct_answer_normalized" rows="4" placeholder="Nhập đáp án mẫu để chấm tự động (so khớp văn bản)">{{ old('correct_answer_normalized', $question?->correct_answer_normalized) }}</textarea>
                        @error('correct_answer_normalized')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="qe-sidebar-divider"></div>

                <div class="form-group">
                    <label for="points">Điểm</label>
                    <input type="number" id="points" name="points" min="1" max="100" value="{{ old('points', $question?->points ?? 1) }}">
                </div>

                <div class="form-group">
                    <label for="time_limit_seconds">Thời gian (giây)</label>
                    <input type="number" id="time_limit_seconds" name="time_limit_seconds" min="5" max="300" value="{{ old('time_limit_seconds', $question?->time_limit_seconds ?? 30) }}">
                </div>

                <div class="form-group">
                    <label for="sort_order">Thứ tự</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $question?->sort_order ?? 0) }}">
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
};
</script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
@php
    $qeJs = public_path('htd-admin/js/question-editor.js');
    $qpJs = public_path('htd-admin/js/quiz-preview.js');
@endphp
<script src="{{ asset('htd-admin/js/quiz-preview.js') }}?v={{ file_exists($qpJs) ? filemtime($qpJs) : $qeCssV }}"></script>
<script src="{{ asset('htd-admin/js/question-editor.js') }}?v={{ file_exists($qeJs) ? filemtime($qeJs) : $qeCssV }}"></script>
@endpush
