@extends('layouts.admin')

@section('title', 'Xóa khoảng trắng — Hóa Thầy Đạt')
@section('body-class', 'admin-body--editor-tool')
@section('content-class', 'admin-content--tool')

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-image-trimmer.css')">
@endpush

@section('content')
<div class="it-tool" id="itTool">
    <input type="file" id="itFileInput" accept="image/*" multiple hidden>

    {{-- Trạng thái ban đầu: chỉ có ô upload --}}
    <div class="it-empty-state" id="itEmptyState">
        <div class="it-empty-inner">
            <h2>Xóa khoảng trắng quanh ảnh</h2>
            <p class="it-empty-desc">Hệ thống tự dò viền nền trắng/gần trắng hoặc nền trong suốt quanh mép ảnh rồi cắt bỏ. Xử lý hoàn toàn trên máy bạn — không tải lên server.</p>
            <label class="it-upload-zone" for="itFileInput" id="itUploadZone">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                <span>Chọn ảnh, kéo thả hoặc <kbd>Ctrl</kbd>+<kbd>V</kbd> để dán</span>
                <small>Chọn được nhiều ảnh cùng lúc</small>
            </label>
        </div>
    </div>

    {{-- Workspace kiểu canvas: menu công cụ trái + ảnh giữa + filmstrip dưới --}}
    <div class="it-workspace" id="itWorkspace" hidden>
        <aside class="it-sidebar" id="itSidebar">
            <div class="it-sidebar-head">
                <span class="it-sidebar-title">Công cụ</span>
                <button type="button" class="it-sidebar-toggle" id="itSidebarToggle" title="Thu gọn / mở rộng menu" aria-label="Thu gọn menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
            </div>

            <div class="it-tool-groups" id="itToolGroups">
                <section class="it-tool-group is-open" data-tool="trim">
                    <button type="button" class="it-tool-head" data-tool-toggle="trim" title="Cắt viền trắng">
                        <svg class="it-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 2v14a2 2 0 0 0 2 2h14M18 22V8a2 2 0 0 0-2-2H2"/></svg>
                        <span class="it-tool-label">Cắt viền trắng</span>
                        <svg class="it-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="it-tool-body">
                        <label class="it-tool-field-label" for="itToleranceInput">Ngưỡng cắt (<span id="itToleranceValue">12</span>/60)</label>
                        <input type="range" id="itToleranceInput" min="0" max="60" step="1" value="12">
                        <p class="it-tool-hint">Tăng nếu nền ố/xám chưa cắt hết, giảm nếu bị cắt lấn vào nội dung — tự áp dụng cho ảnh đang chọn.</p>
                        <button type="button" class="btn btn-secondary btn-sm it-tool-btn" id="itManualEditBtn">Chỉnh tay vùng cắt…</button>
                    </div>
                </section>

                <section class="it-tool-group" data-tool="bg">
                    <button type="button" class="it-tool-head" data-tool-toggle="bg" title="Nền &amp; màu">
                        <svg class="it-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2.7s6.5 7.3 6.5 12a6.5 6.5 0 0 1-13 0c0-4.7 6.5-12 6.5-12Z"/></svg>
                        <span class="it-tool-label">Nền &amp; màu</span>
                        <svg class="it-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="it-tool-body" id="itBgModeGroup">
                        <div class="it-bgmode-options">
                            <label class="it-bgmode-option"><input type="radio" name="itBgMode" value="keep" checked> Giữ nguyên</label>
                            <label class="it-bgmode-option"><input type="radio" name="itBgMode" value="remove"> Xóa nền (trong suốt)</label>
                            <label class="it-bgmode-option"><input type="radio" name="itBgMode" value="recolor"> Đổi màu nội dung</label>
                        </div>
                        <div class="it-bgmode-controls" id="itBgModeControls" hidden>
                            <label class="it-tool-field-label" for="itBgTolerance">Ngưỡng xóa nền (<span id="itBgToleranceValue">12</span>/60)</label>
                            <input type="range" id="itBgTolerance" min="0" max="60" step="1" value="12">
                            <div class="it-colorpicker" id="itRecolorColor" hidden></div>
                        </div>
                        <p class="it-tool-hint">Tự áp dụng cho ảnh đang chọn.</p>
                    </div>
                </section>

                <section class="it-tool-group" data-tool="size">
                    <button type="button" class="it-tool-head" data-tool-toggle="size" title="Kích thước &amp; độ nét">
                        <svg class="it-tool-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 3l-7 7M21 3h-6m6 0v6M3 21l7-7M3 21h6m-6 0v-6"/></svg>
                        <span class="it-tool-label">Kích thước &amp; nét</span>
                        <svg class="it-tool-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="it-tool-body" id="itResizeGroup">
                        <label class="it-tool-field-label" for="itResizeScale">Kích thước (<span id="itResizeScaleValue">100</span>%)</label>
                        <input type="range" id="itResizeScale" min="10" max="300" step="5" value="100">
                        <label class="it-tool-field-label" for="itSharpenAmount">Làm nét (<span id="itSharpenAmountValue">0</span>)</label>
                        <input type="range" id="itSharpenAmount" min="0" max="100" step="5" value="0">
                        <p class="it-tool-hint">Dưới 100% thu nhỏ, trên 100% phóng to — kéo "Làm nét" để bù độ mờ khi phóng to. Tự áp dụng cho ảnh đang chọn.</p>
                    </div>
                </section>
            </div>

            <div class="it-sidebar-foot">
                <button type="button" class="btn btn-primary btn-sm it-foot-btn" id="itDownloadActiveBtn" title="Tải ảnh này">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4v12m0 0l-4-4m4 4 4-4M4 20h16"/></svg>
                    <span class="it-tool-label">Tải ảnh này</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm it-foot-btn" id="itDeleteActiveBtn" title="Xóa ảnh này khỏi danh sách">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/></svg>
                    <span class="it-tool-label">Xóa ảnh này</span>
                </button>
            </div>
        </aside>

        <div class="it-stage-area">
            <div class="it-stage-topbar">
                <div class="it-stage-meta" id="itStageMeta"></div>
                <div class="it-stage-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="itCompareBtn" title="Nhấn giữ để xem ảnh gốc, thả ra để xem ảnh đã xử lý">Giữ để so với gốc</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="itZoomBtn" title="Xem ảnh cỡ lớn">Phóng to</button>
                </div>
            </div>
            <div class="it-stage" id="itStage">
                <img id="itStageImg" src="" alt="Ảnh đang xử lý">
                <div class="it-stage-error" id="itStageError" hidden>Không đọc được ảnh này.</div>
                <span class="it-stage-flag" id="itStageFlag" hidden>Đang xem ảnh gốc</span>
            </div>
            <p class="hint it-progress" id="itProgressHint" hidden></p>
            <div class="it-filmstrip-wrap">
                <button type="button" class="it-filmstrip-add" id="itAddMoreBtn" title="Thêm ảnh (hoặc kéo thả / Ctrl+V)">+</button>
                <div class="it-filmstrip" id="itFilmstrip"></div>
            </div>
        </div>
    </div>

    {{-- Thao tác chung (batch) tạm ẩn — logic JS vẫn giữ nguyên, sẽ phát triển lại sau --}}
    <div class="it-legacy" hidden aria-hidden="true">
        <button type="button" id="itReprocessBtn" disabled>Xử lý lại ảnh đã chọn</button>
        <button type="button" id="itApplyBgBtn" disabled>Áp dụng cho ảnh đã chọn (<span id="itApplyBgCount">0</span>)</button>
        <button type="button" id="itApplyResizeBtn" disabled>Áp dụng cho ảnh đã chọn (<span id="itApplyResizeCount">0</span>)</button>
        <button type="button" id="itDownloadAllBtn" disabled>Tải tất cả</button>
        <button type="button" id="itDeleteSelectedBtn" disabled>Xóa ảnh đã chọn</button>
        <button type="button" id="itClearAllBtn" disabled>Xóa toàn bộ danh sách</button>
        <label><input type="checkbox" id="itSelectAll" checked> Chọn tất cả</label>
        <span id="itSelectedCount"></span>
        <div id="itResultsCard" hidden>
            <h3>Kết quả (<span id="itResultCount">0</span> ảnh)</h3>
            <ul id="itResultList"></ul>
        </div>
        <p id="itEmptyHint">Chưa có ảnh nào.</p>
    </div>
</div>

<div class="it-modal" id="itLightbox" hidden>
    <button type="button" class="it-modal-backdrop" aria-label="Đóng" id="itLightboxBackdrop"></button>
    <div class="it-modal-dialog it-modal-dialog--lightbox" role="dialog" aria-modal="true" aria-label="Xem ảnh cỡ lớn">
        <button type="button" class="it-modal-close" aria-label="Đóng" id="itLightboxClose">×</button>
        <button type="button" class="it-lightbox-nav it-lightbox-nav--prev" aria-label="Ảnh trước" id="itLightboxPrev">‹</button>
        <button type="button" class="it-lightbox-nav it-lightbox-nav--next" aria-label="Ảnh sau" id="itLightboxNext">›</button>
        <img id="itLightboxImg" src="" alt="">
        <div class="it-lightbox-caption" id="itLightboxCaption"></div>
    </div>
</div>

<div class="it-modal" id="itEditModal" hidden>
    <button type="button" class="it-modal-backdrop" aria-label="Đóng" id="itEditBackdrop"></button>
    <div class="it-modal-dialog it-modal-dialog--wide" role="dialog" aria-modal="true" aria-labelledby="itEditModalTitle">
        <button type="button" class="it-modal-close" aria-label="Đóng" id="itEditClose">×</button>
        <h3 id="itEditModalTitle">Chỉnh tay vùng cắt</h3>
        <p class="hint" id="itEditModeHint">Kéo 4 viền nét đứt màu cam để chỉnh vùng giữ lại — phần tối là phần sẽ bị cắt bỏ.</p>

        <div class="it-mode-tabs">
            <button type="button" class="btn btn-secondary btn-sm it-mode-tab is-active" data-edit-mode="crop">Cắt</button>
            <button type="button" class="btn btn-secondary btn-sm it-mode-tab" data-edit-mode="region">Khoanh vùng nền</button>
            <button type="button" class="btn btn-secondary btn-sm it-mode-tab" data-edit-mode="draw">Vẽ vách ngăn</button>
        </div>

        <div class="it-bgmode-group it-edit-bgmode-group" id="itEditBgModeGroup">
            <div class="it-bgmode-options">
                <label class="it-bgmode-option"><input type="radio" name="itEditBgMode" value="keep"> Giữ nguyên</label>
                <label class="it-bgmode-option"><input type="radio" name="itEditBgMode" value="remove"> Xóa nền</label>
                <label class="it-bgmode-option"><input type="radio" name="itEditBgMode" value="recolor"> Đổi màu</label>
            </div>
            <div class="it-bgmode-controls" id="itEditBgModeControls" hidden>
                <input type="range" id="itEditBgTolerance" min="0" max="60" step="1" value="12">
                <div class="it-colorpicker" id="itEditRecolorColor" hidden></div>
            </div>
        </div>

        <div class="it-edit-region-controls" id="itEditRegionControls" hidden>
            <button type="button" class="btn btn-secondary btn-sm" id="itEditClearRegions">Xóa tất cả vùng</button>
        </div>

        <div class="it-edit-draw-controls" id="itEditDrawControls" hidden>
            <label class="it-tool-field-label" for="itEditDrawSize">Cỡ nét (<span id="itEditDrawSizeValue">6</span>px)</label>
            <input type="range" id="itEditDrawSize" min="1" max="60" step="1" value="6">
            <div class="it-colorpicker" id="itEditDrawColor"></div>
            <button type="button" class="btn btn-secondary btn-sm" id="itEditUndoStroke">Hoàn tác nét vừa vẽ</button>
            <button type="button" class="btn btn-secondary btn-sm" id="itEditClearStrokes">Xóa tất cả nét vẽ</button>
        </div>

        <div class="it-edit-canvas-wrap">
            <canvas id="itEditCanvas"></canvas>
        </div>
        <div class="it-modal-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="itEditReset">Dùng lại vùng tự động</button>
            <button type="button" class="btn btn-secondary btn-sm" id="itEditCancel">Hủy</button>
            <button type="button" class="btn btn-primary btn-sm" id="itEditApply">Áp dụng</button>
        </div>
    </div>
</div>

<div class="it-modal" id="itDownloadModal" hidden>
    <button type="button" class="it-modal-backdrop" aria-label="Đóng" id="itDownloadBackdrop"></button>
    <div class="it-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="itDownloadModalTitle">
        <button type="button" class="it-modal-close" aria-label="Đóng" id="itDownloadClose">×</button>
        <h3 id="itDownloadModalTitle">Chọn ảnh để tải xuống</h3>
        <label class="it-download-select-all"><input type="checkbox" id="itDownloadSelectAll" checked> Chọn tất cả</label>
        <ul class="it-download-list" id="itDownloadList"></ul>
        <div class="it-modal-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="itDownloadCancel">Hủy</button>
            <button type="button" class="btn btn-primary btn-sm" id="itDownloadConfirm">Tải xuống (<span id="itDownloadCount">0</span> ảnh)</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="@vasset('js/image-trimmer.js')"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.ImageTrimmer) {
        window.ImageTrimmer.init({
            fileInputEl: '#itFileInput',
            uploadZoneEl: '#itUploadZone',
            toleranceInputEl: '#itToleranceInput',
            toleranceValueEl: '#itToleranceValue',
            bgModeGroupEl: '#itBgModeGroup',
            bgModeControlsEl: '#itBgModeControls',
            bgToleranceInputEl: '#itBgTolerance',
            bgToleranceValueEl: '#itBgToleranceValue',
            recolorColorEl: '#itRecolorColor',
            applyBgBtnEl: '#itApplyBgBtn',
            applyBgCountEl: '#itApplyBgCount',
            resizeScaleInputEl: '#itResizeScale',
            resizeScaleValueEl: '#itResizeScaleValue',
            sharpenAmountInputEl: '#itSharpenAmount',
            sharpenAmountValueEl: '#itSharpenAmountValue',
            applyResizeBtnEl: '#itApplyResizeBtn',
            applyResizeCountEl: '#itApplyResizeCount',
            reprocessBtnEl: '#itReprocessBtn',
            downloadAllBtnEl: '#itDownloadAllBtn',
            deleteSelectedBtnEl: '#itDeleteSelectedBtn',
            clearAllBtnEl: '#itClearAllBtn',
            selectAllEl: '#itSelectAll',
            selectedCountEl: '#itSelectedCount',
            progressHintEl: '#itProgressHint',
            resultsCardEl: '#itResultsCard',
            resultListEl: '#itResultList',
            resultCountEl: '#itResultCount',
            emptyHintEl: '#itEmptyHint',
            editModalEl: '#itEditModal',
            editCanvasEl: '#itEditCanvas',
            editBackdropEl: '#itEditBackdrop',
            editCloseBtnEl: '#itEditClose',
            editCancelBtnEl: '#itEditCancel',
            editResetBtnEl: '#itEditReset',
            editApplyBtnEl: '#itEditApply',
            editBgModeGroupEl: '#itEditBgModeGroup',
            editBgModeControlsEl: '#itEditBgModeControls',
            editBgToleranceInputEl: '#itEditBgTolerance',
            editRecolorColorEl: '#itEditRecolorColor',
            editModeHintEl: '#itEditModeHint',
            editRegionControlsEl: '#itEditRegionControls',
            editClearRegionsBtnEl: '#itEditClearRegions',
            editDrawControlsEl: '#itEditDrawControls',
            editDrawSizeInputEl: '#itEditDrawSize',
            editDrawSizeValueEl: '#itEditDrawSizeValue',
            editDrawColorEl: '#itEditDrawColor',
            editUndoStrokeBtnEl: '#itEditUndoStroke',
            editClearStrokesBtnEl: '#itEditClearStrokes',
            downloadModalEl: '#itDownloadModal',
            downloadListEl: '#itDownloadList',
            downloadSelectAllEl: '#itDownloadSelectAll',
            downloadCountEl: '#itDownloadCount',
            downloadConfirmBtnEl: '#itDownloadConfirm',
            downloadCancelBtnEl: '#itDownloadCancel',
            downloadCloseBtnEl: '#itDownloadClose',
            downloadBackdropEl: '#itDownloadBackdrop',
            lightboxEl: '#itLightbox',
            lightboxImgEl: '#itLightboxImg',
            lightboxBackdropEl: '#itLightboxBackdrop',
            lightboxCloseBtnEl: '#itLightboxClose',
            lightboxPrevBtnEl: '#itLightboxPrev',
            lightboxNextBtnEl: '#itLightboxNext',
            lightboxCaptionEl: '#itLightboxCaption',
            workspaceEl: '#itWorkspace',
            emptyStateEl: '#itEmptyState',
            sidebarEl: '#itSidebar',
            sidebarToggleEl: '#itSidebarToggle',
            toolGroupsEl: '#itToolGroups',
            stageEl: '#itStage',
            stageImgEl: '#itStageImg',
            stageMetaEl: '#itStageMeta',
            stageFlagEl: '#itStageFlag',
            stageErrorEl: '#itStageError',
            filmstripEl: '#itFilmstrip',
            addMoreBtnEl: '#itAddMoreBtn',
            compareBtnEl: '#itCompareBtn',
            zoomBtnEl: '#itZoomBtn',
            manualEditBtnEl: '#itManualEditBtn',
            downloadActiveBtnEl: '#itDownloadActiveBtn',
            deleteActiveBtnEl: '#itDeleteActiveBtn',
        });
    }
});
</script>
@endpush
