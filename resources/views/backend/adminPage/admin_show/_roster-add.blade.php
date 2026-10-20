@php($reserveAdd = $reserveAdd ?? false)
<details class="mt-3"><summary class="btn btn-outline-primary">{{ $reserveAdd ? 'Add reserve player' : 'Add player' }}</summary>
<form method="POST" action="{{ route('backend.team.addPlayers') }}" class="mt-3" data-team-roster-change>
  @csrf<input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
  @if($reserveAdd)<input type="hidden" name="reserve" value="1"><p class="small text-muted">Add an unpaid reserve after the configured team places. Existing players and team size stay unchanged.</p>@endif
  @if($team->noProfile)
    <div class="row g-2"><div class="col-sm-6"><label class="form-label w-100">First name<input class="form-control" name="name" required maxlength="100"></label></div><div class="col-sm-6"><label class="form-label w-100">Surname<input class="form-control" name="surname" required maxlength="100"></label></div></div>
  @else
    <label class="form-label w-100">Find player<input type="search" class="form-control" minlength="2" placeholder="Type a first name or surname" data-team-roster-search="{{ route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id]) }}"></label>
    <label class="form-label w-100">Player<select class="form-select" name="player_ids[]" required data-team-roster-results><option value="">Search for a player first</option></select></label>
  @endif
  <button class="btn btn-primary">{{ $reserveAdd ? 'Add reserve player' : 'Add player' }}</button>
</form></details>
