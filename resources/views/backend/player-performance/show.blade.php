@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player performance')
@section('content')
<h1 class="h3">{{ $player->name }} {{ $player->surname }}</h1>
<p><a href="{{ route('backend.player-performance.directory') }}">Find another player</a> · As of {{ $performance['as_of'] }}</p>
@include('backend.player-performance.ability-card')
@include('backend.player-performance.card')
@if($ability['cohorts']->isNotEmpty())
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Shared ability comparison evidence</h2>
    @foreach($ability['cohorts'] as $estimate)
        <h3 class="h5">{{ $estimate['cohort'] }} · comparison group {{ $estimate['component'] }}</h3>
        <p><strong>{{ $estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100' }}</strong> · Evidence confidence C{{ $estimate['confidence_index'] }} · {{ $estimate['confidence_band'] }}</p>
        <p>Own same-cohort evidence: {{ $estimate['recent_played'] }} matches in the last 90 days; {{ number_format($estimate['effective_played'], 2) }} recency-weighted matches; {{ $estimate['direct_opponents'] }} distinct opponents across {{ $estimate['played_events'] }} played events.</p>
        <p>Last direct-match date (schedule/event proxy): {{ $estimate['last_direct_match'] ?? 'None' }}. Last eligible evidence: {{ $estimate['last_eligible_activity'] ?? 'None' }}. Confidence as of {{ $estimate['confidence_as_of'] }}.</p>
        <p class="small">C0–100 is an evidence confidence heuristic, not an accuracy percentage or win probability. Inactivity reduces it daily. Dates use the match schedule where credible, otherwise conservative event start dates ({{ $estimate['proxy_dated_matches'] }} matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        <p>This player: {{ $estimate['played'] }} played matches; {{ $estimate['inferred'] }} weaker finish-order comparisons. Comparison group: {{ $estimate['component_players'] }} players, {{ $estimate['component_played'] }} played matches, {{ $estimate['component_inferred'] }} inferred comparisons. {{ $estimate['bridge_count'] }} narrow connections; {{ $estimate['division_links'] }} inferred A/B links. These links are not played matches.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Comparable player in this group</th><th>Shared estimate</th></tr></thead><tbody>
            @foreach($estimate['comparators'] as $comparator)
                <tr><td><a href="{{ route('backend.player-performance.show', $comparator['id']) }}">{{ $comparator['name'] }}</a></td><td>{{ $comparator['score'] === null ? 'Withheld' : number_format($comparator['score'], 1).'/100' }}</td></tr>
            @endforeach
        </tbody></table></div>
        <details class="mb-3"><summary>Players connecting multiple event fields</summary>
            <p>Shared entrants connect event evidence, including regional and open tournaments. Event names and regions confer no strength bonus. These are provenance samples, not claims of head-to-head matches.</p>
            @forelse($estimate['anchors'] as $anchor)
                <p><a href="{{ route('backend.player-performance.show', $anchor['id']) }}">{{ $anchor['name'] }}</a>: {{ collect($anchor['events'])->map(fn ($event) => $event['name'].' ('.$event['date'].')')->implode('; ') }}</p>
            @empty
                <p>No multi-event connection in the retained provenance sample.</p>
            @endforelse
        </details>
    @endforeach
    <details><summary>Shared ability method (v3)</summary>
        <p>Completed public individual singles matches carry weight 1. Published finishing order supplies weaker inferred comparisons, totalling weight 0.2 per event/cohort. Unique paired A/B fields can supply one explicitly inferred division-order link. All evidence receives the same 180-day half-life. Open, A and B have no fixed score bands here.</p>
        <p>A regularized Bradley–Terry model estimates each player's strength relative to connected opponents and field evidence. The fixed display transform is 100 × logistic(strength). Each comparison group is centered, so 50 is a model midpoint, not a certified standard. Different groups, ages, ball types and contexts are not comparable. Sparse results and inferred-only connections remain limited confidence; a score is withheld if calculation does not converge or processing limits are exceeded.</p>
        <p>Shared ability currently uses individual singles sources. Team and doubles results remain available in the separate performance pilot below. Cached snapshots last up to five minutes; publication flags are checked on every request. Score changes outside normal timestamped services can take up to five minutes to appear.</p>
    </details>
</div></div>
@endif
@foreach($performance['disciplines'] as $discipline => $preview)
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">{{ ucfirst($discipline) }}</h2>
    @if($preview['truncated'])<p class="alert alert-warning">Limited to the latest 50 fields for this player. Older fields within the window were omitted.</p>@endif
    @forelse($preview['cohorts'] as $cohort)
        <p><strong>{{ number_format($cohort['score'], 1) }}/100 — provisional</strong> · {{ $cohort['cohort'] }} ({{ $cohort['band'] }}) · {{ $cohort['finish_count'] }} finishes / {{ $cohort['match_count'] }} matches ({{ $cohort['wins'] }} wins, {{ $cohort['losses'] }} losses) across {{ $cohort['count'] }} events · Latest {{ $cohort['last_played'] }}</p>
    @empty
        <p>Unrated: no eligible {{ $discipline }} results.</p>
    @endforelse
    <p>{{ $preview['finish_count'] }} eligible finishes and {{ $preview['match_count'] }} eligible matches counted from all published history. Evidence below shows the latest {{ $preview['evidence']->count() }} of {{ $preview['evidence_count'] }} finish records and {{ $preview['match_evidence']->count() }} of {{ $preview['match_evidence_count'] }} match records; older eligible results still count.</p>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Event / date</th><th>Category / division</th><th>Finish / field</th><th>Points</th><th>Evidence</th></tr></thead>
        <tbody>
        @forelse($preview['evidence'] as $result)
            <tr><td>{{ $result['event'] }}<br>{{ $result['date'] }}</td><td>{{ $result['category'] }}<br>{{ $result['tier'] ?? 'Unknown' }} · {{ $result['division_source'] }}</td><td>{{ $result['position'] ?? 'No finish' }} / {{ $result['field_capped'] ? 'at least ' : '' }}{{ $result['field_size'] }} saved ranks<br>{{ $result['unranked_entries'] }} other unranked entries</td><td>{{ $result['points'] === null ? '—' : number_format($result['points'], 1) }}</td><td>{{ $result['reason'] ?? 'Included' }}</td></tr>
        @empty
            <tr><td colspan="5">No published finishing results or category memberships.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <h3 class="h5">Head-to-head matches</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Event / date</th><th>Category / source</th><th>Score / outcome</th><th>Points</th><th>Evidence</th></tr></thead><tbody>
    @forelse($preview['match_evidence'] as $match)
        <tr><td>{{ $match['event'] }}<br>{{ $match['date'] }}</td><td>{{ $match['category'] }}<br>{{ $match['source'] }}</td><td>vs {{ $match['opponents'] }}<br>{{ $match['score'] }} (this player first)<br>{{ $match['reason'] ? 'Excluded' : ($match['won'] ? 'Win' : 'Loss') }}</td><td>{{ $match['points'] === null ? '—' : number_format($match['points'], 1) }}</td><td>{{ $match['reason'] ?? 'Included' }}</td></tr>
    @empty
        <tr><td colspan="5">No publicly visible match evidence.</td></tr>
    @endforelse
    </tbody></table></div>
    @if($preview['reasons'])
        <details><summary>All-history exclusions</summary><ul>@foreach($preview['reasons'] as $reason => $count)<li>{{ $reason }}: {{ $count }} records</li>@endforeach</ul></details>
    @endif
</div></div>
@endforeach
<p>Pilot v2 uses all published history. A scores 50–100, B 0–50, and unlabelled Open cohorts 0–100 separately. Finishes use the saved ranked field, which can exclude unranked entrants; this is not a starter count. Each event's average finish points and band-scaled match win rate contribute equally when both exist, then events are weighted with a 180-day half-life. An ongoing event uses the as-of date until its end date. A main category is A only when uniquely paired with matching B. Ages, genders, Masters, Open and team contexts remain separate. Doubles reflect partnerships. Match scores require published draws/events and verifiable completed score evidence; imported team identities are excluded.</p>
@endsection
