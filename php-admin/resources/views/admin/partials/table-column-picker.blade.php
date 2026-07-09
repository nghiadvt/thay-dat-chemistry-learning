@php
    /** @var string $tableId */
    /** @var array<int, array{key: string, label: string, default?: bool}> $columns */
    $defaultVisible = collect($columns)
        ->filter(fn ($col) => ($col['default'] ?? true) && ($col['key'] ?? '') !== 'actions')
        ->pluck('key')
        ->values()
        ->all();
@endphp
<div class="table-column-picker" data-table-column-picker data-table-target="{{ $tableId }}" data-default-cols='@json($defaultVisible)'>
    <button
        type="button"
        class="table-column-picker__trigger"
        data-column-picker-toggle
        aria-haspopup="listbox"
        aria-expanded="false"
    >
        <span>Hiển thị cột</span>
        <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5H5.5z"/></svg>
    </button>
    <div class="table-column-picker__panel" data-column-picker-panel hidden>
        <p class="table-column-picker__title">Chọn cột hiển thị</p>
        <ul class="table-column-picker__list" role="listbox" aria-label="Cột bảng">
            @foreach ($columns as $column)
                @if (($column['key'] ?? '') === 'actions')
                    @continue
                @endif
                <li>
                    <label class="table-column-picker__option">
                        <input
                            type="checkbox"
                            value="{{ $column['key'] }}"
                            data-col-toggle="{{ $column['key'] }}"
                            @checked($column['default'] ?? true)
                        >
                        <span>{{ $column['label'] }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
    </div>
</div>
