{{-- Contract chung mọi trang: mỗi chip = ['label' => string, 'url' => string (link bỏ điều kiện đó)] --}}
@php
    /** @var array<int, array{label: string, url: string}> $chips */
    $chips = $chips ?? [];
    $clearRoute = $clearRoute ?? null;
    $searchChip = $searchChip ?? null;
@endphp
@if ($searchChip || $chips !== [])
    <div class="list-active-filters" aria-label="Bộ lọc đang áp dụng">
        @if ($searchChip)
            <a class="filter-chip" href="{{ $searchChip['url'] }}">
                Tìm: “{{ Str::limit($searchChip['label'], 32) }}”
                <span class="filter-chip__remove" aria-hidden="true">×</span>
            </a>
        @endif
        @foreach ($chips as $chip)
            <a class="filter-chip" href="{{ $chip['url'] }}">
                {{ $chip['label'] }}
                <span class="filter-chip__remove" aria-hidden="true">×</span>
            </a>
        @endforeach
    </div>
@endif
