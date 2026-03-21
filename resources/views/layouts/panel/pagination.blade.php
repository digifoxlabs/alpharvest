@if ($paginator->hasPages())
    @php
        $startPage = max(1, $paginator->currentPage() - 1);
        $endPage = min($paginator->lastPage(), $paginator->currentPage() + 1);
    @endphp

    <nav class="pagination-shell" role="navigation" aria-label="Pagination Navigation">
        <div class="pagination-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} results
        </div>

        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="page-link is-disabled">Previous</span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @if ($startPage > 1)
                <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
                @if ($startPage > 2)
                    <span class="page-link is-disabled">...</span>
                @endif
            @endif

            @foreach (range($startPage, $endPage) as $page)
                @if ($page === $paginator->currentPage())
                    <span class="page-link is-active">{{ $page }}</span>
                @else
                    <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($endPage < $paginator->lastPage())
                @if ($endPage < $paginator->lastPage() - 1)
                    <span class="page-link is-disabled">...</span>
                @endif
                <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="page-link is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
