@extends('layouts.admin')

@section('title', 'Phòng chơi — Hóa Thầy Đạt')
@section('page-title', 'Phòng chơi')

@section('content')
<div class="page-header">
    <h2>Danh sách phòng</h2>
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">+ Tạo phòng mới</a>
</div>

<div class="card">
    @if ($sessions->isEmpty())
        <div class="empty-state">Chưa có phòng nào. <a href="{{ route('admin.sessions.create') }}">Tạo phòng mới</a></div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên phòng</th>
                    <th>PIN</th>
                    <th>QR</th>
                    <th>Quiz</th>
                    <th>Game</th>
                    <th>Giáo viên</th>
                    <th>Trạng thái</th>
                    <th>Bật</th>
                    <th>Tạo lúc</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $session)
                @php $joinUrl = url('/join/'.$session->pin); @endphp
                <tr class="{{ $session->is_active ? '' : 'row-inactive' }}">
                    <td><strong>{{ $session->name ?? 'Phòng '.$session->pin }}</strong></td>
                    <td><strong class="session-pin-cell">{{ $session->pin }}</strong></td>
                    <td class="session-qr-cell">
                        @if ($session->qr_url)
                        <a href="{{ url('/join/'.$session->pin) }}" target="_blank" rel="noopener" class="session-qr-link" title="{{ url('/join/'.$session->pin) }}">
                            <img
                                src="{{ $session->qr_url }}"
                                alt="QR PIN {{ $session->pin }}"
                                width="48"
                                height="48"
                                loading="lazy"
                            >
                        </a>
                        @else
                        <span class="text-muted" title="Mở trang host để tạo QR">—</span>
                        @endif
                    </td>
                    <td>{{ $session->quiz?->name ?? '—' }}</td>
                    <td>{{ $session->game?->name ?? '—' }}</td>
                    <td>{{ $session->host?->name ?? '—' }}</td>
                    <td><span class="badge badge-{{ $session->status }}">{{ $session->status }}</span></td>
                    <td>
                        @include('admin.partials.toggle-switch', [
                            'formAction' => route('admin.sessions.toggle-active', $session),
                            'checked' => $session->is_active,
                            'submitOnChange' => true,
                            'label' => 'Bật / tắt phòng',
                        ])
                    </td>
                    <td>{{ $session->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions-cell">
                        @if ($session->quiz_id)
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                data-quiz-preview="{{ $session->quiz_id }}"
                                data-quiz-name="{{ $session->quiz?->name }}"
                                data-quiz-trial="1"
                            >Chơi thử</button>
                        @endif
                        @if ($session->status === 'ended')
                            @if ($session->is_active)
                                <form
                                    method="POST"
                                    action="{{ route('admin.sessions.reset', $session) }}"
                                    class="inline-form"
                                    onsubmit="return confirm('Chơi lại với cùng PIN {{ $session->pin }}? Kết quả lần trước vẫn lưu trong báo cáo.');"
                                >
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Chơi lại</button>
                                </form>
                            @else
                                <span class="hint-inline" title="Bật công tắt phòng trước">Chơi lại (tắt)</span>
                            @endif
                            <a href="{{ route('admin.reports.show', $session) }}" class="btn btn-secondary btn-sm">Báo cáo</a>
                        @else
                            <a
                                href="{{ route('admin.sessions.show', $session) }}"
                                class="btn btn-primary btn-sm"
                                title="Vào phòng chơi — điều khiển game"
                            >Phòng chơi</a>
                        @endif
                        <a
                            href="{{ $joinUrl }}"
                            class="btn btn-secondary btn-sm session-join-link"
                            target="_blank"
                            rel="noopener"
                            title="{{ $joinUrl }}"
                        >Link HS</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($sessions->hasPages())
        <div class="pagination-wrap">{{ $sessions->links() }}</div>
    @endif
    @endif
</div>
@endsection

@push('head')
@php $qpCss = public_path('htd-admin/css/quiz-preview.css'); $qpV = file_exists($qpCss) ? filemtime($qpCss) : time(); @endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/quiz-preview.css') }}?v={{ $qpV }}">
@endpush

@push('scripts')
@php $qpJs = public_path('htd-admin/js/quiz-preview.js'); @endphp
<script src="{{ asset('htd-admin/js/quiz-preview.js') }}?v={{ file_exists($qpJs) ? filemtime($qpJs) : $qpV }}"></script>
@endpush
