@if ($paginator->hasPages())
    <div class="lg-pager">
        @if ($paginator->onFirstPage())
            <span class="lg-muted">← Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">← Prev</a>
        @endif

        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next →</a>
        @else
            <span class="lg-muted">Next →</span>
        @endif
    </div>
@endif
