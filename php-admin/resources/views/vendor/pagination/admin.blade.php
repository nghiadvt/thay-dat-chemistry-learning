@if ($paginator->hasPages())
<nav class="admin-pagination" role="navigation" aria-label="Phân trang">
    <ul class="admin-pagination__list">
        @if ($paginator->onFirstPage())
            <li><span class="admin-pagination__link is-disabled" aria-disabled="true">« Trước</span></li>
        @else
            <li><a class="admin-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Trước</a></li>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <li><span class="admin-pagination__gap" aria-hidden="true">{{ $element }}</span></li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li><span class="admin-pagination__link is-active" aria-current="page">{{ $page }}</span></li>
                    @else
                        <li><a class="admin-pagination__link" href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <li><a class="admin-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau »</a></li>
        @else
            <li><span class="admin-pagination__link is-disabled" aria-disabled="true">Sau »</span></li>
        @endif
    </ul>
    <p class="admin-pagination__meta">
        Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        · {{ $paginator->total() }} mục
    </p>
</nav>
@endif
