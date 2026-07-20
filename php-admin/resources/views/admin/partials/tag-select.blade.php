@php
    $mode = $mode ?? 'filter';
    $selectId = $id ?? 'tag-select-' . uniqid();
    $selected = $selected ?? ($mode === 'multi' || $mode === 'filter-multi' ? [] : '');
    $selectedMulti = is_array($selected) ? $selected : [];
    $selectedSingle = is_array($selected) ? '' : (string) $selected;
    $fieldName = $name ?? match ($mode) {
        'multi', 'filter-multi' => 'tag_ids[]',
        default => 'tag_id',
    };
    $autoSubmit = !empty($autoSubmit);
    $showAll = $showAll ?? in_array($mode, ['filter', 'filter-multi'], true);
    $showUntagged = $showUntagged ?? in_array($mode, ['filter', 'filter-multi'], true);
    $labelText = $label ?? 'Chủ đề';
    $tagNone = !empty($tagNone);
    $tagMatch = in_array($tagMatch ?? 'and', ['or', 'and'], true) ? ($tagMatch ?? 'and') : 'and';
    $usesCheckboxes = in_array($mode, ['multi', 'filter-multi'], true);
@endphp

<div class="tag-select-wrap form-group @if ($mode === 'filter-multi') tag-select-wrap--filter-multi @endif"
     id="{{ $selectId }}"
     data-tag-select
     data-mode="{{ $mode }}"
     data-name="{{ $fieldName }}"
     data-auto-submit="{{ $autoSubmit ? '1' : '0' }}"
     @if ($mode === 'filter-multi')
         data-tag-match="{{ $tagMatch }}"
     @endif
     @if ($usesCheckboxes)
         data-selected="{{ json_encode(array_map('intval', $selectedMulti)) }}"
     @else
         data-selected="{{ $selectedSingle }}"
     @endif
>
    @if ($labelText)
        <label for="{{ $selectId }}-trigger">{{ $labelText }}</label>
    @endif

    @if ($mode === 'filter')
        <input type="hidden" name="{{ $fieldName }}" id="{{ $selectId }}-value" value="{{ $selectedSingle }}">
    @elseif ($mode === 'filter-multi')
        <input type="hidden" name="tag_none" id="{{ $selectId }}-tag-none" value="{{ $tagNone ? '1' : '0' }}">
        <input type="hidden" name="tag_match" id="{{ $selectId }}-tag-match" value="{{ $tagMatch }}">
        <div id="{{ $selectId }}-hidden-inputs" class="tag-select-hidden-inputs">
            @foreach ($selectedMulti as $tid)
                <input type="hidden" name="tag_ids[]" value="{{ $tid }}">
            @endforeach
        </div>
    @else
        <div id="{{ $selectId }}-hidden-inputs" class="tag-select-hidden-inputs">
            @foreach ($selectedMulti as $tid)
                <input type="hidden" name="tag_ids[]" value="{{ $tid }}">
            @endforeach
        </div>
    @endif

    <button type="button" class="tag-select-trigger" id="{{ $selectId }}-trigger" aria-haspopup="listbox" aria-expanded="false">
        <span class="tag-select-dot" data-trigger-dot hidden></span>
        <span class="tag-select-trigger-label" data-trigger-label>—</span>
        <span class="tag-select-chevron" aria-hidden="true">▾</span>
    </button>

    <div class="tag-select-dropdown" hidden role="listbox">
        @if ($mode === 'filter-multi')
            <div class="tag-filter-match" data-tag-filter-match>
                <span class="tag-filter-match-label">Khớp khi chọn nhiều chủ đề</span>
                <button type="button"
                        class="tag-filter-match-btn @if ($tagMatch === 'and') is-active @endif"
                        data-tag-match="and">VÀ (AND)</button>
                <button type="button"
                        class="tag-filter-match-btn @if ($tagMatch === 'or') is-active @endif"
                        data-tag-match="or">HOẶC (OR)</button>
            </div>
        @endif
        @if ($showAll && in_array($mode, ['filter', 'filter-multi'], true))
            <button type="button" class="tag-select-option" data-value="" data-label="Tất cả chủ đề">
                <span class="tag-select-dot tag-select-dot--muted"></span>
                Tất cả chủ đề
            </button>
        @endif
        @if ($showUntagged && in_array($mode, ['filter', 'filter-multi'], true))
            <button type="button" class="tag-select-option" data-value="none" data-label="Chưa có chủ đề">
                <span class="tag-select-dot tag-select-dot--muted"></span>
                Chưa có chủ đề
            </button>
        @endif
        <div class="tag-select-options" data-tag-options>
            @foreach ($tags as $tag)
                <div class="tag-select-option-row"
                     data-value="{{ $tag->id }}"
                     data-label="{{ $tag->name }}"
                     data-color="{{ $tag->color }}">
                    @if ($usesCheckboxes)
                        <label class="tag-select-option tag-select-option--check">
                            <input type="checkbox"
                                   class="tag-select-checkbox"
                                   value="{{ $tag->id }}"
                                   @checked(in_array($tag->id, $selectedMulti, true))>
                            <span class="tag-select-dot" style="background:{{ $tag->color }}"></span>
                            <span class="tag-select-option-label">{{ $tag->name }}</span>
                        </label>
                    @else
                        <button type="button" class="tag-select-option">
                            <span class="tag-select-dot" style="background:{{ $tag->color }}"></span>
                            <span class="tag-select-option-label">{{ $tag->name }}</span>
                        </button>
                    @endif
                    <div class="tag-select-option-menu">
                        <button type="button" class="tag-select-kebab" aria-label="Thao tác chủ đề">⋮</button>
                        <div class="tag-select-action-menu" hidden>
                            <button type="button" data-edit-tag="{{ $tag->id }}">Sửa tên và màu</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="tag-select-add" data-open-tag-modal>+ Thêm chủ đề</button>
    </div>
</div>
