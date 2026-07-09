@php
    $paginator = $paginator ?? null;
    $itemLabel = $itemLabel ?? 'mục';
@endphp
@if ($paginator && $paginator->total())
    <div class="list-table-footer">
        <p class="list-table-footer__summary">
            Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} {{ $itemLabel }}
        </p>
        @if ($paginator->hasPages())
            <div class="list-table-footer__pagination">
                {{ $paginator->links('vendor.pagination.admin') }}
            </div>
        @endif
    </div>
@endif
