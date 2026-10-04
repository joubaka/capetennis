@if($openRosterRanks->isNotEmpty())
<form method="POST" action="{{ route($restorePosition ? 'backend.team-selection.invitations.restore' : 'backend.team-selection.invitations.activate', [$event, $activeImport, $invitation]) }}" class="p-2" onsubmit="return confirm('Place this player in the chosen open position? Other players will stay in place. No email will be sent.');">
  @csrf
  <label class="form-label small" for="open-position-{{ $invitation->id }}">{{ $restorePosition ? 'Restore in open position' : 'Activate in position' }}</label>
  <select id="open-position-{{ $invitation->id }}" name="roster_rank" class="form-select form-select-sm mb-2" required aria-label="Open position for {{ $invitation->player?->full_name ?: 'player' }}">
    @foreach($openRosterRanks as $openRank)<option value="{{ $openRank }}">Rank {{ $openRank }}</option>@endforeach
  </select>
  <button class="btn btn-sm btn-outline-success w-100">{{ $restorePosition ? 'Restore in position' : 'Activate in position' }}</button>
</form>
@endif
