@extends('layouts.admin')

@section('title', 'Ôn trắc nghiệm — Hóa Thầy Đạt')

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Ôn trắc nghiệm</h2>
        <p class="page-subtitle">Khối → Chủ đề → Bài trắc nghiệm. Bấm vào tên chủ đề để mở/đóng danh sách bài, sửa nhanh ngay tại chỗ.</p>
    </div>
    <div class="page-header__actions">
        <details class="practice-grade-add-toggle">
            <summary class="btn btn-primary">+ Thêm khối</summary>
            <div class="practice-grade-add-panel">
                <form method="POST" action="{{ route('admin.practice.grades.store') }}" class="group-row-form">
                    @csrf
                    <input type="text" name="name" placeholder="VD: Khối 10, Ôn thi ĐGNL..." required maxlength="100">
                    <button type="submit" class="btn btn-primary btn-sm">Tạo khối</button>
                </form>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </details>
    </div>
</div>

@if ($grades->isEmpty())
    <div class="card">
        <div class="empty-state">Chưa có khối nào. Bấm «+ Thêm khối» ở trên để bắt đầu.</div>
    </div>
@else
    <nav class="practice-grade-tabs" aria-label="Chọn khối">
        @foreach ($grades as $grade)
            <div class="practice-grade-tab">
                <a href="{{ route('admin.practice.index', ['grade' => $grade->id]) }}"
                   class="{{ $activeGrade?->id === $grade->id ? 'active' : '' }}">{{ $grade->name }}</a>
                <details class="practice-grade-tab__menu">
                    <summary aria-label="Tùy chọn khối «{{ $grade->name }}»">⋮</summary>
                    <div class="practice-grade-tab__menu-body">
                        <form method="POST" action="{{ route('admin.practice.grades.update', $grade) }}" class="group-row-form">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $grade->name }}" required maxlength="100">
                            <button type="submit" class="btn btn-secondary btn-sm">Lưu</button>
                        </form>
                        <form method="POST" action="{{ route('admin.practice.grades.destroy', $grade) }}"
                              data-confirm="Xóa khối «{{ $grade->name }}»? Chỉ xóa được khi khối chưa có chủ đề."
                              data-confirm-danger="1">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Xóa khối</button>
                        </form>
                    </div>
                </details>
            </div>
        @endforeach
    </nav>

    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-top:0">Tạo chủ đề mới trong «{{ $activeGrade->name }}»</h3>
        <form method="POST" action="{{ route('admin.practice.topics.store') }}" class="group-row-form">
            @csrf
            <input type="hidden" name="practice_grade_id" value="{{ $activeGrade->id }}">
            <input type="text" name="name" placeholder="VD: Chương 1 - Halogen, Ôn thi giữa kỳ..." required maxlength="150">
            <button type="submit" class="btn btn-primary">Tạo chủ đề</button>
        </form>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    @forelse ($topics as $topic)
        <details class="practice-topic-accordion" @if ($openTopicId === $topic->id) open @endif>
            <summary class="practice-topic-accordion__summary">
                <span class="practice-topic-accordion__title">{{ $topic->name }}</span>
                <span class="page-subtitle">{{ $topic->quizzes->count() }} bài trắc nghiệm</span>
            </summary>
            <div class="practice-topic-accordion__body">
                <div class="practice-topic-accordion__toolbar">
                    <form method="POST" action="{{ route('admin.practice.topics.update', $topic) }}" class="group-row-form">
                        @csrf @method('PUT')
                        <input type="hidden" name="practice_grade_id" value="{{ $topic->practice_grade_id }}">
                        <input type="text" name="name" value="{{ $topic->name }}" required maxlength="150">
                        <button type="submit" class="btn btn-secondary btn-sm">Lưu tên</button>
                    </form>
                    <form method="POST" action="{{ route('admin.practice.topics.destroy', $topic) }}"
                          data-confirm="Xóa chủ đề «{{ $topic->name }}»? Chỉ xóa được khi chủ đề chưa có bài trắc nghiệm."
                          data-confirm-danger="1">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Xóa chủ đề</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.practice.topics.apply-class', $topic) }}" class="group-row-form practice-topic-accordion__apply-class">
                    @csrf
                    <select name="class_id" required>
                        <option value="">— Thêm nhanh 1 lớp cho cả chủ đề —</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}{{ $class->grade ? " (Khối {$class->grade})" : '' }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm">Thêm vào tất cả bài</button>
                </form>
                <p class="page-subtitle">Chỉ cộng thêm lớp vào mọi bài trong chủ đề này, không xóa lớp đã gán riêng cho từng bài.</p>

                @if ($topic->quizzes->isEmpty())
                    <div class="empty-state">Chưa có bài trắc nghiệm nào trong chủ đề này.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table practice-quiz-table">
                            <thead>
                                <tr>
                                    <th>Bật</th>
                                    <th>Pro</th>
                                    <th>Tên bài</th>
                                    <th>Câu hỏi</th>
                                    <th>Lớp</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topic->quizzes as $quiz)
                                    <tr>
                                        <td>
                                            @include('admin.partials.toggle-switch', [
                                                'formAction' => route('admin.practice.quizzes.toggle-active', ['practiceQuiz' => $quiz->id, 'grade' => $activeGrade->id, 'open_topic' => $topic->id]),
                                                'checked' => $quiz->is_active,
                                                'submitOnChange' => true,
                                                'label' => 'Bật/tắt bài',
                                            ])
                                        </td>
                                        <td>
                                            @include('admin.partials.toggle-switch', [
                                                'formAction' => route('admin.practice.quizzes.toggle-pro', ['practiceQuiz' => $quiz->id, 'grade' => $activeGrade->id, 'open_topic' => $topic->id]),
                                                'checked' => $quiz->requires_pro,
                                                'submitOnChange' => true,
                                                'label' => 'Bật/tắt yêu cầu Pro',
                                            ])
                                        </td>
                                        <td class="practice-quiz-table__name-cell">
                                            <form method="POST" action="{{ route('admin.practice.quizzes.update', ['practiceQuiz' => $quiz->id, 'grade' => $activeGrade->id, 'open_topic' => $topic->id]) }}" class="group-row-form">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $quiz->name }}" required maxlength="150">
                                                <button type="submit" class="btn btn-secondary btn-sm">Lưu</button>
                                            </form>
                                        </td>
                                        <td>{{ $quiz->question_bank_items_count }}</td>
                                        <td>
                                            <div class="tag-list">
                                                @forelse ($quiz->studentClasses as $class)
                                                    @include('admin.partials.class-chip', ['class' => $class])
                                                @empty
                                                    <span class="text-muted">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="{{ route('admin.practice.quizzes.show', $quiz) }}" class="btn btn-primary btn-sm">Sửa chi tiết</a>
                                            <form method="POST" action="{{ route('admin.practice.quizzes.destroy', $quiz) }}" style="display:inline"
                                                  data-confirm="Xóa bài «{{ $quiz->name }}»?" data-confirm-danger="1">
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

                <form method="POST" action="{{ route('admin.practice.quizzes.store', $topic) }}" class="group-row-form" style="margin-top:12px">
                    @csrf
                    <input type="text" name="name" placeholder="VD: Bài 1: Halogen" required maxlength="150">
                    <button type="submit" class="btn btn-primary btn-sm">+ Tạo bài trắc nghiệm</button>
                </form>
            </div>
        </details>
    @empty
        <div class="card">
            <div class="empty-state">Chưa có chủ đề nào trong khối «{{ $activeGrade->name }}».</div>
        </div>
    @endforelse
@endif
@endsection

@push('head')
<link rel="stylesheet" href="@vasset('htd-admin/css/practice.css')">
@endpush
