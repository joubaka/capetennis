<details class="roster-more-actions">
  <summary class="btn btn-sm btn-outline-secondary roster-icon-control" aria-label="More actions for empty Rank {{ $slot->rank }}" title="More actions"><i class="ti ti-dots" aria-hidden="true"></i></summary>
  <div class="roster-more-panel">
    <details>
      <summary class="btn btn-sm btn-outline-primary">Add player</summary>
      <div class="mt-2">
        <p class="small mb-2">Fill Rank {{ $slot->rank }}</p>
        @php($availableReserves = $team->workspaceSlots->where('selected_team_reserve', true)->filter(fn ($candidate) => $team->noProfile ? (bool) $candidate->noProfile : (int) $candidate->player_id > 0))
        @if($availableReserves->isNotEmpty())
          <p class="small fw-semibold mb-1">Team reserves</p>
          @foreach($availableReserves as $candidate)
            @php($reserveIdentity = $team->noProfile ? $candidate->noProfile : $candidate)
            @php($reserveName = $candidate->noProfile ? trim($candidate->noProfile->name.' '.$candidate->noProfile->surname) : $candidate->player?->full_name)
            <form method="POST" action="{{ route('backend.team.addPlayers') }}" data-team-roster-change class="mb-2">
              @csrf
              <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
              <input type="hidden" name="target_rank" value="{{ $slot->rank }}"><input type="hidden" name="reserve_slot_id" value="{{ $reserveIdentity->id }}"><input type="hidden" name="expected_reserve_rank" value="{{ $candidate->rank }}">
              <button class="btn btn-sm btn-outline-primary w-100 text-start">Add {{ $reserveName }}</button>
            </form>
          @endforeach
        @else
          <p class="small text-muted">No team reserves available.</p>
        @endif
        <details class="mt-2">
          <summary class="btn btn-sm btn-outline-secondary">Find a database player</summary>
          <form method="POST" action="{{ route('backend.team.addPlayers') }}" data-team-roster-change class="mt-2">
            @csrf
            <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}"><input type="hidden" name="target_rank" value="{{ $slot->rank }}">
            <label class="form-label w-100">Find player<input type="search" class="form-control" minlength="2" placeholder="Type a first name or surname" data-team-roster-search="{{ route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id]) }}"></label>
            <label class="form-label w-100">Player<select class="form-select" name="player_id" required data-team-roster-results><option value="">Search for a player first</option></select></label>
            <button class="btn btn-sm btn-primary">Add to Rank {{ $slot->rank }}</button>
          </form>
        </details>
      </div>
    </details>
  </div>
</details>
