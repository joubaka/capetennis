@php
    $lineup = $fixture->lineup_display[$side];
    $rows = $fixture->fixturePlayers->sortBy('slot_no')->values();
    $profiles = $side === 'home' ? $fixture->team1 : $fixture->team2;
    $canOpenProfiles = auth()->user()?->hasAnyRole(['super-user', 'admin'])
        && $fixture->draw?->event
        && auth()->user()->can('event-draw.view', $fixture->draw->event);
@endphp
<div class="fixture-region" title="{{ $lineup['region_name'] }}">
    @if($lineup['region_logo'] ?? null)
    <img class="fixture-region-logo" src="{{ asset($lineup['region_logo']) }}" alt="" width="28" height="28" style="object-fit: contain">
    @endif
    @include('backend.team-fixtures.partials.region-badge', ['lineup' => $lineup])
</div>
<div class="fixture-players">
@forelse($lineup['players'] as $player)
    @php
        $row = $rows->get($loop->index);
        $profile = $rows->isNotEmpty()
            ? ($side === 'home' ? ($row?->player1 ?? $row?->noProfile1?->profile) : ($row?->player2 ?? $row?->noProfile2?->profile))
            : $profiles->get($loop->index);
    @endphp
    @if($canOpenProfiles && $profile)
    <a class="fixture-player-badge fixture-player-link" href="{{ route('backend.player.profile', $profile->id) }}" aria-label="View player profile: {{ $player['name'] }}">
    @else
    <span class="fixture-player-badge">
    @endif
        @if($player['rank'])<span class="fixture-rank" title="Team roster rank">({{ $player['rank'] }})</span>@endif
        <span>{{ $player['name'] }}@if($profile)<x-player-rating :player-id="$profile->id" :context="$fixture->draw" />@endif</span>
        @unless($player['rank'])<span class="fixture-rank small">— rank unavailable</span>@endunless
    @if($canOpenProfiles && $profile)</a>@else</span>@endif
@empty
    <span class="text-muted small">Players to be confirmed</span>
@endforelse
</div>
