@extends('layouts.admin')

@section('title', 'Cắt ảnh — Hóa Thầy Đạt')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-image-cropper.css')">
@endpush

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>{{ $sourceBoot ? 'Chỉnh sửa: '.$sourceBoot['name'] : 'Cắt nhiều ảnh nhỏ từ 1 ảnh lớn' }}</h2>
        <p class="page-header__meta">Tải ảnh lên, khoanh vùng cần cắt trên ảnh, đặt tên rồi bấm "Cắt &amp; Lưu tất cả". Công cụ khoanh vùng, xoay/lật ảnh và đường kẻ đo nằm ở cột bên trái.</p>
        <details class="ic-help">
            <summary>Xem hướng dẫn chi tiết &amp; phím tắt</summary>
            <p>Kéo chuột trên vùng trống để khoanh vùng cắt mới; kéo vào bên trong 1 vùng đã khoanh để di chuyển nó. Tick chọn nhiều vùng (hoặc giữ Shift khi bấm chọn trên ảnh) để thao tác cùng lúc — vùng đang chọn sẽ đổi màu cam và hiện 4 ô vuông ở góc, rê chuột tới góc rồi kéo để phóng to/thu nhỏ vùng đó.</p>
            <p>Phím tắt: <kbd>Ctrl+Z</kbd> hoàn tác, <kbd>Ctrl+Y</kbd> làm lại, <kbd>Delete</kbd> xóa vùng đang chọn, <kbd>Ctrl+click</kbd> hoặc <kbd>Shift+click</kbd> chọn nhiều vùng, <kbd>Ctrl+C</kbd>/<kbd>Ctrl+V</kbd> copy/paste, mũi tên để di chuyển vùng đang chọn.</p>
            <p>Nút "Tự động khoanh vùng" và "Click để khoanh vùng" chỉ hoạt động tốt với ảnh đã xóa nền (PNG nền trong suốt): mỗi object tách rời sẽ tự thành 1 khung, kiểm tra lại và chỉnh tay các khung dính nhau nếu có. "Click để khoanh vùng" là chế độ bật/tắt — bật lên rồi click vào từng object trên ảnh để khoanh riêng object đó, bấm lại nút để tắt chế độ.</p>
        </details>
    </div>
    <a href="{{ route('admin.image-cropper.index') }}" class="btn btn-secondary">← Danh sách ảnh</a>
</div>

@unless ($sourceBoot)
<div class="ic-upload-card" id="icUploadCard">
    <input type="file" id="icFileInput" accept="image/*" hidden>
    <label class="ic-upload-zone" for="icFileInput" id="icUploadZone">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        <span>Chọn ảnh, kéo thả hoặc <kbd>Ctrl</kbd>+<kbd>V</kbd> để dán</span>
        <small id="icUploadHint">Chấp nhận JPG, PNG, WebP...</small>
    </label>
</div>
@endunless

<div class="ic-workspace" id="icWorkspace" hidden>
    <aside class="card ic-tool-sidebar" id="icSidebar">
        <div class="ic-sidebar-head">
            <span class="ic-sidebar-title">Công cụ</span>
            <button type="button" class="ic-sidebar-toggle" id="icSidebarToggle" title="Thu gọn / mở rộng menu" aria-label="Thu gọn menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
        </div>

        <div class="ic-tool-groups" id="icToolGroups">
            <section class="ic-tool-group is-open" data-tool="regions">
                <button type="button" class="ic-tool-head" data-tool-toggle="regions" title="Vùng cắt">
                    <svg class="ic-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 2v14a2 2 0 0 0 2 2h14M18 22V8a2 2 0 0 0-2-2H2"/></svg>
                    <span class="ic-tool-label">Vùng cắt</span>
                    <svg class="ic-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="ic-tool-body">
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icAutoDetect" title="Tự động khoanh vùng từng object dựa trên vùng trong suốt của ảnh (dùng cho ảnh đã xóa nền)">Tự động khoanh vùng</button>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn ic-toggle-btn" id="icClickDetectToggle" title="Bật rồi click vào 1 object trên ảnh để tự động khoanh riêng vùng đó">Click để khoanh vùng</button>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn ic-toggle-btn" id="icAutoDetectFilterToggle" title="Lọc theo kích thước: chỉ tự động khoanh các object có width/height nằm trong khoảng mong muốn">Lọc kích thước</button>
                    <div class="ic-create-dims-panel ic-autodetect-filter-panel" id="icAutoDetectFilterPanel" hidden>
                        <label class="ic-filter-enable"><input type="checkbox" id="icFilterEnabled"> Lọc theo kích thước</label>
                        <span class="ic-filter-group">Width (px)
                            <input type="number" id="icFilterWMin" min="0" placeholder="min" title="Width tối thiểu (px). Để trống = không giới hạn">
                            <span class="ic-filter-dash">–</span>
                            <input type="number" id="icFilterWMax" min="0" placeholder="max" title="Width tối đa (px). Để trống = không giới hạn">
                        </span>
                        <span class="ic-filter-group">Height (px)
                            <input type="number" id="icFilterHMin" min="0" placeholder="min" title="Height tối thiểu (px). Để trống = không giới hạn">
                            <span class="ic-filter-dash">–</span>
                            <input type="number" id="icFilterHMax" min="0" placeholder="max" title="Height tối đa (px). Để trống = không giới hạn">
                        </span>
                        <label class="ic-filter-mode" title="Khi nhập cả điều kiện width lẫn height">Khi có cả 2 điều kiện:
                            <select id="icFilterMode">
                                <option value="and">Thỏa cả width và height</option>
                                <option value="or">Thỏa width hoặc height</option>
                            </select>
                        </label>
                    </div>

                    <div class="ic-tool-divider"></div>

                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icCreateByDims">+ Tạo khung theo kích thước</button>
                    <div class="ic-create-dims-panel" id="icCreateDimsPanel" hidden>
                        <label>X <input type="number" id="icDimsX" value="0"></label>
                        <label>Y <input type="number" id="icDimsY" value="0"></label>
                        <label>W <input type="number" id="icDimsW" min="0" value=""></label>
                        <label>H <input type="number" id="icDimsH" min="0" value=""></label>
                        <button type="button" class="btn btn-primary btn-sm" id="icDimsDone">Xong</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="icDimsCancel">Hủy</button>
                    </div>

                    <div class="ic-tool-divider"></div>

                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icDuplicateBox" disabled>Nhân bản vùng chọn</button>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icUndoBox">Xóa vùng cuối</button>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icClearBoxes">Xóa hết vùng</button>
                </div>
            </section>

            <section class="ic-tool-group" data-tool="transform">
                <button type="button" class="ic-tool-head" data-tool-toggle="transform" title="Ảnh gốc">
                    <svg class="ic-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    <span class="ic-tool-label">Ảnh gốc</span>
                    <svg class="ic-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="ic-tool-body">
                    <span class="ic-tool-field-label">Phóng to / thu nhỏ</span>
                    <div class="ic-zoom-group" role="group" aria-label="Phóng to / thu nhỏ ảnh">
                        <button type="button" class="ic-icon-btn" id="icZoomOut" title="Thu nhỏ">−</button>
                        <button type="button" class="ic-zoom-label" id="icZoomReset" title="Về 100%">100%</button>
                        <button type="button" class="ic-icon-btn" id="icZoomIn" title="Phóng to">+</button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icImageRotate90" title="Xoay ảnh 90°">
                        <svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M10 3a7 7 0 1 0 6.32 4H14.9A5.5 5.5 0 1 1 10 4.5v2.6l4-3.1-4-3.1V3Z"/></svg>
                        Xoay ảnh
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icImageFlip" title="Lật ngang ảnh">
                        <svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round" d="M10 2v16M4 6l3 2.5-3 2.5M16 6l-3 2.5 3 2.5"/></svg>
                        Lật ảnh
                    </button>
                </div>
            </section>

            <section class="ic-tool-group" data-tool="guides">
                <button type="button" class="ic-tool-head" data-tool-toggle="guides" title="Đường kẻ đo">
                    <svg class="ic-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h18v18H3z"/><path d="M8 3v4M13 3v4M18 3v4M3 8h4M3 13h4M3 18h4"/></svg>
                    <span class="ic-tool-label">Đường kẻ đo</span>
                    <svg class="ic-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="ic-tool-body">
                    <div class="ic-guide-settings" role="group" aria-label="Cài đặt đường kẻ đo">
                        <label class="ic-guide-setting">Màu <input type="color" id="icGuideColor" value="#f97316"></label>
                        <label class="ic-guide-setting">Độ đậm <input type="range" id="icGuideThickness" min="1" max="4" step="1" value="1"></label>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icClearGuides">Xóa đường kẻ</button>
                    <p class="ic-tool-hint">Kéo trên thước ngang/dọc quanh ảnh để tạo đường kẻ đo, kéo đường kẻ để di chuyển.</p>
                </div>
            </section>
        </div>

        @unless ($sourceBoot)
        <div class="ic-sidebar-foot">
            <button type="button" class="btn btn-secondary btn-sm ic-tool-btn" id="icChangeImage">Đổi ảnh khác</button>
        </div>
        @endunless
    </aside>

    <div class="ic-main-column">
        <div class="card ic-canvas-card">
            <div class="ic-stage-topbar">
                <span class="ic-hint">Đã khoanh: <strong id="icBoxCount">0</strong> vùng</span>
                <button type="button" class="btn btn-primary btn-sm" id="icSaveBtn" disabled>Cắt &amp; Lưu tất cả</button>
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
            </div>
            <p class="hint" id="icEmptyHint">Chưa có vùng nào — kéo chuột trên ảnh bên trái để khoanh vùng đầu tiên.</p>
            <ul class="ic-box-list" id="icBoxList"></ul>
        </div>
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
        <div class="form-group">
            <label class="ic-export-trim-toggle"><input type="checkbox" id="icExportTrim"> Tự động cắt bỏ khoảng trống / viền trắng dư thừa quanh mỗi vùng</label>
            <div class="ic-export-trim-tolerance" id="icExportTrimToleranceGroup" hidden>
                <label for="icExportTrimTolerance">Ngưỡng cắt (<span id="icExportTrimToleranceValue">12</span>/60)</label>
                <input type="range" id="icExportTrimTolerance" min="0" max="60" step="1" value="12">
                <span class="hint">Ngưỡng càng cao càng cắt mạnh (bao gồm cả viền gần trắng, bóng nhạt). Vùng nào trống hoàn toàn sẽ được giữ nguyên, không cắt.</span>
            </div>
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

    // Sidebar công cụ: accordion (chỉ mở 1 nhóm) + thu gọn thành thanh icon.
    // Đây chỉ là hành vi hiển thị/bố cục — không đụng tới logic cắt ảnh trong image-cropper.js.
    var sidebar = document.getElementById('icSidebar');
    var sidebarToggle = document.getElementById('icSidebarToggle');
    var toolGroups = document.getElementById('icToolGroups');
    var workspaceEl = document.getElementById('icWorkspace');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-collapsed');
            if (workspaceEl) workspaceEl.classList.toggle('sidebar-collapsed', sidebar.classList.contains('is-collapsed'));
        });
    }

    if (toolGroups && sidebar) {
        toolGroups.addEventListener('click', function (e) {
            var head = e.target.closest('[data-tool-toggle]');
            if (!head) return;
            var group = head.closest('.ic-tool-group');
            if (!group) return;

            if (sidebar.classList.contains('is-collapsed')) {
                sidebar.classList.remove('is-collapsed');
                if (workspaceEl) workspaceEl.classList.remove('sidebar-collapsed');
                Array.prototype.forEach.call(toolGroups.querySelectorAll('.ic-tool-group'), function (g) {
                    g.classList.toggle('is-open', g === group);
                });
                return;
            }

            var wasOpen = group.classList.contains('is-open');
            Array.prototype.forEach.call(toolGroups.querySelectorAll('.ic-tool-group'), function (g) {
                g.classList.remove('is-open');
            });
            if (!wasOpen) group.classList.add('is-open');
        });
    }
});
</script>
@endpush
