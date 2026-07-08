<header class="kbe-header" id="kbeHeader">
  <div class="kbe-header-left">
    <a href="{{ route('admin.keyboards.index') }}" class="kbe-logo" title="Về trang giáo viên">
      <span class="kbe-logo-icon" aria-hidden="true">⚗</span>
      <span class="kbe-logo-text">CHỈNH SỬA BÀN PHÍM</span>
    </a>
    <input type="text" class="kbe-name-input" id="kbeNameInput" value="{{ $keyboard->name }}" aria-label="Tên bàn phím">
    <div class="kbe-history-btns">
      <button type="button" class="kbe-icon-btn" id="kbeUndoBtn" title="Hoàn tác" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14L4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
      </button>
      <button type="button" class="kbe-icon-btn" id="kbeRedoBtn" title="Làm lại" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 14l5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
      </button>
    </div>
  </div>
  <div class="kbe-header-center">
    <div class="kbe-device-toggle" role="group" aria-label="Thiết bị xem trước">
      <button type="button" class="kbe-device-btn active" data-device="phone">Phone</button>
      <button type="button" class="kbe-device-btn" data-device="tablet">Tablet</button>
    </div>
    <label class="kbe-zoom-select-wrap">
      <span class="visually-hidden">Thu phóng</span>
      <select class="kbe-zoom-select" id="kbeZoomSelect" aria-label="Thu phóng">
        <option value="0.5">50%</option>
        <option value="0.75">75%</option>
        <option value="1" selected>100%</option>
        <option value="1.5">150%</option>
      </select>
    </label>
  </div>
  <div class="kbe-header-right">
    <button type="button" class="kbe-btn kbe-btn-ghost" id="kbePreviewBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Preview
    </button>
    <button type="button" class="kbe-btn kbe-btn-ghost" id="kbeTestBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/></svg>
      Test
    </button>
    <button type="button" class="kbe-btn kbe-btn-primary" id="kbeSaveBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save
    </button>
  </div>
</header>

<div class="kbe-body" id="kbeBody">
  <aside class="kbe-panel kbe-layout-panel" id="kbeLayoutPanel">
    <div class="kbe-panel-head">
      <h2>LAYOUT EDITOR</h2>
      <p>Chỉnh sửa hàng và kéo thả phím</p>
    </div>

    <div class="kbe-panel-section kbe-panel-section--rows">
      <div class="kbe-row-list" id="kbeRowList"></div>
      <button type="button" class="kbe-add-row-btn" id="kbeAddRowBtn">+ Thêm hàng mới</button>
    </div>

    <div class="kbe-panel-section kbe-panel-section--keys" id="kbeKeysSection">
      <div class="kbe-section-head">
        <h3>AVAILABLE KEYS</h3>
        <input type="search" class="kbe-search" id="kbeKeySearch" placeholder="Tìm phím…" aria-label="Tìm phím">
      </div>
      <p class="kbe-section-hint">Kéo thả hoặc bấm để thêm vào hàng đang chọn</p>
      <div class="kbe-key-library" id="kbeKeyLibrary"></div>
    </div>
  </aside>

  <main class="kbe-preview-area" id="kbePreviewArea">
    <div class="kbe-preview-stage" id="kbePreviewStage">
      <div class="kbe-device-wrap" id="kbePhoneWrap">
        <div class="kbe-device-frame" id="kbePhoneFrame">
          <div class="kbe-device-bezel">
            <div class="kbe-device-notch" aria-hidden="true"></div>
            <div class="kbe-device-camera" aria-hidden="true"></div>
            <div class="kbe-device-screen" id="kbePhoneScreen">
              <div class="kbe-phone-content" id="kbePhoneContent">
                <p class="kbe-phone-placeholder">Xem trước bàn phím</p>
              </div>
              <div class="kbe-phone-kb" id="kbePhoneKb"></div>
            </div>
            <div class="kbe-device-home" aria-hidden="true"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="kbe-validation-banner" id="kbeValidationBanner" hidden></div>
  </main>

  <aside class="kbe-panel kbe-props-panel" id="kbePropsPanel">
    <div class="kbe-props-scroll" id="kbePropsScroll">
      <div class="kbe-props-dynamic" id="kbePropsContent"></div>
    </div>
  </aside>
</div>

<!-- Row context menu -->
<div class="kbe-context-menu" id="kbeRowMenu" hidden>
  <button type="button" data-action="add-above">Thêm hàng phía trên</button>
  <button type="button" data-action="add-below">Thêm hàng phía dưới</button>
  <button type="button" data-action="duplicate">Nhân đôi hàng</button>
  <button type="button" data-action="hide">Ẩn hàng</button>
  <button type="button" data-action="lock">Khóa hàng</button>
  <button type="button" data-action="copy">Sao chép hàng</button>
  <button type="button" data-action="paste">Dán hàng</button>
  <button type="button" data-action="delete" class="danger">Xóa hàng</button>
</div>

<!-- Preview overlay -->
<div class="kbe-overlay" id="kbePreviewOverlay" hidden>
  <button type="button" class="kbe-overlay-close" id="kbePreviewClose" aria-label="Đóng">×</button>
  <div class="kbe-overlay-phone" id="kbeOverlayPhone"></div>
</div>

<!-- Test overlay -->
<div class="kbe-overlay kbe-test-overlay" id="kbeTestOverlay" hidden>
  <button type="button" class="kbe-overlay-close" id="kbeTestClose" aria-label="Đóng">×</button>
  <div class="kbe-test-wrap">
    <div class="kbe-test-output-wrap">
      <label>Output</label>
      <div class="kbe-test-output" id="kbeTestOutput"></div>
      <button type="button" class="kbe-btn kbe-btn-ghost kbe-test-clear" id="kbeTestClear">Xóa</button>
    </div>
    <div class="kbe-overlay-phone" id="kbeTestPhone"></div>
  </div>
</div>

<input type="file" id="kbeImportFile" accept="application/json,.json" hidden>

<div class="kbe-toast" id="kbeToast" hidden></div>
