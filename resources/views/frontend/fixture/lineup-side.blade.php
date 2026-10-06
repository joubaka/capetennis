@once
<style>
  .fixture-region-side { border-left: 4px solid var(--region-color, #475569); padding-left: .6rem; }
  .fixture-region-badge { display: inline-block; background: var(--region-color, #475569); color: #fff; border-radius: .3rem; padding: .2rem .5rem; margin-bottom: .35rem; font-size: .85rem; font-weight: 600; line-height: 1.5; }
</style>
@endonce
<div class="fixture-region-side" style="--region-color: {{ $lineup['region_color'] ?? '#475569' }}">
@if(!empty($lineup['region']))
  <div><span class="fixture-region-badge" title="{{ $lineup['region_name'] ?? $lineup['region'] }}">{{ $lineup['region_name'] ?? $lineup['region'] }}</span></div>
@endif
@foreach($lineup['players'] ?? [] as $player)
  @if(!$loop->first)<span class="text-muted"> + </span>@endif
  <span class="fixture-player-label">@if($player['rank'])<span class="fixture-roster-rank" title="Team roster rank">({{ $player['rank'] }})</span> @endif{{ $player['name'] }}@if(!empty($player['player_id']))<x-player-rating :player-id="$player['player_id']" :context="$ratingContext ?? ($fx ?? null)?->draw ?? ($fixture ?? null)?->draw ?? $draw ?? null" />@endif@if(!empty($lineup['region'])) <span class="fixture-region" title="{{ $lineup['region_name'] ?? $lineup['region'] }}">({{ $lineup['region'] }})</span>@endif</span>
@endforeach
@if(empty($lineup['players']))<span class="text-muted">TBD</span>@endif
</div>
