@if($refund->relationLoaded('historicalImport') && $refund->historicalImport)
  @php($refundImport = $refund->historicalImport)
  <p class="small text-muted mb-1 mt-2">
    Historical refund #{{ $refundImport->source_refund_id }} copied from
    <a href="{{ $refundImport->source_snapshot['source_url'] }}" target="_blank" rel="noopener noreferrer">verified live history</a>.
    Recorded locally by {{ $refundImport->imported_by_name }} on {{ $refundImport->imported_at->format('d M Y H:i') }}.
    The original refund actors and dates above belong to the source record. Importing this evidence sent no payment.
  </p>
@endif
