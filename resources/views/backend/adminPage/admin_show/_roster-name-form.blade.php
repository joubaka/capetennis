@if($team->noProfile && $identity && (int) $identity->player_profile === 0)
  <details>
    <summary class="btn btn-sm btn-outline-secondary">Change name</summary>
    <form method="POST" action="{{ route('backend.team.editPlayerName') }}" class="mt-2" data-team-roster-change>
      @csrf
      <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
      <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
      <input type="hidden" name="expected_player_id" value="0">
      <input type="hidden" name="expected_name" value="{{ $identity->name }}"><input type="hidden" name="expected_surname" value="{{ $identity->surname }}">
      <label class="form-label w-100">First name<input class="form-control" name="name" value="{{ $identity->name }}" required maxlength="100"></label>
      <label class="form-label w-100">Surname<input class="form-control" name="surname" value="{{ $identity->surname }}" required maxlength="100"></label>
      <button class="btn btn-primary">Save name</button>
    </form>
  </details>
@endif
