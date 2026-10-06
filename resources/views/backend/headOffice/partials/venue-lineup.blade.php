@include('backend.team-fixtures.partials.region-badge', ['lineup' => array_merge($lineup, ['region' => '(' . ($lineup['region'] ?: 'TBC') . ')'])])
@forelse($lineup['players'] as $player)
    <span class="venue-player">@if($player['rank'])<span title="Team roster rank">({{ $player['rank'] }})</span> @endif{{ $player['name'] }}@unless($player['rank']) <span class="small text-muted">— rank unavailable</span>@endunless</span>
@empty
    <span class="venue-player text-muted">Players to be confirmed</span>
@endforelse
