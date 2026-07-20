@extends('layouts.admin')

@section('title', 'Bài làm — '.$student->display_name)
@section('page-title', 'Chi tiết bài làm')

@php
    // 30 câu -> 6 cột x 5 hàng. Luôn cố gắng giữ khoảng 5 hàng.
    $gridColumns = max(1, (int) ceil(max(1, $summary['total']) / 5));
    $gradeLabels = ['gioi' => 'Giỏi', 'kha' => 'Khá', 'duoi-kha' => 'Dưới khá'];
@endphp

@push('head')
<style>
    .report-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .report-layout { grid-template-columns: minmax(0, 1fr); } }

    .report-q { border-left: 4px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: var(--card-bg, #fff); box-shadow: 0 1px 2px rgba(0,0,0,.06); }
    .report-q--dung { border-left-color: #16a34a; }
    .report-q--sai { border-left-color: #dc2626; }
    .report-q--chua-lam { border-left-color: #9ca3af; }
    .report-q__head { display: flex; gap: 8px; align-items: baseline; margin-bottom: 6px; }
    .report-q__no { font-weight: 700; color: #6b7280; min-width: 28px; }
    .report-q__content { flex: 1; }
    .report-q__answers { font-size: 13px; display: grid; grid-template-columns: 110px 1fr; gap: 2px 10px; margin-top: 8px; }
    .report-q__answers dt { color: #6b7280; }
    .report-q__answers dd { margin: 0; }
    .report-q__answers dd.is-wrong { color: #dc2626; }
    .report-q__answers dd.is-right { color: #16a34a; font-weight: 600; }

    .report-side { position: sticky; top: 12px; }
    .report-side dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; margin: 0 0 14px; font-size: 14px; }
    .report-side dt { color: #6b7280; }
    .report-side dd { margin: 0; font-weight: 700; }

    .report-grid { display: grid; gap: 6px; grid-template-columns: repeat({{ $gridColumns }}, minmax(0, 1fr)); }
    .report-cell { aspect-ratio: 1; display: grid; place-items: center; border: 2px solid #9ca3af; border-radius: 6px; font-size: 12px; font-weight: 700; color: #374151; text-decoration: none; }
    .report-cell--dung { border-color: #16a34a; color: #16a34a; }
    .report-cell--sai { border-color: #dc2626; color: #dc2626; }
    .report-cell--chua-lam { border-color: #9ca3af; color: #9ca3af; }

    .report-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; margin-top: 10px; color: #6b7280; }
    .report-legend span::before { content: ''; display: inline-block; width: 10px; height: 10px; border: 2px solid; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
    .report-legend .lg-dung::before { border-color: #16a34a; }
    .report-legend .lg-sai::before { border-color: #dc2626; }
    .report-legend .lg-chua::before { border-color: #9ca3af; }

    .grade-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 2px solid; }
    .grade-pill--gioi { border-color: #16a34a; color: #16a34a; }
    .grade-pill--kha { border-color: #f59e0b; color: #b45309; }
    .grade-pill--duoi-kha { border-color: #dc2626; color: #dc2626; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>{{ $summary['title'] }}</h2>
        <p class="page-header__meta">
            {{ $student->display_name }} (<code>{{ $student->username }}</code>) ·
            {{ $summary['played_at']?->format('d/m/Y H:i') ?? '—' }}
        </p>
    </div>
    <a href="{{ $student->class_id ? route('admin.students.classes.show', $student->class_id) : route('admin.students.index') }}" class="btn">Quay lại</a>
</div>

<div class="report-layout">
    <div class="card">
        @forelse ($rows as $row)
            <div class="report-q report-q--{{ $row['status'] }}">
                <div class="report-q__head">
                    <span class="report-q__no">{{ $row['position'] }}.</span>
                    <div class="report-q__content">{!! $row['question']?->content ?? '<em>(câu hỏi đã bị xóa)</em>' !!}</div>
                </div>
                <dl class="report-q__answers">
                    <dt>Học sinh chọn</dt>
                    <dd class="{{ $row['status'] === 'dung' ? 'is-right' : ($row['status'] === 'sai' ? 'is-wrong' : '') }}">
                        {{ $row['student_answer_text'] }}
                        @if ($row['status'] === 'chua-lam')<em>(chưa làm)</em>@endif
                    </dd>
                    <dt>Đáp án đúng</dt>
                    <dd class="is-right">{{ $row['correct_answer_text'] }}</dd>
                </dl>
            </div>
        @empty
            <p>Không có câu hỏi nào trong bài này.</p>
        @endforelse
    </div>

    <aside class="card report-side">
        <h3 style="margin-top:0">Tổng quan</h3>
        <dl>
            <dt>Bài</dt><dd>{{ $summary['title'] }}</dd>
            <dt>Số câu đúng</dt><dd>{{ $summary['correct'] }}/{{ $summary['total'] }} ({{ $summary['percent'] }}%)</dd>
            <dt>Xếp loại</dt>
            <dd><span class="grade-pill grade-pill--{{ $summary['grade'] }}">{{ $gradeLabels[$summary['grade']] }}</span></dd>
            <dt>Điểm</dt><dd>{{ $summary['score'] }}</dd>
            @if ($summary['rank'])
                <dt>Hạng</dt><dd>#{{ $summary['rank'] }}</dd>
            @endif
            <dt>Thời gian</dt><dd>{{ $summary['duration'] }}</dd>
        </dl>

        <h3>Bản đồ câu trả lời</h3>
        <div class="report-grid">
            @foreach ($rows as $row)
                <div class="report-cell report-cell--{{ $row['status'] }}"
                     title="Câu {{ $row['position'] }}: {{ ['dung' => 'đúng', 'sai' => 'sai', 'chua-lam' => 'chưa làm'][$row['status']] }}">
                    {{ $row['position'] }}
                </div>
            @endforeach
        </div>
        <div class="report-legend">
            <span class="lg-dung">Đúng</span>
            <span class="lg-sai">Sai</span>
            <span class="lg-chua">Chưa làm</span>
        </div>
    </aside>
</div>
@endsection
