@foreach($lineup['players'] ?? [] as $player)
  @if(!$loop->first)<span class="text-muted"> + </span>@endif
  <span class="fixture-player-label">@if($player['rank'])<span class="fixture-roster-rank" title="Team roster rank">({{ $player['rank'] }})</span> @endif{{ $player['name'] }}@if(!empty($lineup['region'])) <span class="fixture-region" title="{{ $lineup['region_name'] ?? $lineup['region'] }}">({{ $lineup['region'] }})</span>@endif</span>
@endforeach
@if(empty($lineup['players']))<span class="text-muted">TBD</span>@endif
