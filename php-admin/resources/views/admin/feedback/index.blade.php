@extends('layouts.admin')

@section('title', 'Góp ý — Hóa Thầy Đạt')
@section('page-title', 'Góp ý về website')

@section('content')
<div class="page-header">
    <h2>{{ $isAdmin ? 'Tất cả góp ý' : 'Góp ý của tôi' }}</h2>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div class="form-group">
            <label for="priority">Độ ưu tiên</label>
            <select id="priority" name="priority">
                <option value="">Tất cả</option>
                <option value="high" @selected(request('priority') === 'high')>Cao</option>
                <option value="medium" @selected(request('priority') === 'medium')>Trung bình</option>
                <option value="low" @selected(request('priority') === 'low')>Thấp</option>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Trạng thái</label>
            <select id="status" name="status">
                <option value="">Tất cả</option>
                <option value="new" @selected(request('status') === 'new')>Mới</option>
                <option value="read" @selected(request('status') === 'read')>Đã xem</option>
                <option value="done" @selected(request('status') === 'done')>Hoàn thành</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Lọc</button>
        @if (request()->hasAny(['priority', 'status']))
            <a href="{{ route('admin.feedback.index') }}" class="btn btn-secondary btn-sm">Xóa bộ lọc</a>
        @endif
    </form>

    @if ($feedback->isEmpty())
        <div class="empty-state">Chưa có góp ý nào.</div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    @if ($isAdmin)
                        <th>Giáo viên</th>
                    @endif
                    <th>Trang</th>
                    <th>Ưu tiên</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($feedback as $item)
                <tr>
                    <td>{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                    @if ($isAdmin)
                        <td>
                            <div class="feedback-user-cell">
                                @if ($item->user?->avatar_url)
                                    <img src="{{ $item->user->avatar_url }}" alt="" class="feedback-avatar feedback-avatar--img">
                                @else
                                    <span class="feedback-avatar" aria-hidden="true">{{ $item->user?->initials }}</span>
                                @endif
                                <div>
                                    <strong>{{ $item->user?->name }}</strong>
                                    <div class="feedback-meta">#{{ $item->user_id }} · {{ $item->user?->role?->name }}</div>
                                </div>
                            </div>
                        </td>
                    @endif
                    <td>
                        <div class="feedback-page-cell">
                            <code>{{ $item->page_url }}</code>
                            @if ($item->page_title)
                                <div class="feedback-meta">{{ $item->page_title }}</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <span class="feedback-priority feedback-priority--{{ $item->priority }}">{{ $item->priorityLabel() }}</span>
                    </td>
                    <td>
                        <span class="feedback-status feedback-status--{{ $item->status }}">{{ $item->statusLabel() }}</span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('admin.feedback.show', $item) }}" class="btn btn-secondary btn-sm">Chi tiết</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $feedback->links() }}</div>
    @endif
</div>
@endsection

@push('head')
@php $fbCss = public_path('css/feedback-admin.css'); @endphp
<link rel="stylesheet" href="{{ asset('css/feedback-admin.css') }}?v={{ file_exists($fbCss) ? filemtime($fbCss) : time() }}">
@endpush
