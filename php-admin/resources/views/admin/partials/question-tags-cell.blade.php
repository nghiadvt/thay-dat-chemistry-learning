@php
    $selectedTags = $selectedTags ?? collect();
    if (is_array($selectedTags)) {
        $selectedTags = collect($selectedTags);
    }
    $selectedIds = $selectedTags->pluck('id')->map(fn ($id) => (int) $id)->all();
    $cellId = 'qtags-' . ($itemId ?? uniqid());
@endphp

<div class="question-tags-cell"
     id="{{ $cellId }}"
     data-question-tags-cell
     data-update-url="{{ $updateUrl }}"
     data-selected-ids="{{ json_encode($selectedIds) }}">
    <div class="question-tags-cell-display" data-tags-display>
        @if ($selectedTags->isEmpty())
            <span class="tag-chip tag-chip--untagged">Chưa có chủ đề</span>
        @else
            <div class="tag-list tag-list--compact">
                @foreach ($selectedTags as $tag)
                    @include('admin.partials.tag-chip', ['tag' => $tag])
                @endforeach
            </div>
        @endif
    </div>
    <div class="question-tags-cell-actions">
        <button type="button"
                class="question-tags-kebab"
                data-tags-kebab
                aria-label="Sửa chủ đề"
                aria-haspopup="dialog"
                aria-expanded="false">⋮</button>
        <div class="question-tags-editor" data-tags-editor hidden>
            <p class="question-tags-editor-title">Chủ đề của câu hỏi</p>
            @include('admin.partials.tag-checklist', [
                'tags' => $tags,
                'selectedIds' => $selectedIds,
                'id' => $cellId . '-checklist',
            ])
            <button type="button" class="tag-checklist-add" data-open-tag-modal-from-checklist>+ Thêm chủ đề</button>
            <div class="question-tags-editor-actions">
                <button type="button" class="btn btn-primary btn-sm" data-tags-save>Lưu</button>
                <button type="button" class="btn btn-secondary btn-sm" data-tags-cancel>Hủy</button>
                <button type="button" class="btn btn-secondary btn-sm question-tags-clear" data-tags-clear>Bỏ hết</button>
            </div>
        </div>
    </div>
</div>
