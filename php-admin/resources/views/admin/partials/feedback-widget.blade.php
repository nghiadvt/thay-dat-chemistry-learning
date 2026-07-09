<div id="feedbackWidget" class="feedback-widget" data-user-id="{{ auth()->id() }}" data-submit-url="{{ route('site-feedback.store') }}" hidden>
    <div class="feedback-widget-panel" id="feedbackWidgetPanel" hidden role="dialog" aria-modal="true" aria-labelledby="feedbackWidgetTitle">
        <header class="feedback-widget-panel-header">
            <div class="feedback-widget-panel-brand">
                <img src="{{ asset('images/chatbot.png') }}" alt="" class="feedback-widget-panel-avatar" width="44" height="44">
                <div>
                    <h2 id="feedbackWidgetTitle">Góp ý về website</h2>
                    <p>Chúng tôi luôn lắng nghe ý kiến của bạn</p>
                </div>
            </div>
            <button type="button" class="feedback-widget-panel-close" id="feedbackWidgetClose" aria-label="Đóng">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <form id="feedbackWidgetForm" class="feedback-widget-form" enctype="multipart/form-data">
            <div class="feedback-field">
                <label for="feedbackBody">Nội dung góp ý</label>
                <textarea id="feedbackBody" name="body" rows="4" required minlength="3" maxlength="5000" placeholder="Mô tả vấn đề hoặc đề xuất của bạn…"></textarea>
            </div>

            <div class="feedback-field">
                <span class="feedback-field-label" id="feedbackPriorityLabel">Độ ưu tiên</span>
                <div class="feedback-priority-group" role="radiogroup" aria-labelledby="feedbackPriorityLabel">
                    <label class="feedback-priority-pill">
                        <input type="radio" name="priority" value="low">
                        <span>Thấp</span>
                    </label>
                    <label class="feedback-priority-pill">
                        <input type="radio" name="priority" value="medium" checked>
                        <span>Trung bình</span>
                    </label>
                    <label class="feedback-priority-pill">
                        <input type="radio" name="priority" value="high">
                        <span>Cao</span>
                    </label>
                </div>
            </div>

            <div class="feedback-field">
                <span class="feedback-field-label">Ảnh đính kèm</span>
                <label class="feedback-upload-zone" for="feedbackImages">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    <span>Chọn ảnh, kéo thả hoặc <kbd>Ctrl</kbd>+<kbd>V</kbd> để dán</span>
                    <small>Tối đa 3 ảnh · mỗi ảnh ≤ 5MB · hỗ trợ chụp màn hình dán nhanh</small>
                </label>
                <input type="file" id="feedbackImages" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
                <div id="feedbackImagePreview" class="feedback-widget-previews"></div>
            </div>

            <p class="feedback-widget-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Trang hiện tại sẽ được gửi kèm khi bạn bấm Gửi.
            </p>

            <button type="submit" class="feedback-widget-submit" id="feedbackWidgetSubmit">Gửi góp ý</button>
        </form>
    </div>

    <div class="feedback-widget-fab-row" id="feedbackWidgetFabRow">
        <span class="feedback-widget-fab-label" id="feedbackWidgetFabLabel">💬 Góp ý về ứng dụng</span>
        <button type="button" class="feedback-widget-fab" id="feedbackWidgetFab" aria-label="Góp ý về website — kéo để di chuyển" title="Kéo để di chuyển · Bấm để góp ý" aria-expanded="false" aria-controls="feedbackWidgetPanel">
            <img src="{{ asset('images/chatbot.png') }}" alt="" class="feedback-widget-fab-img" width="56" height="56">
        </button>
    </div>
</div>
