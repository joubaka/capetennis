@if($pendingOriginalSourceRefunds->isNotEmpty())
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">PayFast refunds awaiting confirmation</h2>
    <p class="small text-muted">These withdrawn orders have saved original-source refund claims. Review confirmation or recovery before taking any further refund action.</p>
    @foreach($pendingOriginalSourceRefunds as $pendingOrder)
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom py-2">
        <span>{{ $pendingOrder->event?->name }} · Order #{{ $pendingOrder->id }} · {{ $pendingOrder->user?->name }} · R{{ number_format($pendingOrder->refund_net, 2) }}</span>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.team.withdrawal.refund.show', [$pendingOrder->event_id, $pendingOrder]) }}">Review original-source refund</a>
      </div>
    @endforeach
    <div class="mt-3">{{ $pendingOriginalSourceRefunds->links('pagination::bootstrap-5') }}</div>
  </div></div>
@endif
