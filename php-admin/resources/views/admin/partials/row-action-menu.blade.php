@php
    /** @var array<int, array{key: string, label: string, danger?: bool, href?: string, method?: string, confirm?: string}> $actions */
    /** @var array<string, string> $dataAttrs */
    $menuLabel = $menuLabel ?? 'Hành động';
@endphp
<div class="row-action-menu" data-row-action-menu @foreach ($dataAttrs as $attr => $value) @if ($value !== null && $value !== '') data-{{ $attr }}="{{ e($value) }}" @endif @endforeach>
    <button
        type="button"
        class="row-action-menu__trigger"
        data-row-action-trigger
        aria-haspopup="menu"
        aria-expanded="false"
    >
        <span>{{ $menuLabel }}</span>
        <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5H5.5z"/></svg>
    </button>
    <div class="row-action-menu__panel" data-row-action-panel hidden role="menu">
        <ul class="row-action-menu__list">
            @foreach ($actions as $action)
                <li role="none">
                    <button
                        type="button"
                        role="menuitem"
                        class="row-action-menu__item{{ !empty($action['danger']) ? ' is-danger' : '' }}"
                        data-action="{{ $action['key'] }}"
                        @if (!empty($action['href'])) data-href="{{ $action['href'] }}" @endif
                        @if (!empty($action['method'])) data-method="{{ $action['method'] }}" @endif
                        @if (!empty($action['confirm'])) data-confirm="{{ $action['confirm'] }}" @endif
                    >{{ $action['label'] }}</button>
                </li>
            @endforeach
        </ul>
    </div>
</div>
