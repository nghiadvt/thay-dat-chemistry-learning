@extends('layouts.admin')

@section('title', 'Chi tiết góp ý — Hóa Thầy Đạt')
@section('page-title', 'Chi tiết góp ý')

@section('content')
<div class="page-header">
    <a href="{{ route('admin.feedback.index') }}" class="btn btn-secondary btn-sm">← Danh sách góp ý</a>
</div>

<div class="card feedback-detail-card">
    <div class="feedback-detail-header">
        <div class="feedback-user-cell feedback-user-cell--lg">
            @if ($item->user?->avatar_url)
                <img src="{{ $item->user->avatar_url }}" alt="" class="feedback-avatar feedback-avatar--img feedback-avatar--lg">
            @else
                <span class="feedback-avatar feedback-avatar--lg" aria-hidden="true">{{ $item->user?->initials }}</span>
            @endif
            <div>
                <h2 style="margin:0;font-size:1.1rem;">{{ $item->user?->name }}</h2>
                <div class="feedback-meta">ID #{{ $item->user_id }} · {{ $item->user?->role?->name }} · {{ $item->user?->email }}</div>
            </div>
        </div>
        <div class="feedback-detail-badges">
            <span class="feedback-priority feedback-priority--{{ $item->priority }}">{{ $item->priorityLabel() }}</span>
            <span class="feedback-status feedback-status--{{ $item->status }}">{{ $item->statusLabel() }}</span>
        </div>
    </div>

    <dl class="feedback-detail-meta">
        <div>
            <dt>Thời gian</dt>
            <dd>{{ $item->created_at?->format('d/m/Y H:i') }}</dd>
        </div>
        <div>
            <dt>Trang</dt>
            <dd><code>{{ $item->page_url }}</code></dd>
        </div>
        @if ($item->page_title)
        <div>
            <dt>Tiêu đề trang</dt>
            <dd>{{ $item->page_title }}</dd>
        </div>
        @endif
    </dl>

    <div class="feedback-detail-body">
        <h3>Nội dung góp ý</h3>
        <p>{{ $item->body }}</p>
    </div>

    @if ($item->attachments->isNotEmpty())
    <div class="feedback-detail-attachments">
        <h3>Ảnh đính kèm</h3>
        <div class="feedback-attachment-grid">
            @foreach ($item->attachments as $attachment)
                <a href="{{ $attachment->url }}" target="_blank" rel="noopener noreferrer" class="feedback-attachment-thumb">
                    <img src="{{ $attachment->url }}" alt="Ảnh đính kèm">
                </a>
            @endforeach
        </div>
    </div>
    @endif

    @if ($isAdmin)
    <form method="POST" action="{{ route('admin.feedback.update-status', $item) }}" class="feedback-status-form">
        @csrf
        @method('PATCH')
        <label for="status">Cập nhật trạng thái</label>
        <div class="feedback-status-form-row">
            <select id="status" name="status">
                <option value="new" @selected($item->status === 'new')>Mới</option>
                <option value="read" @selected($item->status === 'read')>Đã xem</option>
                <option value="done" @selected($item->status === 'done')>Hoàn thành</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Lưu</button>
        </div>
    </form>
    @endif
</div>
@endsection

@push('head')
@php $fbCss = public_path('css/feedback-admin.css'); @endphp
<link rel="stylesheet" href="{{ asset('css/feedback-admin.css') }}?v={{ file_exists($fbCss) ? filemtime($fbCss) : time() }}">
@endpush
