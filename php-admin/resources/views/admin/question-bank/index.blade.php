@extends('layouts.admin')

@section('title', 'Bộ câu hỏi — Hóa Thầy Đạt')
@section('page-title', 'Bộ câu hỏi')

@section('content')
<div class="page-header">
    <h2>Bộ câu hỏi</h2>
    <a href="{{ route('admin.question-bank.create') }}" class="btn btn-primary">+ Thêm câu hỏi</a>
</div>

<div class="card" id="qbListCard" data-bulk-tags-url="{{ route('admin.question-bank.bulk-tags') }}">
    <form method="GET" class="filters">
        @include('admin.partials.tag-select', [
            'mode' => 'filter-multi',
            'tags' => $tags,
            'selected' => $filterTagIds ?? [],
            'tagNone' => $filterTagNone ?? false,
            'tagMatch' => $filterTagMatch ?? 'and',
            'autoSubmit' => false,
            'showAll' => true,
            'showUntagged' => true,
        ])
        <div class="form-group">
            <label for="answer_type">Loại câu</label>
            <select id="answer_type" name="answer_type" onchange="this.form.submit()">
                <option value="">Tất cả loại</option>
                @foreach (['mc' => 'Trắc nghiệm', 'essay' => 'Tự luận', 'structured' => 'Phương trình'] as $val => $label)
                    <option value="{{ $val }}" @selected($filterAnswerType === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="q">Tìm kiếm</label>
            <input type="search" id="q" name="q" value="{{ $filterQuery }}" placeholder="Nội dung câu hỏi...">
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-secondary btn-sm">Lọc</button>
        </div>
        @if (($filterTagIds ?? []) !== [] || ($filterTagNone ?? false) || ($filterTagMatch ?? 'and') !== 'and' || $filterAnswerType || $filterQuery)
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="{{ route('admin.question-bank.index') }}" class="btn btn-secondary btn-sm">Xóa bộ lọc</a>
            </div>
        @endif
    </form>

    @if ($items->isEmpty())
        <div class="empty-state">
            Chưa có câu hỏi trong bộ.
            <a href="{{ route('admin.question-bank.create') }}">Thêm câu hỏi đầu tiên</a>
        </div>
    @else
    <div id="qbBulkBar" class="qq-bulk-bar qq-bulk-bar--idle">
        <span class="qq-bulk-count"><strong id="qbBulkCount">0</strong> câu đã chọn</span>
        <div class="qq-bulk-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-qb-bulk-action="tags" disabled>Đổi chủ đề</button>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="qq-col-check"><input type="checkbox" id="qbSelectAll" aria-label="Chọn tất cả"></th>
                    <th>Loại</th>
                    <th>Chủ đề</th>
                    <th>Nội dung</th>
                    <th>Điểm</th>
                    <th>Thời gian</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                <tr data-bank-item-id="{{ $item->id }}">
                    <td class="qq-col-check">
                        <input type="checkbox" class="qb-row-check" value="{{ $item->id }}" aria-label="Chọn câu hỏi">
                    </td>
                    <td>@php
                        echo match ($item->answer_type) {
                            'mc' => 'Trắc nghiệm',
                            'structured' => match ($item->input_mode) {
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
                            'tags' => $tags,
                            'selectedTags' => $item->tags,
                            'updateUrl' => route('admin.question-bank.update-tags', $item),
                            'itemId' => $item->id,
                        ])
                    </td>
                    <td>{!! Str::limit(strip_tags($item->content), 100) !!}</td>
                    <td>{{ $item->points }}</td>
                    <td>{{ $item->time_limit_seconds }}s</td>
                    <td class="actions">
                        <a href="{{ route('admin.question-bank.edit', $item) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.question-bank.destroy', $item) }}" onsubmit="return confirm('Xóa câu hỏi khỏi bộ? Câu hỏi trong quiz vẫn giữ nguyên.')">
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

<div id="qbBulkModal" class="qq-modal" hidden aria-hidden="true">
    <div class="qq-modal-backdrop" data-close-qb-bulk-modal></div>
    <div class="qq-modal-dialog qq-bulk-modal" role="dialog" aria-labelledby="qbBulkModalTitle">
        <header class="qq-modal-header">
            <h3 id="qbBulkModalTitle">Đổi chủ đề hàng loạt</h3>
            <button type="button" class="qq-modal-close" data-close-qb-bulk-modal aria-label="Đóng">×</button>
        </header>
        <div class="qq-modal-body">
            <div class="form-group">
                <label>Chọn chủ đề (có thể chọn nhiều)</label>
                @include('admin.partials.bulk-tag-picker', ['tags' => $tags, 'id' => 'qbBulkTagPicker', 'selectedIds' => []])
                <button type="button" class="tag-checklist-add" data-open-tag-modal-from-checklist>+ Thêm chủ đề</button>
            </div>
            <p id="qbBulkConfirmText" class="bulk-tag-confirm-text"></p>
        </div>
        <footer class="qq-modal-footer">
            <button type="button" class="btn btn-secondary" data-close-qb-bulk-modal>Hủy</button>
            <button type="button" class="btn btn-primary" id="btnQbBulkConfirm">Xác nhận</button>
        </footer>
    </div>
</div>
@endsection

@push('head')
@php $qbCss = public_path('htd-admin/css/quiz-questions.css'); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-questions.css') }}?v={{ file_exists($qbCss) ? filemtime($qbCss) : time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/question-tags-cell.js') }}?v={{ file_exists(public_path('js/question-tags-cell.js')) ? filemtime(public_path('js/question-tags-cell.js')) : time() }}"></script>
<script src="{{ asset('js/question-bank-list.js') }}?v={{ file_exists(public_path('js/question-bank-list.js')) ? filemtime(public_path('js/question-bank-list.js')) : time() }}"></script>
@endpush
