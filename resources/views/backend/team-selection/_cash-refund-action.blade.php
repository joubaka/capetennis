@php
  $cashOrder = $invitation->order;
  $cashAmounts = $cashOrder ? app(\App\Domain\Refunds\Services\TeamRefundCalculator::class)->calculate($cashOrder) : null;
  $cashBlocked = !$cashOrder ? 'No payment order is attached. Paid Confirmed alone is not proof of money received.'
    : (!$cashOrder->payfast_paid || !$cashOrder->payfast_pf_payment_id ? 'A verified PayFast payment is required. Private collection marks use Mark as unpaid instead.'
    : ($cashOrder->withdrawn_at || $cashOrder->hasRefund() || $cashOrder->refund_waived_at ? 'This order already has withdrawal or refund history.'
    : (now()->gt($event->withdrawalCloseAt()) ? 'The standard withdrawal refund deadline has passed.'
    : ($cashAmounts['net'] <= 0 ? 'No amount is refundable under the current withdrawal policy.' : null))));
  $cashOpen = (int) old('cash_refund_invitation_id') === (int) $invitation->id;
@endphp
<details class="p-2" data-cash-refund-action @if($cashOpen) open @endif>
  <summary class="text-warning">Record cash refund</summary>
  @if($cashBlocked)<p class="small text-muted mt-2 mb-0">{{ $cashBlocked }}</p>
  @else
  <form method="POST" action="{{ route('backend.team-selection.invitations.cash-refund', [$event, $activeImport, $invitation]) }}" class="mt-2">
    @csrf
    <input type="hidden" name="cash_refund_invitation_id" value="{{ $invitation->id }}"><input type="hidden" name="expected_order_id" value="{{ $cashOrder->id }}"><input type="hidden" name="expected_player_id" value="{{ $invitation->player_id }}"><input type="hidden" name="expected_roster_rank" value="{{ $invitation->roster_rank }}">
    <input type="hidden" name="refund_fingerprint" value="{{ \App\Domain\Payments\Services\TeamPaymentService::cashRefundFingerprint($cashOrder, $cashAmounts) }}">
    @if($cashOpen && $errors->any())<div class="small text-danger mb-2">{{ $errors->first() }}</div>@endif
    <p class="small">Original payer: <strong>{{ $cashOrder->user?->name ?: 'Payer unavailable' }}</strong> · Order #{{ $cashOrder->id }}</p>
    <p class="small">Paid R{{ number_format($cashAmounts['gross'], 2) }} · Fee R{{ number_format($cashAmounts['fee'], 2) }} · <strong>Cash refund R{{ number_format($cashAmounts['net'], 2) }}</strong></p>
    <p class="small text-muted">This records cash already handed to the original payer. Choose whether to remove this player or keep them selected at the same rank, unpaid and requiring a fresh checkout. It does not refund through PayFast or credit a wallet.</p>
    <label class="form-label small" for="cash-disposition-{{ $invitation->id }}">Player after refund</label>
    <select id="cash-disposition-{{ $invitation->id }}" name="disposition" class="form-select form-select-sm mb-2" required>
      <option value="">Choose remove or keep</option><option value="remove" @selected($cashOpen && old('disposition') === 'remove')>Remove player and free roster place</option><option value="keep" @selected($cashOpen && old('disposition') === 'keep')>Keep selected at same rank, unpaid</option>
    </select>
    <label class="form-label small" for="cash-reference-{{ $invitation->id }}">Cash receipt / reference</label><input id="cash-reference-{{ $invitation->id }}" name="reference" class="form-control form-control-sm mb-2" maxlength="255" value="{{ $cashOpen ? old('reference') : '' }}" required>
    <label class="form-label small" for="cash-reason-{{ $invitation->id }}">Reason</label><input id="cash-reason-{{ $invitation->id }}" name="reason" class="form-control form-control-sm mb-2" maxlength="1000" value="{{ $cashOpen ? old('reason') : '' }}" required>
    <label class="form-check small"><input class="form-check-input" type="checkbox" name="confirm_cash_paid" value="1" required><span class="form-check-label">I confirm R{{ number_format($cashAmounts['net'], 2) }} cash was handed to the original payer.</span></label>
    <button class="btn btn-sm btn-outline-warning mt-2">Confirm recorded cash refund</button>
  </form>
  @endif
</details>
