@php($compactRoster = $compactRoster ?? false)
@php($identity = $team->noProfile ? $slot->noProfile : $slot)
@if($identity && ($team->noProfile || (int) $identity->player_id > 0))
<div class="team-roster-actions">
  @foreach(['up' => 'Move up', 'down' => 'Move down', 'remove' => 'Remove'] as $direction => $label)
    @if($compactRoster && $direction === 'remove')
      <details class="roster-more-actions">
        <summary class="btn btn-sm btn-outline-secondary roster-icon-control" aria-label="More actions for {{ $name }}" title="More actions"><i class="ti ti-dots" aria-hidden="true"></i></summary>
        <div class="roster-more-panel">
    @endif
    <form method="POST" action="{{ route($direction === 'remove' ? 'backend.team.removePlayer' : 'team.order.player.list') }}" data-team-roster-change>
      @csrf
      <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
      @if($direction !== 'remove')<input type="hidden" name="direction" value="{{ $direction }}">@else<input type="hidden" name="remove" value="1">@endif
      <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
      <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
      @if($direction === 'remove')
        <button type="button" class="btn btn-sm btn-outline-danger" data-team-roster-remove-toggle aria-expanded="false">Remove</button>
        <span data-team-roster-remove-confirm hidden role="group" aria-label="Confirm player removal">
          <span class="small">Remove this player?</span>
          <button class="btn btn-sm btn-danger">Confirm remove</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
        </span>
      @else
        <button class="btn btn-sm btn-outline-secondary {{ $compactRoster ? 'roster-icon-control' : '' }}" @if($compactRoster) title="{{ $label }}" aria-label="{{ $label }}: {{ $name }}" @endif>@if($compactRoster)<i class="ti ti-arrow-{{ $direction }}" aria-hidden="true"></i>@else{{ $label }}@endif</button>
      @endif
    </form>
  @endforeach
  @if(($team->noProfile ? (int) $identity->player_profile : (int) $identity->player_id) > 0)
    <details>
      <summary class="btn btn-sm btn-outline-secondary">Change player</summary>
    <form method="POST" action="{{ route('backend.team.changePlayer') }}" class="mt-2" data-team-roster-change>
        @csrf
        <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
        <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
        <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
        @if($team->noProfile)
          <label class="form-label w-100">First name<input class="form-control" name="name" required maxlength="100"></label>
          <label class="form-label w-100">Surname<input class="form-control" name="surname" required maxlength="100"></label>
        @else
          <label class="form-label w-100">Find player<input class="form-control" type="search" minlength="2" placeholder="Type a first name or surname" data-team-roster-search="{{ route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id]) }}"></label>
          <label class="form-label w-100">Player<select class="form-select" name="player_id" required data-team-roster-results><option value="">Search for a player first</option></select></label>
        @endif
        <button class="btn btn-primary">Save player</button>
        <p class="small text-muted mt-1">The current player stays in place until you save.</p>
      </form>
    </details>
  @elseif($team->noProfile)
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
  @if($compactRoster)
    @if(!($rosterOnly ?? false) && $slot->exists && (int) $slot->player_id > 0 && (int) $slot->pay_status === 1)
      @role('super-user')
        <a class="btn btn-sm btn-outline-warning mt-2" href="{{ route('backend.team.withdrawal.show', [$event, $team, $slot]) }}">Review withdrawal / refund</a>
      @endrole
    @endif
  </div></details>@endif
</div>
@endif
