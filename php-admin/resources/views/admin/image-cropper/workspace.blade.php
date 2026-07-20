@extends('layouts.admin')

@section('title', 'Cắt ảnh — Hóa Thầy Đạt')
@section('page-title', 'Cắt ảnh')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-image-cropper.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>{{ $sourceBoot ? 'Chỉnh sửa: '.$sourceBoot['name'] : 'Cắt nhiều ảnh nhỏ từ 1 ảnh lớn' }}</h2>
        <p class="page-header__meta">Tải lên 1 ảnh, kéo chuột trên vùng trống để khoanh vùng cắt mới; kéo vào bên trong 1 vùng đã khoanh để di chuyển nó. Tick chọn nhiều vùng (hoặc giữ Shift khi bấm chọn trên ảnh) để thao tác cùng lúc — vùng đang chọn sẽ đổi màu cam và hiện 4 ô vuông ở góc, rê chuột tới góc rồi kéo để phóng to/thu nhỏ vùng đó. Đặt tên rồi bấm Cắt &amp; Lưu.</p>
        <p class="page-header__meta">Phím tắt: <kbd>Ctrl+Z</kbd> hoàn tác, <kbd>Ctrl+Y</kbd> làm lại, <kbd>Delete</kbd> xóa vùng đang chọn, <kbd>Ctrl+click</kbd> hoặc <kbd>Shift+click</kbd> chọn nhiều vùng, <kbd>Ctrl+C</kbd>/<kbd>Ctrl+V</kbd> copy/paste, mũi tên để di chuyển vùng đang chọn.</p>
        <p class="page-header__meta">Nút "Tự động khoanh vùng" và "Click để khoanh vùng" chỉ hoạt động tốt với ảnh đã xóa nền (PNG nền trong suốt): mỗi object tách rời sẽ tự thành 1 khung, kiểm tra lại và chỉnh tay các khung dính nhau nếu có. "Click để khoanh vùng" là chế độ bật/tắt — bật lên rồi click vào từng object trên ảnh để khoanh riêng object đó, bấm lại nút để tắt chế độ.</p>
    </div>
    <a href="{{ route('admin.image-cropper.index') }}" class="btn btn-secondary">← Danh sách ảnh</a>
</div>

@unless ($sourceBoot)
<div class="card ic-upload-card">
    <label class="form-group ic-upload-label">
        <span>Chọn ảnh gốc</span>
        <input type="file" id="icFileInput" accept="image/*">
        <span class="hint" id="icUploadHint">Chấp nhận JPG, PNG, WebP...</span>
    </label>
</div>
@endunless

<div class="ic-workspace" id="icWorkspace" hidden>
    <div class="card ic-canvas-card">
        <div class="ic-canvas-toolbar">
            <span class="ic-hint">Đã khoanh: <strong id="icBoxCount">0</strong> vùng</span>
            <div class="actions">
                <button type="button" class="btn btn-secondary btn-sm" id="icCreateByDims">+ Tạo khung</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icAutoDetect" title="Tự động khoanh vùng từng object dựa trên vùng trong suốt của ảnh (dùng cho ảnh đã xóa nền)">Tự động khoanh vùng</button>
                <button type="button" class="btn btn-secondary btn-sm ic-toggle-btn" id="icClickDetectToggle" title="Bật rồi click vào 1 object trên ảnh để tự động khoanh riêng vùng đó">Click để khoanh vùng</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icDuplicateBox" disabled>Nhân bản vùng chọn</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icUndoBox">Xóa vùng cuối</button>
                <button type="button" class="btn btn-secondary btn-sm" id="icClearBoxes">Xóa hết vùng</button>
                @unless ($sourceBoot)
                <button type="button" class="btn btn-secondary btn-sm" id="icChangeImage">Đổi ảnh khác</button>
                @endunless
            </div>
        </div>
        <div class="ic-create-dims-panel" id="icCreateDimsPanel" hidden>
            <label>X <input type="number" id="icDimsX" value="0"></label>
            <label>Y <input type="number" id="icDimsY" value="0"></label>
            <label>W <input type="number" id="icDimsW" min="0" value=""></label>
            <label>H <input type="number" id="icDimsH" min="0" value=""></label>
            <button type="button" class="btn btn-primary btn-sm" id="icDimsDone">Xong</button>
            <button type="button" class="btn btn-secondary btn-sm" id="icDimsCancel">Hủy</button>
        </div>
        <div class="ic-canvas-toolbar ic-canvas-toolbar--secondary">
            <div class="ic-zoom-group" role="group" aria-label="Phóng to / thu nhỏ ảnh">
                <button type="button" class="ic-icon-btn" id="icZoomOut" title="Thu nhỏ">−</button>
                <button type="button" class="ic-zoom-label" id="icZoomReset" title="Về 100%">100%</button>
                <button type="button" class="ic-icon-btn" id="icZoomIn" title="Phóng to">+</button>
            </div>
            <div class="ic-image-transform-group" role="group" aria-label="Xoay / lật ảnh gốc">
                <button type="button" class="btn btn-secondary btn-sm" id="icImageRotate90" title="Xoay ảnh 90°">
                    <svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M10 3a7 7 0 1 0 6.32 4H14.9A5.5 5.5 0 1 1 10 4.5v2.6l4-3.1-4-3.1V3Z"/></svg>
                    Xoay ảnh
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="icImageFlip" title="Lật ngang ảnh">
                    <svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round" d="M10 2v16M4 6l3 2.5-3 2.5M16 6l-3 2.5 3 2.5"/></svg>
                    Lật ảnh
                </button>
            </div>
            <div class="ic-guide-settings" role="group" aria-label="Cài đặt đường kẻ đo">
                <label class="ic-guide-setting">Màu <input type="color" id="icGuideColor" value="#f97316"></label>
                <label class="ic-guide-setting">Độ đậm <input type="range" id="icGuideThickness" min="1" max="4" step="1" value="1"></label>
                <button type="button" class="btn btn-secondary btn-sm" id="icClearGuides">Xóa đường kẻ</button>
            </div>
        </div>
        <div class="ic-canvas-outer">
            <div class="ic-ruler-row">
                <div class="ic-ruler-corner"></div>
                <div class="ic-ruler-h" id="icRulerH"><canvas id="icRulerHCanvas"></canvas></div>
            </div>
            <div class="ic-canvas-body-row">
                <div class="ic-ruler-v" id="icRulerV"><canvas id="icRulerVCanvas"></canvas></div>
                <div class="ic-canvas-wrap" id="icCanvasWrap">
                    <div class="ic-canvas-stage" id="icCanvasStage">
                        <canvas id="icCanvas"></canvas>
                        <div class="ic-guide-layer" id="icGuideLayer"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ic-boxes-card">
        <div class="ic-boxes-card-header">
            <h3>Danh sách vùng đã khoanh</h3>
            <button type="button" class="btn btn-primary btn-sm" id="icSaveBtn" disabled>Cắt &amp; Lưu tất cả</button>
        </div>
        <p class="hint" id="icEmptyHint">Chưa có vùng nào — kéo chuột trên ảnh bên trái để khoanh vùng đầu tiên.</p>
        <ul class="ic-box-list" id="icBoxList"></ul>
    </div>
</div>

<div class="card ic-results-card" id="icResultsCard" hidden>
    <h3>Ảnh đã lưu</h3>
    <ul class="ic-result-list" id="icResultList"></ul>
</div>

<div class="ic-region-modal" id="icRegionModal" hidden>
    <button type="button" class="ic-region-modal-backdrop" aria-label="Đóng"></button>
    <div class="ic-region-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="icRegionModalTitle">
        <button type="button" class="ic-region-modal-close" aria-label="Đóng">×</button>
        <h3 id="icRegionModalTitle"></h3>
        <div class="ic-region-modal-imgwrap">
            <img id="icRegionModalImg" src="" alt="">
        </div>
        <div class="ic-region-modal-controls">
            <button type="button" class="btn btn-secondary btn-sm" id="icModalRotate90" title="Xoay 90°">
                <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M10 3a7 7 0 1 0 6.32 4H14.9A5.5 5.5 0 1 1 10 4.5v2.6l4-3.1-4-3.1V3Z"/></svg>
                Xoay 90°
            </button>
            <label class="ic-region-modal-angle">
                Góc
                <input type="number" id="icModalRotateInput" min="0" max="359" step="1">
                <input type="range" id="icModalRotateRange" min="0" max="359" step="1">
            </label>
            <button type="button" class="btn btn-secondary btn-sm" id="icModalFlip" title="Lật ngang (đối chiếu gương)">
                <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M10 2v16M4 5l3 3-3 3M16 5l-3 3 3 3" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Lật ngang
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="icModalReset" title="Về góc/chiều ban đầu">
                <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M4 4v5h5M4.5 9A5.5 5.5 0 1 1 6 13" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Reset
            </button>
        </div>
    </div>
</div>

<div class="ic-export-modal" id="icExportModal" hidden>
    <button type="button" class="ic-export-modal-backdrop" aria-label="Đóng"></button>
    <div class="ic-export-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="icExportModalTitle">
        <button type="button" class="ic-export-modal-close" aria-label="Đóng">×</button>
        <h3 id="icExportModalTitle">Tùy chọn xuất ảnh</h3>
        <div class="form-group">
            <label for="icExportFormat">Định dạng ảnh</label>
            <select id="icExportFormat" class="list-filter-control">
                <option value="png">PNG</option>
                <option value="jpeg">JPEG</option>
                <option value="webp">WebP</option>
                <option value="avif">AVIF</option>
                <option value="svg">SVG (bọc ảnh raster)</option>
            </select>
        </div>
        <div class="form-group" id="icExportQualityGroup" hidden>
            <label for="icExportQuality">Chất lượng (<span id="icExportQualityValue">92</span>%)</label>
            <input type="range" id="icExportQuality" min="50" max="100" step="1" value="92">
        </div>
        <div class="form-group">
            <label for="icExportPreset">Mục đích (gợi ý DPI)</label>
            <select id="icExportPreset" class="list-filter-control">
                <option value="custom">Tùy chỉnh</option>
                <option value="logo">Logo</option>
                <option value="banner">Banner</option>
                <option value="hero">Hero</option>
            </select>
        </div>
        <div class="form-group">
            <label for="icExportDpi">DPI</label>
            <input type="number" id="icExportDpi" min="36" max="1200" value="96">
            <span class="hint" id="icExportDpiHint">Chỉ PNG mới nhúng được DPI thật vào file; các định dạng khác chỉ mang tính tham khảo.</span>
        </div>
        <div class="ic-export-modal-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="icExportCancel">Hủy</button>
            <button type="button" class="btn btn-primary btn-sm" id="icExportConfirm">Xác nhận</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/image-cropper.js')"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.ImageCropper) {
        window.ImageCropper.init({
            uploadUrl: @json(route('image-crop-sources.store')),
            regionsUrlTemplate: @json(url('/api/image-crop-sources/__ID__/regions')),
            regionDeleteUrlTemplate: @json(url('/api/image-crop-sources/__ID__/regions/__REGION__')),
            sourceBoot: @json($sourceBoot),
        });
    }
});
</script>
@endpush
