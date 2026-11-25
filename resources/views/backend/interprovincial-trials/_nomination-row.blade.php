@php
  $invitation = $presentation['invitation'];
  $hasNominationHistory = $nomination->archived_at || $nomination->removed_at
    || ($invitation && ($invitation->queued_at || $invitation->sent_at || $invitation->accepted_at || $invitation->paid_at || $invitation->withdrawn_at));
@endphp
<div class="interpro-nomination-row" role="row" data-nomination-row data-nomination-state="{{ $presentation['filter_key'] }}">
  <div class="interpro-nomination-row__identity" role="cell">
    <div class="fw-semibold">{{ $nomination->display_name }}</div>
    @if($nomination->player_id === null)<span class="badge bg-label-warning">Profile required</span>@endif
    @if($nomination->player_id === null && !($presentation['historical'] ?? false))
      <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.email', [$event, $categoryEvent, $nomination]) }}" class="mt-2">
        @csrf @method('PUT')
        <label class="form-label small" for="nominee-email-{{ $nomination->id }}">Invitation email (optional)</label>
        <input id="nominee-email-{{ $nomination->id }}" type="email" name="nominee_email" value="{{ $nomination->nominee_email }}" maxlength="255" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-primary mt-1">Save email</button>
      </form>
    @endif
    <div class="text-muted small interpro-row-category">{{ $categoryEvent->category?->name ?? 'Category unavailable' }}</div>
    @if($invitation?->recipient_email)
      <a class="small interpro-email" href="mailto:{{ rawurlencode($invitation->recipient_email) }}">{{ $invitation->recipient_email }}</a>
    @elseif($invitation)
      <span class="small text-muted">Email not available</span>
    @endif
  </div>
  <div class="interpro-nomination-row__status" role="cell">
    <span class="interpro-cell-label" aria-hidden="true">Status</span>
    <span class="badge bg-label-{{ $presentation['tone'] }}">{{ $invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id ? 'Paid privately' : $presentation['label'] }}</span>
    @if($nomination->archived_at)<span class="badge bg-label-secondary">Archived nomination</span>@endif
    @if($invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id)<span class="small text-muted">Private collection; not reconciled</span>@endif
    @if($invitation && in_array($invitation->status, ['queued', 'sending', 'sent'], true))
      <span class="small text-muted">{{ str($invitation->status)->title() }}</span>
    @endif
  </div>
  <div class="interpro-nomination-row__timeline small" role="cell">
    @if($hasNominationHistory)
    <span class="interpro-cell-label" aria-hidden="true">Registration history</span>
    <details class="interpro-history"><summary>View history</summary><div class="interpro-history-content">
    @if($invitation)
      @foreach([
        'Queued' => $invitation->queued_at,
        'Sent' => $invitation->sent_at,
        'Accepted' => $invitation->accepted_at,
        'Paid' => $invitation->paid_at,
        'Withdrawn' => $invitation->withdrawn_at,
      ] as $timestampLabel => $timestamp)
        @if($timestamp)<span><span class="text-muted">{{ $timestampLabel }}:</span> {{ $timestamp->format('d M Y H:i') }}</span>@endif
      @endforeach
    @endif
    @if($nomination->archived_at)<span>Archived: {{ $nomination->archived_at->format('d M Y H:i') }}</span><span class="text-muted">{{ $nomination->archive_reason }}</span>@endif
    @if($nomination->removed_at)<span>Removed: {{ \Illuminate\Support\Carbon::parse($nomination->removed_at)->format('d M Y H:i') }}</span><span class="text-muted">{{ $nomination->removed_by_name ?: 'Actor not recorded' }} · {{ $nomination->removal_reason ?: 'Reason not recorded' }}</span>@endif
    </div></details>
    @endif
  </div>
  <div class="interpro-nomination-row__actions" role="cell">
    <span class="interpro-cell-label w-100" aria-hidden="true">Actions</span>
    @if($presentation['reactivation_revision'] ?? null)
      <details><summary class="btn btn-sm btn-outline-primary interpro-row-action">Re-add nomination</summary>
        <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.reactivate', [$event, $categoryEvent, $nomination->id]) }}" class="mt-2">
          @csrf<input type="hidden" name="revision" value="{{ $presentation['reactivation_revision'] }}">
          <label class="form-label small" for="reactivation-reason-{{ $nomination->id }}">Reason for re-adding</label>
          <input id="reactivation-reason-{{ $nomination->id }}" name="reason" class="form-control form-control-sm" minlength="5" maxlength="1000" required>
          <p class="small text-muted">Re-adds this player to the same category. If nominations are public, the player will appear publicly again. Previous history is retained. Registration and payment must still be completed separately; no email is sent.</p>
          <label class="small d-block"><input type="checkbox" name="confirm" value="1" required> Confirm the player is available again</label>
          <button class="btn btn-sm btn-primary mt-2 interpro-row-action">Confirm re-add</button>
        </form>
      </details>
    @endif
    @if($presentation['historical'] ?? false)
      <span class="small text-muted">Retained history</span>
    @else
    @if($presentation['can_administer'])
      <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.administer', [$event, $categoryEvent, $nomination]) }}">
        @csrf<input type="hidden" name="action" value="decline">
        <button class="btn btn-sm btn-outline-secondary interpro-row-action">Decline</button>
      </form>
      @if($nomination->player_id)
        <details><summary class="btn btn-sm btn-outline-success interpro-row-action">Mark paid manually</summary>
          <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.administer', [$event, $categoryEvent, $nomination]) }}" class="mt-2">
            @csrf<input type="hidden" name="action" value="paid_privately">
            <p class="small text-muted">Creates an entry with private collection marked paid. No payment amount or reconciled receipt is recorded.</p>
            <button class="btn btn-sm btn-success interpro-row-action">Confirm paid privately</button>
          </form>
        </details>
      @endif
    @elseif($invitation?->status === 'accepted_pending_payment' && $invitation->order_id)
      <details><summary class="btn btn-sm btn-outline-success interpro-row-action">Mark paid manually</summary>
        <form method="POST" action="{{ route('backend.interprovincial-trials.orders.mark-paid', [$event, $invitation->order_id]) }}" class="mt-2">
          @csrf<label class="form-label small" for="manual-reference-{{ $nomination->id }}">Payment reference</label>
          <input id="manual-reference-{{ $nomination->id }}" name="reference" class="form-control form-control-sm" minlength="3" maxlength="120" required>
          <p class="small text-muted">Records the exact checkout amount as received. Payments already in progress cannot be marked manually.</p>
          <button class="btn btn-sm btn-success interpro-row-action">Record payment received</button>
        </form>
      </details>
    @endif
    @if($presentation['can_resend'])
      <button type="button" class="btn btn-sm btn-outline-secondary interpro-row-action" data-send-preview-mode="individual" data-invitation-id="{{ $invitation->id }}">Resend invitation</button>
    @endif
    @if($presentation['can_retry_follow_up'])
      <form method="POST" action="{{ route('backend.interprovincial-trials.invitations.retry-follow-up', [$event, $invitation]) }}">
        @csrf
        <button class="btn btn-sm btn-outline-primary interpro-row-action">Retry follow-up</button>
      </form>
    @endif
    @if($invitation?->status === 'failed')
      <form method="POST" action="{{ route('backend.interprovincial-trials.invitations.retry', [$event, $invitation->batch_id, $invitation]) }}">
        @csrf
        <button class="btn btn-sm btn-outline-primary interpro-row-action">Retry</button>
      </form>
    @endif
    @if($presentation['can_remove'])
      <form class="nomination-remove-form" method="POST" action="{{ route('backend.interprovincial-trials.nominations.destroy', [$event, $categoryEvent, $nomination]) }}">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger interpro-row-action">Remove</button>
      </form>
    @else
      <a class="small" href="{{ route('admin.events.entries.new', $event) }}">Manage registration in Entries</a>
    @endif
    @endif
  </div>
</div>
