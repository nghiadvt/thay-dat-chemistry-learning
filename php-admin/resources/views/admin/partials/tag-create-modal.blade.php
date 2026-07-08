<div id="tagFormModal" class="tag-modal" hidden aria-hidden="true">
    <div class="tag-modal-backdrop" data-close-tag-modal></div>
    <div class="tag-modal-dialog" role="dialog" aria-labelledby="tagFormModalTitle">
        <header class="tag-modal-header">
            <h3 id="tagFormModalTitle">Thêm chủ đề mới</h3>
            <button type="button" class="tag-modal-close" data-close-tag-modal aria-label="Đóng">×</button>
        </header>
        <div class="tag-modal-body">
            <input type="hidden" id="tagFormId" value="">
            <div class="form-group">
                <label for="tagFormName">Tên chủ đề</label>
                <input type="text" id="tagFormName" maxlength="64" placeholder="VD: Hóa vô cơ, Lớp 10">
                <div id="tagFormError" class="field-error" hidden></div>
            </div>
            <div class="form-group">
                <label>Màu chủ đề</label>
                <div class="tag-color-palette" id="tagColorPalette">
                    @foreach (\App\Models\Tag::PRESET_COLORS as $i => $color)
                        <button type="button"
                            class="tag-color-swatch {{ $i === 0 ? 'is-selected' : '' }}"
                            data-color="{{ $color }}"
                            style="background:{{ $color }}"
                            aria-label="Màu {{ $color }}"></button>
                    @endforeach
                    <label class="tag-color-custom" title="Chọn màu tuỳ chỉnh">
                        <input type="color" id="tagFormCustomColor" value="#6366F1">
                        <span class="tag-color-custom-label">Tuỳ chỉnh</span>
                    </label>
                </div>
            </div>
        </div>
        <footer class="tag-modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-close-tag-modal>Hủy</button>
            <button type="button" class="btn btn-primary btn-sm" id="tagFormSubmit">Lưu chủ đề</button>
        </footer>
    </div>
</div>
