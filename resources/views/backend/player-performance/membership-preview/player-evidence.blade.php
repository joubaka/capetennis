<article class="mp-card">
    <div class="mp-topline"><span class="mp-eyebrow">{{ $player['cohort_label'] }}</span><span class="mp-chip @if($player['established']) mp-chip-active @endif">{{ $player['status'] }}</span></div>
    <h2 class="mt-3">{{ $player['name'] }}</h2>
    <p class="mp-subtle">Comparison group {{ $player['component'] }} · saved {{ $as_of }}</p>
    @if($player['score'] !== null)<div class="mp-score">{{ number_format($player['score'], 1) }} <small>/ 100</small></div><p class="mp-subtle mt-2">{{ $player['confidence'] }} evidence confidence · not calibrated accuracy</p>@else<div class="mp-score">— <small>Unrated</small></div><p class="mp-subtle mt-2">No direct played evidence; numeric rating withheld.</p>@endif
    <div class="mp-counts"><div><strong>{{ $player['played'] }}</strong><span>singles / 365 days</span></div><div><strong>{{ $player['events'] }}</strong><span>events / 365 days</span></div><div><strong>{{ $player['opponents'] }}</strong><span>opponents / 365 days</span></div></div>
    <p class="mp-subtle"><strong>{{ $player['played_all_time'] }}</strong> recorded direct singles matches across all saved dates in this cohort. Last match: {{ $player['last_match'] ?? 'none' }}.</p>
    @include('backend.player-performance.membership-preview.profile-freshness')
    <p class="mp-subtle">{{ $player['baseline_status'] }}. Own finishing-order links: {{ $player['own_inferred'] }}; full comparison-group finishing-order links: {{ $player['component_inferred'] }}. These links are not played matches.</p>
    @if(!$player['established'])<p class="mp-note">{{ ucfirst(implode('; ', $player['needs'])) }}. @if($player['status'] === 'Provisional')Included on the leaderboard while still building evidence.@endif This status does not award an official selection position.</p>@endif
    <a href="{{ route('backend.player-performance.membership-preview.compare', ['cohort' => $player['cohort']]) }}" class="mp-btn">Compare in this cohort</a>
</article>
