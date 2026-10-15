@php $receipt = session('clothing_refund_completed'); @endphp
@if($receipt && (int) $receipt['region_id'] === (int) $region->id && (!$clothingEvent || (int) $receipt['event_id'] === (int) $clothingEvent->id))
<div class="modal fade" id="clothing-refund-completed" tabindex="-1" aria-labelledby="clothing-refund-completed-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="clothing-refund-completed-title">Refund completed</h5></div>
    <div class="modal-body">
      <p class="fs-5"><strong>R{{ number_format((float) $receipt['net'], 2) }}</strong> refunded to {{ $receipt['payer'] ?: 'the original payer' }} via {{ $receipt['method'] === 'wallet' ? 'wallet credit' : 'PayFast' }}.</p>
      <p>Order #{{ $receipt['order_id'] }} · Refund #{{ $receipt['id'] }} · Fee: R{{ number_format((float) $receipt['fee'], 2) }}</p>
      <ul>@foreach($receipt['items'] as $line)<li>{{ $line['name'] }} · {{ $line['size'] }} · {{ $line['quantity'] > 0 ? $line['quantity'].' units' : 'Amount only' }} · R{{ number_format((float) $line['amount'], 2) }}</li>@endforeach</ul>
      @if($receipt['method'] === 'payfast')<p class="small text-muted">PayFast confirmed the refund. Your payer’s bank may take time to reflect it.</p>@endif
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const element = document.getElementById('clothing-refund-completed');
  if (element && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(element, { backdrop: 'static', keyboard: false }).show();
});
</script>
@endif
