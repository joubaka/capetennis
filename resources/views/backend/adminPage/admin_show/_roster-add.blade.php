@php($reserveAdd = $reserveAdd ?? false)
<details class="mt-3 roster-player-editor"><summary class="btn btn-outline-primary">{{ $reserveAdd ? 'Add reserve player' : 'Add player' }}</summary>
<form method="POST" action="{{ route('backend.team.addPlayers') }}" class="mt-3 roster-player-form" data-team-roster-change>
  @csrf<input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
  @if($reserveAdd)<input type="hidden" name="reserve" value="1"><p class="small text-muted">Add an unpaid reserve after the configured team places. Existing players and team size stay unchanged.</p>@endif
  @include('backend.adminPage.admin_show._roster-player-inputs', ['profileFieldName' => $team->noProfile ? 'player_id' : 'player_ids[]'])
  <button class="btn btn-primary">{{ $reserveAdd ? 'Add reserve player' : 'Add player' }}</button>
</form></details>
