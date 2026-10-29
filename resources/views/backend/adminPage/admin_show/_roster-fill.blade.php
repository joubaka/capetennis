<details class="roster-player-editor">
  <summary class="btn btn-sm btn-outline-primary" aria-label="Add player to Rank {{ $slot->rank }}">Add player</summary>
  <div class="roster-player-form mt-2">
    <p class="small fw-semibold mb-2">Fill Rank {{ $slot->rank }}</p>
    @php($availableReserves = $team->workspaceSlots->where('selected_team_reserve', true)->filter(fn ($candidate) => $team->noProfile ? (bool) $candidate->noProfile : (int) $candidate->player_id > 0))
    @if($availableReserves->isNotEmpty())
      <fieldset class="mb-3">
        <legend class="form-label">Choose a team reserve</legend>
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
      </fieldset>
      <p class="small text-muted">Or add another player:</p>
    @endif
    <form method="POST" action="{{ route('backend.team.addPlayers') }}" data-team-roster-change>
      @csrf
      <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}"><input type="hidden" name="target_rank" value="{{ $slot->rank }}">
      @include('backend.adminPage.admin_show._roster-player-inputs', ['profileFieldName' => 'player_id'])
      <button class="btn btn-sm btn-primary">Add to Rank {{ $slot->rank }}</button>
    </form>
  </div>
</details>
