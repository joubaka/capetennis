@php
  $restoreRefund = (string) old('item_id') === (string) $item->id;
  $remainingCents = \App\Services\Clothing\ClothingRefundService::cents($item->line_total) - $item->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true))->sum(fn ($entry) => \App\Services\Clothing\ClothingRefundService::cents($entry->amount));
@endphp
<p class="small mt-2 mb-2">{{ $item->item_name ?: 'Clothing item' }} · R{{ number_format(max(0, $remainingCents) / 100, 2) }} available · {{ $item->remaining_quantity }} units available.</p>
@if($remainingCents > 0)
<form method="POST" action="{{ route('backend.clothing.refunds.store', [$event, $order]) }}" class="border rounded p-2 mb-2">
  @csrf
  <input type="hidden" name="request_token" value="{{ old('item_id') == $item->id ? old('request_token', (string) str()->uuid()) : (string) str()->uuid() }}">
  <input type="hidden" name="item_id" value="{{ $item->id }}">
  <label class="form-label" for="{{ $formKey }}-mode">Refund size</label>
  <select class="form-select mb-2" name="mode" id="{{ $formKey }}-mode" data-refund-mode>
    <option value="full" @selected($restoreRefund && old('mode') === 'full')>Full remaining item amount</option>
    <option value="quantity" @selected($restoreRefund && old('mode') === 'quantity')>Selected quantity</option>
    <option value="amount" @selected($restoreRefund && old('mode') === 'amount')>Partial rand amount</option>
  </select>
  <div data-refund-field="quantity" hidden><label class="form-label" for="{{ $formKey }}-qty">Quantity</label><input class="form-control mb-2" id="{{ $formKey }}-qty" name="items[{{ $item->id }}]" type="number" min="1" max="{{ $item->remaining_quantity }}" value="{{ $restoreRefund ? old('items.'.$item->id, 1) : 1 }}" disabled></div>
  <div data-refund-field="amount" hidden><label class="form-label" for="{{ $formKey }}-amount">Amount (R)</label><input class="form-control mb-2" id="{{ $formKey }}-amount" name="amount" value="{{ $restoreRefund ? old('amount') : '' }}" type="number" min="0.01" step="0.01" max="{{ number_format($remainingCents / 100, 2, '.', '') }}" disabled></div>
  <p class="small text-muted">Quantity refunds use the original item price, capped at its remaining amount. A rand refund leaves the quantity unchanged. Full refunds use all remaining units and money.</p>
  <label class="form-label" for="{{ $formKey }}-method">Refund to original payer</label>
  <select name="method" class="form-select mb-2" id="{{ $formKey }}-method"><option value="wallet" @selected($restoreRefund && old('method') === 'wallet')>Wallet — credit now</option>@if($order->payfast_paid && $order->payfast_pf_payment_id)<option value="payfast" @selected($restoreRefund && old('method') === 'payfast')>PayFast — submit refund now</option>@endif</select>
  <p class="small text-muted">PayFast refunds are submitted for the original payer. If PayFast requires a bank payout, provide the original payer’s bank details below. An uncertain provider response keeps the amount reserved until reconciled.</p>
  <details class="mb-2" data-refund-bank hidden><summary>Original payer’s bank details (if PayFast requires them)</summary>
    <p class="small">These details are sent only to PayFast for this refund and are not saved. Enter them again after any error.</p>
    <label class="form-label" for="{{ $formKey }}-holder">Account holder</label><input class="form-control mb-2" id="{{ $formKey }}-holder" name="bank_details[account_holder]" maxlength="100" autocomplete="off" disabled>
    <label class="form-label" for="{{ $formKey }}-bank">Bank name</label><input class="form-control mb-2" id="{{ $formKey }}-bank" name="bank_details[bank_name]" maxlength="100" autocomplete="off" disabled>
    <label class="form-label" for="{{ $formKey }}-branch">Branch code</label><input class="form-control mb-2" id="{{ $formKey }}-branch" name="bank_details[branch_code]" inputmode="numeric" pattern="[0-9]{4,6}" autocomplete="off" disabled>
    <label class="form-label" for="{{ $formKey }}-account">Account number</label><input class="form-control mb-2" id="{{ $formKey }}-account" name="bank_details[account_number]" inputmode="numeric" pattern="[0-9]{5,12}" autocomplete="off" disabled>
    <label class="form-label" for="{{ $formKey }}-type">Account type</label><select class="form-select mb-2" id="{{ $formKey }}-type" name="bank_details[account_type]" disabled><option value="">Choose account type</option><option value="current">Current / cheque</option><option value="savings">Savings</option></select>
    <label><input type="checkbox" name="bank_details[original_payer]" value="1" disabled> These bank details belong to the original payer.</label>
  </details>
  <label class="form-label" for="{{ $formKey }}-reason">Reason</label><input required maxlength="500" name="reason" value="{{ $restoreRefund ? old('reason') : '' }}" class="form-control mb-2" id="{{ $formKey }}-reason">
  <button class="btn btn-outline-danger" type="submit">Confirm refund</button>
</form>
@else
<p class="small">This item is fully refunded or reserved for a pending refund.</p>
@endif
