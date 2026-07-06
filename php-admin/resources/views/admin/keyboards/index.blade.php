@extends('layouts.admin')

@section('title', 'Bàn phím — Hóa Thầy Đạt')
@section('page-title', 'Bàn phím')

@section('content')
<div class="page-header">
    <h2>Danh sách bàn phím</h2>
    <a href="{{ route('admin.keyboards.create') }}" class="btn btn-primary">+ Tạo bàn phím</a>
</div>

<div class="card">
    @if ($keyboards->isEmpty())
        <div class="empty-state">Chưa có bàn phím. <a href="{{ route('admin.keyboards.create') }}">Tạo mới</a></div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Tên</th>
                    <th>Môn</th>
                    <th>Số hàng</th>
                    <th>Quiz dùng</th>
                    <th>Cập nhật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($keyboards as $keyboard)
                <tr>
                    <td class="kb-preview-cell">
                        @if ($keyboard->preview_url)
                            <button type="button"
                                class="kb-preview-thumb"
                                data-preview-src="{{ $keyboard->preview_url }}"
                                data-preview-name="{{ $keyboard->name }}"
                                title="Xem preview — {{ $keyboard->name }}">
                                <img src="{{ $keyboard->preview_url }}" alt="Preview {{ $keyboard->name }}" loading="lazy">
                            </button>
                        @else
                            <a href="{{ route('admin.keyboards.editor', $keyboard) }}" class="kb-preview-missing" title="Mở editor để tạo preview">
                                Chưa có
                            </a>
                        @endif
                    </td>
                    <td><strong>{{ $keyboard->name }}</strong></td>
                    <td>{{ $keyboard->subject }}</td>
                    <td>{{ count($keyboard->config['rows'] ?? []) }}</td>
                    <td>{{ $keyboard->quizzes_count }}</td>
                    <td>{{ $keyboard->updated_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.keyboards.edit', $keyboard) }}" class="btn btn-secondary btn-sm">Chỉnh sửa</a>
                        <form method="POST" action="{{ route('admin.keyboards.destroy', $keyboard) }}" onsubmit="return confirm('Xóa bàn phím này?')">
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

<div class="kb-preview-lightbox" id="kbPreviewLightbox" hidden>
    <button type="button" class="kb-preview-lightbox-backdrop" aria-label="Đóng"></button>
    <div class="kb-preview-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="kbPreviewLightboxTitle">
        <button type="button" class="kb-preview-lightbox-close" aria-label="Đóng">×</button>
        <h3 id="kbPreviewLightboxTitle"></h3>
        <img id="kbPreviewLightboxImg" src="" alt="">
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('htd-admin/js/admin-keyboards-index.js') }}"></script>
@endpush
