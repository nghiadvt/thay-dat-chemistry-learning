@extends('layouts.admin')

@section('title', ($template ? 'Sửa' : 'Tạo').' mẫu thẻ — Hóa Thầy Đạt')
@section('body-class', 'admin-body--editor-tool')
@section('content-class', 'admin-content--tool')

@push('head')
<link rel="stylesheet" href="@vasset('htd-admin/css/shared.css')">
@php
    $cdeCss = public_path('htd-admin/css/card-editor.css');
    $cdeV = file_exists($cdeCss) ? filemtime($cdeCss) : time();
@endphp
<link rel="stylesheet" href="{{ asset('htd-admin/css/card-editor.css') }}?v={{ $cdeV }}">
<style>{!! \App\Support\CardFonts::editorFontFaceCss() !!}</style>
@endpush

@section('content')
<div class="cde-app" id="cardEditorApp">
    <header class="cde-header">
        <div class="cde-header__left">
            <a href="{{ route('admin.card-templates.index') }}" class="cde-back" title="Về danh sách">← Mẫu thẻ</a>
            <input type="text" class="cde-name" id="cdeName" value="{{ $templateBoot['name'] }}" maxlength="120" aria-label="Tên mẫu">
            <div class="cde-history">
                <button type="button" class="cde-icon-btn" id="cdeUndo" title="Hoàn tác (Ctrl+Z)" disabled>↶</button>
                <button type="button" class="cde-icon-btn" id="cdeRedo" title="Làm lại (Ctrl+Y)" disabled>↷</button>
            </div>
        </div>
        <div class="cde-header__tabs" role="tablist">
            <button type="button" class="cde-tab is-active" data-tab="design" role="tab">Thiết kế thẻ</button>
            <button type="button" class="cde-tab" data-tab="a4" role="tab">Sắp lên A4</button>
        </div>
        <div class="cde-header__right">
            <button type="button" class="cde-btn cde-btn--ghost" id="cdePreviewBtn">Xem trước</button>
            <button type="button" class="cde-btn cde-btn--primary" id="cdeSaveBtn">Lưu</button>
        </div>
    </header>

    <div class="cde-panel cde-panel--design" id="cdePanelDesign">
        <aside class="cde-sidebar">
            <div class="cde-sidebar__section">
                <div class="cde-sidebar__head">
                    <h3>Ảnh nền</h3>
                    <button type="button" class="cde-btn cde-btn--sm" id="cdeAddImageBtn">+ Thêm ảnh</button>
                    <input type="file" id="cdeImageInput" accept="image/png,image/jpeg,image/webp" hidden>
                </div>
                <ul class="cde-layer-list" id="cdeLayerList"></ul>
                <p class="cde-hint" id="cdeDpiWarn" hidden></p>
            </div>
            <details class="cde-advanced">
                <summary>Thông số in / Nâng cao</summary>
                <label>Số mặt
                    <select id="cdeSides">
                        <option value="1" @selected($templateBoot['sides'] === 1)>1 mặt</option>
                        <option value="2" @selected($templateBoot['sides'] === 2)>2 mặt</option>
                    </select>
                </label>
                <label>Bề rộng khung
                    <input type="number" id="cdeFrameW" step="0.1" min="20" max="210" value="{{ $templateBoot['frame_width_mm'] }}">
                </label>
                <label>Chiều cao khung
                    <input type="number" id="cdeFrameH" step="0.1" min="20" max="297" value="{{ $templateBoot['frame_height_mm'] }}">
                </label>
            </details>
        </aside>

        <main class="cde-stage">
            <div class="cde-side-bar" id="cdeSideBar" role="tablist">
                <button type="button" class="cde-side-btn is-active" data-side="front">Mặt trước</button>
                <button type="button" class="cde-side-btn" data-side="back" id="cdeSideBackBtn" @if($templateBoot['sides'] !== 2) hidden @endif>Mặt sau</button>
            </div>
            <div class="cde-chips" id="cdeChips" aria-label="Chèn dữ liệu"></div>
            <div class="cde-canvas-toolbar">
                <label class="cde-zoom-label">Phóng to
                    <input type="range" id="cdeCanvasZoom" min="80" max="200" step="5" value="120">
                    <span id="cdeCanvasZoomLabel">120%</span>
                </label>
            </div>
            <div class="cde-frame-wrap" id="cdeFrameWrap">
                <div class="cde-artboard" id="cdeArtboard">
                    <img class="cde-artboard__img" id="cdeArtboardImg" alt="" hidden>
                    <p class="cde-artboard__empty" id="cdeArtboardEmpty">Chưa có ảnh — bấm «+ Thêm ảnh» ở panel trái</p>
                    <div class="cde-card-frame" id="cdeCardFrame">
                        <div class="cde-frame-drag-handle" id="cdeFrameDragHandle" title="Giữ và kéo lên/xuống" role="button" tabindex="0"></div>
                        <div class="cde-frame__elements" id="cdeElements"></div>
                        <div class="cde-frame-handle cde-frame-handle--bl" title="Kéo góc để đổi chiều cao"></div>
                        <div class="cde-frame-handle cde-frame-handle--br" title="Kéo góc để đổi chiều cao"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="cde-panel cde-panel--a4" id="cdePanelA4" hidden>
        <div class="cde-a4-controls">
            <label>Bề rộng thẻ khi in
                <input type="range" id="cdeCardWidth" min="30" max="90" step="1" value="{{ ($templateBoot['layout']['a4']['cardWidthMm'] ?? 54) }}">
                <span id="cdeCardWidthLabel"></span>
            </label>
            <label>Lề trang
                <input type="number" id="cdeMargin" min="0" max="30" step="1" value="{{ ($templateBoot['layout']['a4']['marginMm'] ?? 8) }}">
            </label>
            <label>Khoảng cách
                <input type="number" id="cdeGap" min="0" max="20" step="1" value="{{ ($templateBoot['layout']['a4']['gapMm'] ?? 4) }}">
            </label>
            <button type="button" class="cde-btn" id="cdeAutoTileBtn">Tự động xếp</button>
            <button type="button" class="cde-btn cde-btn--primary" id="cdeA4PreviewBtn">Xem trước A4</button>
            <p class="cde-a4-info" id="cdeA4Info"></p>
        </div>
        <div class="cde-a4-preview" id="cdeA4Preview"></div>
    </div>

    <div class="cde-popover" id="cdePopover" hidden>
        <div class="cde-popover__chips" id="cdePopoverChips"></div>
        <label>Text <input type="text" id="cdePopText"></label>
        <label>Font
            <select id="cdePopFont"></select>
        </label>
        <label>Cỡ chữ
            <input type="range" id="cdePopSize" min="1" max="5" step="1">
            <span id="cdePopSizeLabel"></span>
        </label>
        <div class="cde-popover__row">
            <button type="button" class="cde-toggle" id="cdePopBold">B</button>
            <button type="button" class="cde-toggle" id="cdePopItalic">I</button>
            <button type="button" class="cde-toggle" id="cdePopUnderline">U</button>
        </div>
        <label>Màu chữ <input type="color" id="cdePopColor"></label>
        <label>Canh lề
            <select id="cdePopAlign">
                <option value="left">Trái</option>
                <option value="center">Giữa</option>
                <option value="right">Phải</option>
            </select>
        </label>
        <label>Nền <input type="color" id="cdePopBg"></label>
        <label>Độ mờ nền <input type="range" id="cdePopBgOp" min="0" max="100"></label>
        <label>Viền <input type="range" id="cdePopBorder" min="0" max="4" step="0.5"></label>
        <label>Bo góc <input type="range" id="cdePopRadius" min="0" max="20"></label>
        <button type="button" class="cde-btn cde-btn--danger" id="cdePopDelete">Xóa ô</button>
    </div>

    <div class="cde-modal" id="cdePreviewModal" hidden>
        <div class="cde-modal__backdrop" id="cdePreviewClose"></div>
        <div class="cde-modal__box">
            <button type="button" class="cde-modal__close" id="cdePreviewCloseBtn" aria-label="Đóng">×</button>
            <iframe id="cdePreviewFrame" title="Xem trước thẻ"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_BOOT = {
    template: @json($templateBoot),
    apiBase: @json(url('/')),
    routes: {
        store: @json(route('admin.card-templates.store')),
        update: @json($template ? route('admin.card-templates.update', $template) : null),
        index: @json(route('admin.card-templates.index')),
        preview: @json($template ? route('admin.card-templates.preview', $template) : null),
    },
};
</script>
<script src="@vasset('htd-admin/js/admin-boot.js')"></script>
<script src="@vasset('htd-admin/js/api.js')"></script>
<script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.27/dist/interact.min.js"></script>
@php
    $cdeJs = public_path('htd-admin/js/card-editor.js');
    $cdeJsV = file_exists($cdeJs) ? filemtime($cdeJs) : $cdeV;
@endphp
<script src="{{ asset('htd-admin/js/card-editor.js') }}?v={{ $cdeJsV }}"></script>
@endpush
