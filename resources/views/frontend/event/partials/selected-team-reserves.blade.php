@php
  $selectedReserves = ($team->noProfile ? $team->team_players_no_profile : $team->teamPlayers)->filter(fn($member) => $member->selected_team_reserve)->sortBy('rank');
@endphp
@if($selectedReserves->isNotEmpty())
<section class="border-top" aria-label="Reserves for {{ $team->name }}">
  <div class="p-3"><h6 class="mb-1">Reserves ({{ $selectedReserves->count() }})</h6><p class="small text-muted mb-0">Reserves can register after a tournament administrator approves promotion to the team.</p></div>
  <ul class="list-group list-group-flush">
    @foreach($selectedReserves as $reserve)
    @php
      $reserveProfile = $team->noProfile ? $reserve->profile : $reserve->player;
      $reservePlayerId = $team->noProfile ? $reserve->player_profile : $reserve->player_id;
      $reserveName = $reserveProfile ? trim($reserveProfile->name.' '.$reserveProfile->surname) : trim($reserve->name.' '.$reserve->surname);
      $reserveOrder = ($myPaidTeamOrdersByPlayer ?? collect())->get($team->id.'-'.$reservePlayerId);
      $reserveMirror = $team->noProfile ? $team->team_players->first(fn($mirror) => (int) $mirror->rank === (int) $reserve->rank && (int) $mirror->player_id === (int) $reservePlayerId) : $reserve;
      $reservePaid = (int) $reserve->pay_status === 1 || (int) $reserveMirror?->pay_status === 1;
    @endphp
    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
      <span>{{ $reserveName }}</span>
      <div class="d-flex flex-wrap gap-1"><span class="badge bg-label-warning">Reserve</span>
      @if($reservePaid)<span class="badge bg-success-subtle text-success">Registered</span>@endif
      @if($reservePaid && ($canWithdraw ?? false) && $reserveOrder && $reserveMirror)
      <button type="button" class="btn btn-sm btn-outline-danger withDrawPlayer" data-id="{{ $reserveMirror->id }}" data-team="{{ $team->id }}" data-player="{{ $reservePlayerId }}" data-event="{{ $event->id }}" data-url="{{ route('team.player.withdraw', [$team->id, $reservePlayerId, $event->id]) }}">Withdraw</button>
      @endif</div>
    </li>
    @endforeach
  </ul>
</section>
@endif
