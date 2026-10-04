@php
  $coverageOrder = $invitation->order;
  $transferTargets = $teamInvitations->filter(fn ($candidate) => in_array($candidate->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true) && $candidate->roster_rank && $candidate->player_id != $invitation->player_id);
  $transferUnavailable = $activeImport->status !== 'sent' ? 'Payment transfer requires an active invitation campaign.'
    : (!$coverageOrder || !$coverageOrder->pay_status ? 'No settled payment order is attached. Paid Confirmed alone is not proof of payment.'
    : ($coverageOrder->collection_status === 'paid_privately' ? 'Payment collected privately cannot be transferred: there is no verified wallet or PayFast settlement.'
    : ($coverageOrder->withdrawn_at || $coverageOrder->hasRefund() ? 'Payment has withdrawal or refund history and cannot be transferred.'
    : ($transferTargets->isEmpty() ? 'No selected unpaid player is available. Activate a reserve first.' : null))));
  $transferFormOpen = (int) old('transfer_invitation_id') === (int) $invitation->id;
@endphp
<details id="invitation-payment-transfer-{{ $invitation->id }}" class="mt-2" style="max-width:22rem;white-space:normal;" data-invitation-payment-transfer @if($transferFormOpen) open @endif>
  <summary class="small text-primary">Move payment to another player</summary>
  @if($transferFormOpen && $errors->any())<div class="alert alert-danger mt-2 small" role="alert">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
  @if($transferUnavailable)
    <p class="small text-muted mt-2 mb-0" data-payment-transfer-unavailable>{{ $transferUnavailable }}</p>
  @else
    <form method="POST" action="{{ route('backend.team-selection.invitations.payment.transfer', [$event, $activeImport, $invitation]) }}" class="mt-2">
      @csrf
      <input type="hidden" name="transfer_invitation_id" value="{{ $invitation->id }}">
      <input type="hidden" name="order_id" value="{{ $coverageOrder->id }}">
      <input type="hidden" name="expected_player_id" value="{{ $invitation->player_id }}">
      <label class="small" for="invitation-transfer-target-{{ $invitation->id }}">Unpaid player in this team</label>
      <select id="invitation-transfer-target-{{ $invitation->id }}" name="target_player_id" class="form-select form-select-sm" required>
        <option value="">Choose player</option>
        @foreach($transferTargets as $candidate)
          <option value="{{ $candidate->player_id }}" @selected($transferFormOpen && old('target_player_id') == $candidate->player_id)>{{ $candidate->player?->full_name }} · Rank {{ $candidate->roster_rank }}</option>
        @endforeach
      </select>
      <div class="small text-muted mt-1">Selected unpaid players can receive this payment without starting a checkout. Activate reserves first.</div>
      <label class="small mt-2" for="invitation-transfer-reason-{{ $invitation->id }}">Reason</label>
      <input id="invitation-transfer-reason-{{ $invitation->id }}" name="reason" maxlength="1000" class="form-control form-control-sm" value="{{ $transferFormOpen ? old('reason') : '' }}" required>
      <p class="small text-muted mt-2">Use this payment for the selected player. Any later refund goes to the original payer.</p>
      <p class="small text-muted">The original player remains selected and becomes unpaid. No team place is released.</p>
      <label class="small d-flex gap-2 my-2"><input type="checkbox" class="flex-shrink-0" id="confirm-invitation-transfer-{{ $invitation->id }}" name="confirm_transfer" value="1" required><span>Confirm this player becomes unpaid and the selected player becomes paid.</span></label>
      <button class="btn btn-sm btn-outline-primary">Move payment</button>
    </form>
  @endif
</details>
