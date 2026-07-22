@extends('layouts.admin')

@section('title', 'Bảng nguyên tố')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-periodic.css')">
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2>Bảng nguyên tố</h2>
        <p class="page-header__sub">Mỗi phiên bản là một cách bày bảng «Đọc nguyên tố» cho học sinh. Chỉ một phiên bản được chiếu tại một thời điểm.</p>
    </div>
    <details class="hdr-create">
        <summary class="btn btn-primary">+ Tạo phiên bản mới</summary>
        <form method="POST" action="{{ route('admin.periodic.store') }}" class="hdr-create__form">
            @csrf
            <input type="text" name="name" placeholder="Tên phiên bản (vd: Tháng 9 – cơ bản)" maxlength="120" required>
            <button type="submit" class="btn btn-primary btn-sm">Tạo & mở</button>
        </form>
    </details>
</div>

@if ($presets->isEmpty())
    <div class="empty-state">Chưa có phiên bản nào.</div>
@else
<div class="periodic-card-grid">
    @foreach ($presets as $preset)
    <article class="preset-card {{ $preset->is_live ? 'is-live' : '' }}" data-preset-id="{{ $preset->id }}">
        <div class="preset-card__thumb" data-thumb="{{ $preset->id }}"></div>
        <div class="preset-card__body">
            <div class="preset-card__title-row">
                <h3 class="preset-card__title">{{ $preset->name }}</h3>
            </div>
            <div class="preset-card__badges">
                @if ($preset->is_live)
                    <span class="preset-badge preset-badge--live">Đang chiếu cho học sinh</span>
                @endif
                @if ($preset->has_unpublished_changes)
                    <span class="preset-badge preset-badge--dirty">Có thay đổi chưa xuất bản</span>
                @endif
            </div>
            <p class="preset-card__meta">Sửa gần nhất: {{ $preset->updated_at?->format('d/m/Y H:i') }}</p>
            <div class="preset-card__actions">
                <a href="{{ route('admin.periodic.edit', $preset) }}" class="btn btn-secondary btn-sm">Mở &amp; sửa</a>
                @include('admin.partials.row-action-menu', [
                    'menuLabel' => '⋯',
                    'dataAttrs' => ['item-label' => $preset->name],
                    'actions' => array_values(array_filter([
                        ['key' => 'navigate', 'label' => ($preset->is_live ? 'Xuất bản lại' : 'Xuất bản cho học sinh'),
                         'href' => route('admin.periodic.publish', $preset), 'method' => 'POST',
                         'confirm' => 'Xuất bản «'.$preset->name.'»? Học sinh sẽ thấy phiên bản này ngay.'],
                        ['key' => 'navigate', 'label' => 'Nhân bản',
                         'href' => route('admin.periodic.duplicate', $preset), 'method' => 'POST'],
                        $preset->is_live ? null : ['key' => 'delete', 'label' => 'Xóa',
                         'href' => route('admin.periodic.destroy', $preset), 'method' => 'DELETE', 'danger' => true,
                         'confirm' => 'Xóa phiên bản «'.$preset->name.'»?'],
                    ])),
                ])
            </div>
        </div>
    </article>
    @endforeach
</div>
@endif

{{-- Modal phóng to: cùng mode «normal» như thumbnail (= HS tài khoản thường) --}}
<div class="periodic-thumb-modal" id="periodicThumbModal" hidden>
    <button type="button" class="periodic-thumb-modal__backdrop" data-periodic-modal-close aria-label="Đóng"></button>
    <div class="periodic-thumb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="periodicThumbModalTitle">
        <button type="button" class="periodic-thumb-modal__close" data-periodic-modal-close aria-label="Đóng">×</button>
        <h3 id="periodicThumbModalTitle">Xem bảng</h3>
        <p class="periodic-thumb-modal__hint">Ô Pro bị khoá · ô ẩn không hiện — đúng thứ học sinh account thường thấy.</p>
        <div class="periodic-thumb-modal__stage" id="periodicThumbModalStage"></div>
    </div>
</div>

<script>
  window.__PERIODIC_INDEX__ = {
    categories: @json($categories),
    catalog: @json($catalog),
    states: @json($states),
  };
</script>
@endsection

@push('scripts')
<script src="@vasset('js/periodic-layout.js')"></script>
<script src="@vasset('js/periodic-grid.js')"></script>
<script src="@vasset('js/periodic-index.js')"></script>
@endpush
