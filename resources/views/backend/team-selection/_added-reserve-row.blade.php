<tr class="reserve-row" data-team-invitation-row="{{ $invitation->id }}">
  <td><span class="badge bg-label-warning">Reserve {{ $invitation->queue_position }}</span></td>
  <td>
    <strong>{{ $invitation->player?->full_name ?: 'Missing player' }}</strong>
    @if(! $invitation->player?->profile_complete)<div class="small text-warning">Profile incomplete</div>@endif
  </td>
  <td>
    @if($recipientEmail)
      <div>{{ $recipientEmail }}</div>
    @elseif($rawContactEmails->isNotEmpty())
      <div class="text-warning">Profile/account email invalid</div>
      <div class="small text-muted">{{ $rawContactEmails->first() }}</div>
    @else
      <div>Email required</div>
    @endif
    <div class="small text-muted">{{ $invitation->player?->cellNr ?: 'No cell number' }}</div>
  </td>
  <td><strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div></td>
  <td><span class="badge bg-label-warning">Reserve</span><div class="small text-muted mt-1">Read only</div></td>
  <td><div>Not sent</div></td>
  <td>
    @if($reserveActivationRank)
      <div class="dropdown">
        <button class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Regional actions for {{ $invitation->player?->full_name ?: 'player' }}">
          <i class="ti ti-dots-vertical" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-1" style="min-width:15rem;">
          <form method="POST" action="{{ route('backend.team-selection.invitations.activate', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Activate this reserve in the next open team place? No invitation email will be sent. You can review and send all pending newly activated invitations together later.');">
            @csrf
            <button class="dropdown-item text-success">Activate as Rank {{ $reserveActivationRank }}</button>
          </form>
        </div>
      </div>
    @else
      <span class="text-muted small">No action available</span>
    @endif
  </td>
</tr>
