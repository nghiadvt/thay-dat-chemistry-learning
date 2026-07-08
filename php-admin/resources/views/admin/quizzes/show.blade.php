@extends('layouts.admin')

@section('title', $quiz->name.' — Quiz')
@section('page-title', 'Chi tiết quiz')


@section('content')
<div class="page-header">
    <div>
        <h2>{{ $quiz->name }}</h2>
        <p style="margin:4px 0 0;color:#6b7280;">
            Game: <strong>{{ $quiz->game?->name }}</strong> ·
            Bàn phím: <strong>{{ $quiz->keyboard?->name }}</strong>
        </p>
        @if ($quiz->tags->isNotEmpty())
            <div class="tag-list" style="margin-top:8px;">
                @foreach ($quiz->tags as $tag)
                    @include('admin.partials.tag-chip', ['tag' => $tag])
                @endforeach
            </div>
        @endif
    </div>
    <div class="actions">
        <button type="button" class="btn btn-secondary" data-quiz-preview="{{ $quiz->id }}" data-quiz-name="{{ $quiz->name }}">Xem trước</button>
        <a href="{{ route('admin.quizzes.index') }}" class="btn btn-secondary">← Danh sách</a>
    </div>
</div>

<div class="card quiz-detail-section">
    <div class="section-header">
        <h3>Thông tin quiz</h3>
        <div class="section-header-actions toggle-field">
            <span class="toggle-field-label">Kích hoạt</span>
            @include('admin.partials.toggle-switch', [
                'formAction' => route('admin.quizzes.toggle-active', $quiz),
                'checked' => $quiz->is_active,
                'submitOnChange' => true,
                'label' => 'Bật/tắt quiz',
            ])
        </div>
    </div>

    <form method="POST" action="{{ route('admin.quizzes.update', $quiz) }}">
        @csrf @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label for="game_id">Game *</label>
                <select id="game_id" name="game_id" required>
                    <option value="">— Chọn game —</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected(old('game_id', $quiz->game_id) == $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                @error('game_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            @include('admin.partials.keyboard-select-with-preview', [
                'keyboards' => $keyboards,
                'selectedKeyboardId' => old('keyboard_id', $quiz->keyboard_id),
            ])
        </div>

        <div class="form-group">
            <label for="name">Tên quiz *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $quiz->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            @include('admin.partials.tag-select', [
                'mode' => 'multi',
                'tags' => $bankTags,
                'selected' => $selectedQuizTagIds ?? [],
                'label' => 'Chủ đề (tag)',
            ])
            @error('tag_ids')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="subject">Môn</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject', $quiz->subject ?? 'chemistry') }}">
            </div>
            <div class="form-group">
                <label for="grade">Lớp</label>
                <input type="text" id="grade" name="grade" value="{{ old('grade', $quiz->grade) }}" placeholder="10, 11, 12...">
            </div>
        </div>

        <div class="form-group">
            <label for="sort_order">Thứ tự trong game</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $quiz->sort_order ?? 0) }}" style="max-width:160px;">
        </div>

        <div class="form-group quiz-play-settings">
            <label>Tùy chọn khi chơi</label>
            <div class="toggle-field">
                <input type="hidden" name="show_explanation" value="0">
                @include('admin.partials.toggle-switch', [
                    'name' => 'show_explanation',
                    'checked' => (bool) old('show_explanation', $quiz->show_explanation ?? false),
                    'label' => 'Hiển thị giải thích đáp án',
                ])
                <span class="toggle-field-label">Hiển thị giải thích đáp án sau khi học sinh trả lời</span>
            </div>
            <div class="toggle-field">
                <input type="hidden" name="shuffle_options" value="0">
                @include('admin.partials.toggle-switch', [
                    'name' => 'shuffle_options',
                    'checked' => (bool) old('shuffle_options', $quiz->shuffle_options ?? false),
                    'label' => 'Xáo trộn đáp án trắc nghiệm',
                ])
                <span class="toggle-field-label">Xáo trộn thứ tự đáp án trắc nghiệm (mỗi học sinh khác nhau)</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Lưu thông tin quiz</button>
    </form>
</div>

<div class="card quiz-detail-section" id="quizQuestionsCard"
     data-quiz-id="{{ $quiz->id }}"
     data-from-bank-url="{{ route('admin.questions.from-bank', $quiz) }}"
     data-reorder-url="{{ route('admin.questions.reorder', $quiz) }}"
     data-bulk-url="{{ route('admin.questions.bulk', $quiz) }}"
     data-bank-search-url="{{ route('admin.question-bank.search') }}">
    <div class="section-header">
        <h3>Câu hỏi ({{ $quiz->questions->count() }})</h3>
        <div class="section-header-actions qq-add-actions">
            <div class="qq-split-btn">
                <a href="{{ route('admin.questions.create', $quiz) }}" class="btn btn-primary btn-sm">+ Tạo câu mới</a>
                <button type="button" class="btn btn-primary btn-sm" id="btnOpenBankModal">+ Từ bộ câu hỏi</button>
            </div>
        </div>
    </div>

    <div id="qqBulkBar" class="qq-bulk-bar qq-bulk-bar--idle">
        <span class="qq-bulk-count"><strong id="qqBulkCount">0</strong> câu đã chọn</span>
        <div class="qq-bulk-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-bulk-action="tags" disabled>Đổi chủ đề</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bulk-action="time" disabled>Đổi thời gian</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bulk-action="points" disabled>Đổi điểm</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bulk-action="enable" disabled>Bật</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bulk-action="disable" disabled>Tắt</button>
            <button type="button" class="btn btn-danger btn-sm" data-bulk-action="delete" disabled>Xóa</button>
        </div>
    </div>

    @if ($quiz->questions->isEmpty())
        <div class="empty-state">
            Chưa có câu hỏi.
            <a href="{{ route('admin.questions.create', $quiz) }}">Tạo câu hỏi mới</a>
            hoặc
            <button type="button" class="btn-link" id="btnOpenBankModalEmpty">thêm từ bộ câu hỏi</button>.
        </div>
    @else
    <div class="table-wrap">
        <table class="data-table qq-questions-table">
            <thead>
                <tr>
                    <th class="qq-col-check"><input type="checkbox" id="qqSelectAll" aria-label="Chọn tất cả"></th>
                    <th class="qq-col-drag" aria-label="Kéo thả"></th>
                    <th>#</th>
                    <th>Loại</th>
                    <th>Chủ đề</th>
                    <th>Nội dung</th>
                    <th>Điểm</th>
                    <th>Thời gian</th>
                    <th>Bật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="qqQuestionsBody">
                @foreach ($quiz->questions as $question)
                @php $questionActive = $question->is_active ?? true; @endphp
                <tr class="qq-question-row {{ $questionActive ? '' : 'row-inactive' }}" data-question-id="{{ $question->id }}" draggable="true">
                    <td class="qq-col-check">
                        <input type="checkbox" class="qq-row-check" value="{{ $question->id }}" aria-label="Chọn câu hỏi">
                    </td>
                    <td class="qq-col-drag"><span class="qq-drag-handle" title="Kéo đổi thứ tự">⠿</span></td>
                    <td class="qq-sort-cell">{{ $question->sort_order }}</td>
                    <td>@php
                        echo match ($question->answer_type) {
                            'mc' => 'Trắc nghiệm',
                            'structured' => match ($question->input_mode) {
                                'balance' => 'Cân bằng hệ số',
                                'blank' => 'Điền chỗ thiếu',
                                'blank_balance' => 'Cân bằng + điền',
                                'product' => 'Điền sản phẩm',
                                default => 'Phương trình',
                            },
                            default => 'Tự luận',
                        };
                    @endphp</td>
                    <td class="qq-tag-cell">
                        @include('admin.partials.question-tags-cell', [
                            'tags' => $bankTags,
                            'selectedTags' => $question->sourceBankItem?->tags ?? collect(),
                            'updateUrl' => route('admin.questions.update-tags', [$quiz, $question]),
                            'itemId' => $question->id,
                        ])
                    </td>
                    <td>{!! Str::limit(strip_tags($question->content), 80) !!}</td>
                    <td class="qq-points-cell">{{ $question->points ?? 1 }}</td>
                    <td class="qq-time-cell">{{ $question->time_limit_seconds }}s</td>
                    <td>
                        @include('admin.partials.toggle-switch', [
                            'formAction' => route('admin.questions.toggle-active', [$quiz, $question]),
                            'checked' => $questionActive,
                            'submitOnChange' => true,
                            'label' => 'Bật/tắt câu hỏi',
                        ])
                    </td>
                    <td class="actions">
                        <a href="{{ route('admin.questions.edit', [$quiz, $question]) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.questions.destroy', [$quiz, $question]) }}" onsubmit="return confirm('Xóa câu hỏi này?')">
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
            <button type="button" class="btn btn-primary" id="btnAddFromBank" disabled>Thêm vào quiz</button>
        </footer>
    </div>
</div>

<div id="bulkModal" class="qq-modal" hidden aria-hidden="true">
    <div class="qq-modal-backdrop" data-close-bulk-modal></div>
    <div class="qq-modal-dialog qq-bulk-modal" role="dialog">
        <header class="qq-modal-header">
            <h3 id="bulkModalTitle">Cập nhật hàng loạt</h3>
            <button type="button" class="qq-modal-close" data-close-bulk-modal aria-label="Đóng">×</button>
        </header>
        <div class="qq-modal-body">
            <div id="bulkFieldTags" class="form-group" hidden>
                <label>Chọn chủ đề (có thể chọn nhiều)</label>
                @include('admin.partials.bulk-tag-picker', ['tags' => $bankTags, 'id' => 'qqBulkTagPicker', 'selectedIds' => []])
                <button type="button" class="tag-checklist-add" data-open-tag-modal-from-checklist>+ Thêm chủ đề</button>
            </div>
            <div id="bulkFieldTime" class="form-group" hidden>
                <label for="bulkTimeInput">Thời gian (giây)</label>
                <input type="number" id="bulkTimeInput" min="5" max="300" value="30">
            </div>
            <div id="bulkFieldPoints" class="form-group" hidden>
                <label for="bulkPointsInput">Điểm</label>
                <input type="number" id="bulkPointsInput" min="1" max="100" value="1">
            </div>
            <p id="bulkConfirmText" hidden></p>
        </div>
        <footer class="qq-modal-footer">
            <button type="button" class="btn btn-secondary" data-close-bulk-modal>Hủy</button>
            <button type="button" class="btn btn-primary" id="btnBulkConfirm">Xác nhận</button>
        </footer>
    </div>
</div>

@include('admin.partials.keyboard-preview-lightbox')
@endsection

@push('head')
@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@php $qqCss = public_path('htd-admin/css/quiz-questions.css'); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-questions.css') }}?v={{ file_exists($qqCss) ? filemtime($qqCss) : $qpV }}">
@endpush
@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/quiz-preview.js') }}?v={{ file_exists($qpJs) ? filemtime($qpJs) : $qpV }}"></script>
@php $kbPreviewJs = public_path('htd-admin/js/admin-keyboard-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/admin-keyboard-preview.js') }}?v={{ file_exists($kbPreviewJs) ? filemtime($kbPreviewJs) : $qpV }}"></script>
@php $qqJs = public_path('htd-admin/js/quiz-questions.js'); @endphp
<script src="{{ asset('htd-admin/js/quiz-questions.js') }}?v={{ file_exists($qqJs) ? filemtime($qqJs) : $qpV }}"></script>
<script src="{{ asset('js/question-tags-cell.js') }}?v={{ file_exists(public_path('js/question-tags-cell.js')) ? filemtime(public_path('js/question-tags-cell.js')) : $qpV }}"></script>
@endpush
