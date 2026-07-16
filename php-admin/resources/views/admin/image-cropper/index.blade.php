@extends('layouts.admin')

@section('title', 'Cắt ảnh — Hóa Thầy Đạt')
@section('page-title', 'Cắt ảnh')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-image-cropper.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Cắt nhiều ảnh nhỏ từ 1 ảnh lớn</h2>
        <p class="page-header__meta">Tải lên 1 ảnh, kéo chuột trên vùng trống để khoanh vùng cắt mới; kéo vào bên trong 1 vùng đã khoanh để di chuyển nó. Tick chọn nhiều vùng (hoặc giữ Shift khi bấm chọn trên ảnh) để thao tác cùng lúc — vùng đang chọn sẽ đổi màu cam và hiện 4 ô vuông ở góc, rê chuột tới góc rồi kéo để phóng to/thu nhỏ vùng đó. Đặt tên rồi bấm Cắt &amp; Lưu. Ảnh cắt được lưu vào <code>storage/app/public/cropped-images</code>.</p>
        <p class="page-header__meta">Phím tắt: <kbd>Ctrl+Z</kbd> hoàn tác, <kbd>Ctrl+Y</kbd> làm lại, <kbd>Delete</kbd> xóa vùng đang chọn.</p>
    </div>
</div>

<div class="card ic-upload-card">
    <label class="form-group ic-upload-label">
        <span>Chọn ảnh gốc</span>
        <input type="file" id="icFileInput" accept="image/*">
        <span class="hint">Chấp nhận JPG, PNG, WebP...</span>
    </label>
</div>

<div class="ic-workspace" id="icWorkspace" hidden>
    <div class="card ic-canvas-card">
        <div class="ic-canvas-toolbar">
            <span class="ic-hint">Đã khoanh: <strong id="icBoxCount">0</strong> vùng</span>
            <div class="actions">
                <button type="button" class="btn btn-secondary btn-sm" id="icDuplicateBox" disabled>Nhân bản vùng chọn</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icUndoBox">Xóa vùng cuối</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icClearBoxes">Xóa hết vùng</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icChangeImage">Đổi ảnh khác</button>
            </div>
        </div>
        <div class="ic-canvas-wrap">
            <canvas id="icCanvas"></canvas>
        </div>
    </div>

    <div class="card ic-boxes-card">
        <h3>Danh sách vùng đã khoanh</h3>
        <p class="hint" id="icEmptyHint">Chưa có vùng nào — kéo chuột trên ảnh bên trái để khoanh vùng đầu tiên.</p>
        <ul class="ic-box-list" id="icBoxList"></ul>
        <div class="ic-actions">
            <button type="button" class="btn btn-primary" id="icSaveBtn" disabled>Cắt &amp; Lưu tất cả</button>
        </div>
    </div>
</div>

<div class="card ic-results-card" id="icResultsCard" hidden>
    <h3>Ảnh đã lưu</h3>
    <p class="hint">Thư mục: <code id="icResultFolder"></code></p>
    <ul class="ic-result-list" id="icResultList"></ul>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/image-cropper.js')"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.ImageCropper) {
        window.ImageCropper.init({
            saveUrl: @json(url('/api/image-crops')),
        });
    }
});
</script>
@endpush
