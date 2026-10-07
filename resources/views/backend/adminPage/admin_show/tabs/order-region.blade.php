<h2 class="h5">Player order · {{ $region->region_name }}</h2>
@forelse($region->teams as $team)
  @php
    $rankingManaged = ($teamSelectionInvitations ?? collect())->has($team->id);
    $editable = !$rankingManaged && !$orderLocked && auth()->user()->can('team.players.manage', $team);
    $slots = $team->teamPlayers->keyBy('rank');
    $noProfiles = $team->team_players_no_profile->keyBy('rank');
    $ranks = $slots->keys()->merge($noProfiles->keys())->unique()->sort();
  @endphp
  <details class="roster-team" data-order-team="{{ $team->id }}" @if($loop->first) open @endif>
    <summary class="roster-team-summary"><span class="roster-team-title"><strong>{{ $team->name }}</strong><span class="small text-muted">{{ $rankingManaged ? 'Ranking-managed order' : ($orderLocked ? 'Order locked: fixtures generated' : 'Drag a row or use Move up / Move down') }}</span></span><span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-secondary' }}" data-team-publication="{{ $team->id }}">{{ $team->published ? 'Published' : 'Unpublished' }}</span></summary>
    <div class="roster-team-body">
      <div class="table-responsive"><table class="table align-middle order-player-table"><thead><tr><th scope="col">Move</th><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Payment</th></tr></thead>
        <tbody class="{{ $editable ? 'sortablePlayers' : '' }}" data-team-id="{{ $team->id }}">
          @foreach($ranks as $rank)
            @php
              $slot = $slots->get($rank);
              $player = $slot?->player;
              $np = $noProfiles->get($rank);
              $type = $np ? 'noprofile' : 'profile';
              $id = $np ? $np->id : $slot?->id;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
            @endphp
            <tr class="drag-item" data-playerteamid="{{ $id }}" data-teamplayerid="{{ $slot?->id }}" data-noprofileid="{{ $np?->id }}" data-type="{{ $type }}">
              <td data-label="Move">@if($editable && $id)<span class="drag-handle" title="Drag to reorder" aria-hidden="true"><i class="ti ti-grip-vertical"></i></span><button type="button" class="btn btn-outline-secondary" data-order-move="up" aria-label="Move {{ $name }} up">↑</button><button type="button" class="btn btn-outline-secondary" data-order-move="down" aria-label="Move {{ $name }} down">↓</button>@else<span class="text-muted">Locked</span>@endif</td>
              <td data-label="Rank"><span class="badge bg-label-primary">{{ $rank }}</span></td>
              <td data-label="Player">{{ $name }}</td>
              <td data-label="Payment"><span class="badge {{ !$player && !$np ? 'bg-label-secondary' : ((int) $slot?->pay_status === 1 ? 'bg-label-success' : 'bg-label-warning') }}">{{ !$player && !$np ? 'Vacant' : ((int) $slot?->pay_status === 1 ? 'Paid' : 'Unpaid') }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
      @if($rankingManaged)<a class="btn btn-outline-primary" href="{{ route('backend.team-selection.index', $event) }}">Manage selection order</a>@endif
      <p class="small text-muted mb-0" role="status" data-order-status aria-live="polite"></p>
    </div>
  </details>
@empty <div class="alert alert-light border">No teams in this region.</div> @endforelse
