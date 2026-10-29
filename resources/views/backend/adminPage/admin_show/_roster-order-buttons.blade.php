@foreach(['up' => 'Move up', 'down' => 'Move down'] as $direction => $label)
  <form method="POST" action="{{ route('team.order.player.list') }}" data-team-roster-change>
    @csrf
    <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
    <input type="hidden" name="direction" value="{{ $direction }}">
    <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
    <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
    <button class="btn btn-sm btn-outline-secondary {{ $compactRoster ? 'roster-icon-control' : '' }}" @if($compactRoster) title="{{ $label }}" aria-label="{{ $label }}: {{ $name }}" @endif>@if($compactRoster)<i class="ti ti-arrow-{{ $direction }}" aria-hidden="true"></i>@else{{ $label }}@endif</button>
  </form>
@endforeach
