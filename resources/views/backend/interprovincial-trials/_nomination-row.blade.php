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
    <span class="badge bg-label-{{ $presentation['tone'] }}">{{ $presentation['label'] }}</span>
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
      <span class="small text-muted">History retained</span>
    @endif
  </div>
</div>
