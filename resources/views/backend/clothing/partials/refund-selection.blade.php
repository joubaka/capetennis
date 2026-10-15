<form method="POST" action="{{ route('backend.clothing.refunds.review', [$event, $order]) }}" data-clothing-refund-selection>
  @csrf
  <input type="hidden" name="request_token" value="{{ old('request_token', (string) str()->uuid()) }}">
  <input type="hidden" name="return_sort" value="{{ request('sort', 'rank') === 'name' ? 'name' : 'rank' }}">
  <input type="hidden" name="return_search" value="{{ request('search', '') }}">
  <input type="hidden" name="return_team" value="{{ request('team', '') }}">
  <input type="hidden" name="return_all_events" value="{{ request()->boolean('all_events') ? '1' : '0' }}">
  <h5>Select items to refund</h5>
  <p>All purchased items are shown below. Nothing is selected by default. Choose the items and quantities, then review the combined amount before confirming.</p>
  <p class="small text-muted">Selecting an item starts with all its remaining units; reduce the quantity for a partial refund.</p>
  <button class="btn btn-outline-primary mb-2" type="button" data-refund-select-all>Select all remaining items</button>
  <div class="table-responsive">
    <table class="table align-middle clothing-refund-lines">
      <thead><tr><th>Select</th><th>Item / size</th><th>Purchased / paid</th><th>Previously refunded or pending</th><th>Available</th><th>Quantity to refund</th></tr></thead>
      <tbody>@foreach($order->items as $item)@include('backend.clothing.partials.refund-item')@endforeach</tbody>
    </table>
  </div>
  <details class="mb-3"><summary>Advanced: refund a partial rand amount for one item</summary>
    <label for="refund-mode" class="form-label">Refund size</label>
    <select name="mode" id="refund-mode" class="form-select mb-2"><option value="quantity" @selected(old('mode', 'quantity') === 'quantity')>Selected items and quantities</option><option value="amount" @selected(old('mode') === 'amount')>Partial rand amount</option></select>
    <div data-refund-amount-fields hidden>
      <label for="refund-amount-item" class="form-label">Item</label><select id="refund-amount-item" name="item_id" class="form-select mb-2" disabled><option value="">Choose an item</option>@foreach($order->items as $item)<option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->item_name }} · {{ $item->size_name }}</option>@endforeach</select>
      <label for="refund-amount" class="form-label">Amount (R)</label><input id="refund-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" class="form-control" disabled>
      <p class="small text-muted">An amount refund leaves the quantity unchanged. The review shows all included and excluded items.</p>
    </div>
  </details>
  <label for="refund-method" class="form-label">Refund to original payer: {{ $order->user?->name }}</label>
  <select id="refund-method" name="method" class="form-select mb-2"><option value="wallet" @selected(old('method') === 'wallet')>Wallet — credit after confirmation</option>@if($order->payfast_paid && $order->payfast_pf_payment_id)<option value="payfast" @selected(old('method') === 'payfast')>PayFast — submit after confirmation</option>@endif</select>
  <label for="refund-reason" class="form-label">Reason</label><input id="refund-reason" name="reason" required maxlength="500" value="{{ old('reason') }}" class="form-control mb-2">
  @include('backend.clothing.partials.late-note', ['noteKey' => 'order-refund-late'])
  <p class="small text-muted">No money is moved when you review. Clothing refunds have no withdrawal fee.</p>
  <button class="btn btn-primary" type="submit">Review selected refund</button>
</form>
