@php
    $panelId = $panelId ?? 'listFilterPanel';
    $activeCount = (int) ($activeCount ?? 0);
    $expanded = $expanded ?? ($activeCount > 0);
@endphp
<button
    type="button"
    class="list-toolbar__filter-toggle"
    data-filter-panel-toggle
    aria-expanded="{{ $expanded ? 'true' : 'false' }}"
    aria-controls="{{ $panelId }}"
>
    <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M2.5 4.75A.75.75 0 0 1 3.25 4h13.5a.75.75 0 0 1 .53 1.28l-5.03 5.03v4.19a.75.75 0 0 1-1.085.67l-2.5-1.25A.75.75 0 0 1 8 14.25v-3.94L2.72 5.28A.75.75 0 0 1 2.5 4.75Z"/></svg>
    <span>Bộ lọc</span>
    @if ($activeCount > 0)
        <span class="list-toolbar__badge">{{ $activeCount }}</span>
    @endif
</button>
