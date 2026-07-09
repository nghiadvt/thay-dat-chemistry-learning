@php
    $inputId = $inputId ?? 'listSearch';
    $searchName = $searchName ?? 'q';
    $searchValue = trim((string) ($searchValue ?? ''));
    $searchPlaceholder = $searchPlaceholder ?? 'Tìm kiếm…';
    $preserveQuery = $preserveQuery ?? request()->except([$searchName, 'page']);
@endphp
<form method="GET" class="list-toolbar__search" data-admin-list-search>
    <label class="list-search" for="{{ $inputId }}">
        <svg class="list-search__icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">
            <path fill="currentColor" d="M8.5 3a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm6.36 10.14-2.55-2.55a.75.75 0 0 1 1.06-1.06l2.55 2.55a.75.75 0 1 1-1.06 1.06Z"/>
        </svg>
        <input
            type="search"
            id="{{ $inputId }}"
            name="{{ $searchName }}"
            value="{{ $searchValue }}"
            placeholder="{{ $searchPlaceholder }}"
            autocomplete="off"
        >
    </label>
    @foreach ($preserveQuery as $key => $value)
        @if (is_array($value))
            @foreach ($value as $item)
                @if (is_scalar($item) && $item !== '')
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endif
            @endforeach
        @elseif (is_scalar($value) && $value !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <button type="submit" class="btn btn-primary btn-sm">Tìm</button>
    <button type="button" class="btn btn-secondary btn-sm list-search__clear" data-search-clear @if ($searchValue === '') hidden @endif aria-label="Xóa tìm kiếm">×</button>
</form>
