@if($paginator->hasPages())
<nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="{{ $label }} pages" @if($ajax ?? false) data-share-pagination @endif>
  <span class="text-muted small">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ $paginator->total() }} accounts</span>
  <div class="d-flex gap-2">
    @if($paginator->previousPageUrl())<a class="btn btn-outline-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" @if($ajax ?? false) data-share-page @endif>Previous</a>@endif
    @if($paginator->nextPageUrl())<a class="btn btn-outline-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" @if($ajax ?? false) data-share-page @endif>Next</a>@endif
  </div>
</nav>
@endif
