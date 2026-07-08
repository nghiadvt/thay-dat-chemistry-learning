@php
    $checklistId = $id ?? 'tag-checklist-' . uniqid();
    $selectedIds = array_map('intval', $selectedIds ?? []);
@endphp

<div class="tag-checklist" id="{{ $checklistId }}" data-tag-checklist role="group" aria-label="Chọn chủ đề">
    @forelse ($tags as $tag)
        <div class="tag-checklist-row"
             data-value="{{ $tag->id }}"
             data-label="{{ $tag->name }}"
             data-color="{{ $tag->color }}">
            <label class="tag-checklist-item">
                <input type="checkbox"
                       name="tag_ids[]"
                       value="{{ $tag->id }}"
                       @checked(in_array($tag->id, $selectedIds, true))>
                <span class="tag-checklist-dot" style="background: {{ $tag->color }}"></span>
                <span class="tag-checklist-label">{{ $tag->name }}</span>
            </label>
            <div class="tag-checklist-option-menu">
                <button type="button" class="tag-select-kebab" aria-label="Sửa chủ đề">⋮</button>
                <div class="tag-select-action-menu" hidden>
                    <button type="button" data-edit-tag="{{ $tag->id }}">Sửa tên và màu</button>
                </div>
            </div>
        </div>
    @empty
        <p class="tag-checklist-empty">Chưa có chủ đề nào.</p>
    @endforelse
</div>
