@php($invitation = $presentation['invitation'])
<div class="interpro-nomination-row" data-nomination-row data-nomination-state="{{ $presentation['filter_key'] }}">
  <div class="interpro-nomination-row__identity">
    <div class="fw-semibold">{{ $nomination->display_name }}</div>
    @if($nomination->player_id === null)<span class="badge bg-label-warning">Profile required</span>@endif
    @if($nomination->player_id === null)
      <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.email', [$event, $categoryEvent, $nomination]) }}" class="mt-2">
        @csrf @method('PUT')
        <label class="form-label small" for="nominee-email-{{ $nomination->id }}">Invitation email (optional)</label>
        <input id="nominee-email-{{ $nomination->id }}" type="email" name="nominee_email" value="{{ $nomination->nominee_email }}" maxlength="255" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-primary mt-1">Save email</button>
      </form>
    @endif
    <div class="text-muted small">{{ $categoryEvent->category?->name ?? 'Category unavailable' }}</div>
    @if($invitation?->recipient_email)
      <a class="small interpro-email" href="mailto:{{ rawurlencode($invitation->recipient_email) }}">{{ $invitation->recipient_email }}</a>
    @elseif($invitation)
      <span class="small text-danger">No linked account email</span>
    @endif
  </div>
  <div class="interpro-nomination-row__status">
    <span class="badge bg-label-{{ $presentation['tone'] }}">{{ $invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id ? 'Paid privately' : $presentation['label'] }}</span>
    @if($invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id)<span class="small text-muted">Private collection; not reconciled</span>@endif
    @if($invitation && in_array($invitation->status, ['queued', 'sending', 'sent'], true))
      <span class="small text-muted">{{ str($invitation->status)->title() }}</span>
    @endif
  </div>
  <div class="interpro-nomination-row__timeline small">
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
    @if(!$invitation || (!$invitation->queued_at && !$invitation->sent_at && !$invitation->accepted_at && !$invitation->paid_at && !$invitation->withdrawn_at))
      <span class="text-muted">No lifecycle timestamps yet</span>
    @endif
  </div>
  <div class="interpro-nomination-row__actions">
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
  </div>
</div>
