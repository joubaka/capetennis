@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player directory · membership preview')
@section('content')
<div class="mp-preview">
@include('backend.player-performance.membership-preview.chrome')
@include('backend.player-performance.membership-preview.overview')
<p class="mp-note">A direct result within 24 calendar months allows a player onto this preview leaderboard, including low-confidence estimates. Provisional scores do not establish precise rankings; more varied evidence can improve their context.</p>
@include('backend.player-performance.membership-preview.filters')
<div class="mp-grid">
@forelse($players as $player)
<article class="mp-card">
    <div class="mp-topline"><span class="mp-eyebrow">{{ $player['cohort_label'] }}</span><span class="mp-chip @if($player['established']) mp-chip-active @endif">{{ $player['status'] }}</span></div>
    <h2 class="mt-3 mb-1">{{ $player['name'] }}</h2>
    <p class="mp-subtle mb-1">Comparison group {{ $player['component'] }}</p>
    @if($player['score'] !== null)<p class="mp-subtle mb-3">Saved estimate as of {{ $as_of }} · {{ $player['confidence'] }} evidence confidence</p>
    <div class="mp-score">{{ number_format($player['score'], 1) }} <small>/ 100</small></div>
    @else<p class="mp-subtle">No direct played-match evidence. A numeric rating is withheld.</p><div class="mp-score">— <small>Unrated</small></div>@endif
    <div class="mp-counts"><div><strong>{{ $player['played'] }}</strong><span>singles matches</span></div><div><strong>{{ $player['events'] }}</strong><span>{{ Str::plural('event', $player['events']) }}</span></div><div><strong>{{ $player['opponents'] }}</strong><span>opponents</span></div></div>
    <p class="mp-subtle">Recorded in the last 365 days. Last match: {{ $player['last_match'] ?? 'unavailable' }}.</p>
    @include('backend.player-performance.membership-preview.profile-freshness')
    @if(!$player['established'])<div class="mp-note"><strong>@if($player['status'] === 'Inactive')Inactive evidence @elseif($player['status'] === 'Unrated')Direct results needed @else Building evidence · included on leaderboard @endif</strong><br>{{ ucfirst(implode('; ', $player['needs'])) }}.</div>@endif
    <details><summary>How to read this estimate</summary><p class="mp-subtle">Compare within {{ $player['cohort_label'] }} and the same connected group. Confidence describes the amount and freshness of evidence, not guaranteed accuracy. One match can change nearby ordering.</p></details>
    <a class="mp-btn mt-2" href="{{ route('backend.player-performance.membership-preview.detail', ['player' => $player['id'], 'cohort' => $player['cohort']]) }}">View results &amp; evidence</a>
</article>
@empty
<div class="mp-card"><h2>No player cards available</h2><p class="mp-subtle mb-0">@if($reason)Cards will appear when the saved data can be safely displayed.@else No saved profiles match these filters or page.@endif</p></div>
@endforelse
</div>
<p class="mp-subtle mt-3">{{ $players->total() }} profiles match these filters · up to 25 per page.</p>
{{ $players->links('pagination::bootstrap-5') }}
@if($unavailable_count && !$reason)<p class="mp-subtle mt-3">{{ $unavailable_count }} of the {{ $total_count }} saved cohort profiles have invalid or unavailable evidence and are withheld.</p>@endif
@include('backend.player-performance.membership-preview.rules')
@include('backend.player-performance.membership-preview.footer')
</div>
@endsection
