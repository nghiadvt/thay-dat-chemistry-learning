@extends('layouts.admin')

@section('title', $quiz->name.' — Ôn trắc nghiệm')

@php
    $backToTopicUrl = route('admin.practice.index', ['grade' => $quiz->topic->practice_grade_id, 'open_topic' => $quiz->topic->id]);
@endphp

@section('content')
<p class="page-subtitle">
    <a href="{{ route('admin.practice.index', ['grade' => $quiz->topic->practice_grade_id]) }}">Ôn trắc nghiệm</a>
    ▸ {{ $quiz->topic->grade->name }} ▸
    <a href="{{ $backToTopicUrl }}">{{ $quiz->topic->name }}</a>
</p>

<div class="page-header">
    <div class="page-header__text">
        <h2>{{ $quiz->name }}</h2>
        @if ($quiz->requires_pro)
            <span class="practice-pro-badge">🔒 Yêu cầu Pro</span>
        @endif
    </div>
    <a href="{{ $backToTopicUrl }}" class="btn btn-secondary">← {{ $quiz->topic->name }}</a>
</div>

<div class="card quiz-detail-section">
    <div class="section-header">
        <h3>Thông tin bài</h3>
        <div class="section-header-actions toggle-field">
            <span class="toggle-field-label">Kích hoạt</span>
            @include('admin.partials.toggle-switch', [
                'formAction' => route('admin.practice.quizzes.toggle-active', $quiz),
                'checked' => $quiz->is_active,
                'submitOnChange' => true,
                'label' => 'Bật/tắt bài',
            ])
        </div>
    </div>

    <form method="POST" action="{{ route('admin.practice.quizzes.update', $quiz) }}">
        @csrf @method('PUT')
        <div class="form-group">
            <label for="name">Tên bài *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $quiz->name) }}" required maxlength="150">
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="sort_order">Thứ tự trong chủ đề</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $quiz->sort_order) }}" class="input-narrow">
        </div>
        <button type="submit" class="btn btn-primary">Lưu thông tin bài</button>
    </form>

    <div class="toggle-field" style="margin-top:16px">
        <span class="toggle-field-label">Yêu cầu tài khoản Pro</span>
        @include('admin.partials.toggle-switch', [
            'formAction' => route('admin.practice.quizzes.toggle-pro', $quiz),
            'checked' => $quiz->requires_pro,
            'submitOnChange' => true,
            'label' => 'Bật/tắt yêu cầu Pro',
        ])
    </div>
    <p class="page-subtitle">Bật thì tài khoản thường/khách sẽ thấy tên bài nhưng bấm vào bị khóa hoàn toàn, phải nâng cấp Pro mới mở được.</p>
</div>

<div class="card quiz-detail-section" id="practiceQuestionsCard"
     data-practice-quiz-id="{{ $quiz->id }}"
     data-attach-url="{{ route('admin.practice.quizzes.questions.attach', $quiz) }}"
     data-reorder-url="{{ route('admin.practice.quizzes.questions.reorder', $quiz) }}"
     data-bank-search-url="{{ route('admin.question-bank.search') }}"
     data-attached-ids="{{ $quiz->questionBankItems->pluck('id')->toJson() }}">
    <div class="section-header">
        <h3>Câu hỏi ({{ $quiz->questionBankItems->count() }})</h3>
        <button type="button" class="btn btn-primary btn-sm" id="btnOpenBankModal">+ Từ bộ câu hỏi</button>
    </div>

    @if ($quiz->questionBankItems->isEmpty())
        <div class="empty-state">
            Chưa có câu hỏi.
            <button type="button" class="btn-link" id="btnOpenBankModalEmpty">Thêm từ bộ câu hỏi</button>.
        </div>
    @else
    <div class="table-wrap">
        <table class="data-table qq-questions-table">
            <thead>
                <tr>
                    <th class="qq-col-drag" aria-label="Kéo thả"></th>
                    <th>#</th>
                    <th>Loại</th>
                    <th>Chủ đề</th>
                    <th>Nội dung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="practiceQuestionsBody">
                @foreach ($quiz->questionBankItems as $item)
                <tr class="qq-question-row" data-question-id="{{ $item->id }}" draggable="true">
                    <td class="qq-col-drag"><span class="qq-drag-handle" title="Kéo đổi thứ tự">⠿</span></td>
                    <td class="qq-sort-cell">{{ $loop->iteration }}</td>
                    <td>@php
                        echo match ($item->answer_type) {
                            'mc' => 'Trắc nghiệm',
                            'structured' => 'Phương trình',
                            default => 'Tự luận',
                        };
                    @endphp</td>
                    <td class="qq-tag-cell">
                        <div class="tag-list">
                            @foreach ($item->tags as $tag)
                                @include('admin.partials.tag-chip', ['tag' => $tag])
                            @endforeach
                        </div>
                    </td>
                    <td>{!! Str::limit(strip_tags($item->content), 80) !!}</td>
                    <td class="actions">
                        <form method="POST" action="{{ route('admin.practice.quizzes.questions.detach', [$quiz, $item]) }}" data-confirm="Bỏ câu hỏi này khỏi bài?" data-confirm-danger="1">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div id="bankModal" class="qq-modal" hidden aria-hidden="true">
    <div class="qq-modal-backdrop" data-close-bank-modal></div>
    <div class="qq-modal-dialog qq-bank-modal" role="dialog" aria-labelledby="bankModalTitle">
        <header class="qq-modal-header">
            <h3 id="bankModalTitle">Thêm câu hỏi từ bộ</h3>
            <button type="button" class="qq-modal-close" data-close-bank-modal aria-label="Đóng">×</button>
        </header>
        <div class="qq-modal-filters">
            @include('admin.partials.tag-select', [
                'mode' => 'filter-multi',
                'tags' => $bankTags,
                'selected' => [],
                'tagNone' => false,
                'id' => 'bankFilterTagSelect',
                'autoSubmit' => false,
                'showAll' => true,
                'showUntagged' => true,
            ])
            <div class="form-group">
                <label for="bankFilterType">Loại</label>
                <select id="bankFilterType">
                    <option value="">Tất cả</option>
                    <option value="mc">Trắc nghiệm</option>
                    <option value="essay">Tự luận</option>
                    <option value="structured">Phương trình</option>
                </select>
            </div>
            <div class="form-group qq-filter-search">
                <label for="bankFilterQ">Tìm kiếm</label>
                <input type="search" id="bankFilterQ" placeholder="Nội dung câu hỏi...">
            </div>
        </div>
        <div class="qq-bank-body">
            <div class="qq-bank-list-wrap">
                <p class="qq-bank-list-label">Danh sách câu hỏi</p>
                <div id="bankList" class="qq-bank-list" aria-live="polite">
                    <p class="qq-bank-loading">Đang tải...</p>
                </div>
            </div>
            <div class="qq-bank-selected-wrap">
                <p class="qq-bank-selected-label">Đã chọn (<span id="bankSelectedCount">0</span>)</p>
                <div id="bankSelected" class="qq-bank-selected"></div>
            </div>
        </div>
        <footer class="qq-modal-footer">
            <button type="button" class="btn btn-secondary" data-close-bank-modal>Hủy</button>
            <button type="button" class="btn btn-primary" id="btnAddFromBank" disabled>Thêm vào bài</button>
        </footer>
    </div>
</div>

<div class="card quiz-detail-section">
    <h3 style="margin-top:0">Lớp áp dụng</h3>
    <p class="page-subtitle">Chỉ những lớp được chọn ở đây mới thấy bài này.</p>
    <form method="POST" action="{{ route('admin.practice.quizzes.classes.sync', $quiz) }}">
        @csrf @method('PUT')
        @include('admin.partials.class-checklist', [
            'classes' => $classes,
            'selectedIds' => $selectedClassIds,
            'id' => 'practiceQuizClassChecklist',
        ])
        <button type="submit" class="btn btn-primary" style="margin-top:12px">Lưu danh sách lớp</button>
    </form>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="@vasset('htd-admin/css/quiz-questions.css')">
<link rel="stylesheet" href="@vasset('htd-admin/css/practice.css')">
@endpush
@push('scripts')
<script src="@vasset('htd-admin/js/practice-quiz-editor.js')"></script>
@endpush
