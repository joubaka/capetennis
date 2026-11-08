@php
    $configData = Helper::appClasses();
@endphp
@extends('layouts.backend')
@section('title', 'Player performance')
@section('content')
<div class="operational-page mx-auto" style="max-width: 72rem; font-size: 1rem; line-height: 1.55">
@include('backend.partials.operational-controls')

<header class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <p class="text-body-secondary mb-1">Player performance</p>
        <h1 class="h2 mb-1">{{ $player->name }} {{ $player->surname }}</h1>
        <p class="text-body-secondary mb-0">Evidence as of {{ $performance['as_of'] }}</p>
    </div>
    <a class="btn btn-outline-primary" href="{{ route('backend.player-performance.directory') }}">Find another player</a>
</header>
@include('backend.player-performance.ability-card', ['performanceDetailPage' => true])
@include('backend.player-performance.card', ['performanceDetailPage' => true])
<div id="rating-evidence">
@if($ability['cohorts']->isNotEmpty())
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Age-specific comparison evidence</h2>
    <p>See what the player’s results show within each age group. Compare these estimates only within the same age and comparison group. The singles CTR above is shown separately.</p>
    @foreach($ability['cohorts'] as $estimate)
        <section class="border rounded p-3 mb-3">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
            <h3 class="h5 mb-0">{{ $estimate['cohort'] }}</h3>
            <strong class="fs-5">{{ $estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100' }}</strong>
        </div>
        <p class="mt-2 mb-2"><strong>{{ \App\Services\Performance\AbilityConfidenceDisplay::label((int) $estimate['confidence_index'], $estimate) }} confidence</strong> · {{ $estimate['played'] }} played matches · {{ $estimate['direct_opponents'] }} different opponents</p>
        <details>
        <summary class="text-primary py-2">See evidence and comparable players</summary>
        <dl class="row g-2 my-2">
            @foreach(['Matches in the last 90 days' => $estimate['recent_played'], 'Events played' => $estimate['played_events'], 'Last match date (may be estimated)' => $estimate['last_direct_match'] ?? 'None'] as $label => $value)
                <div class="col-12 col-sm">
                    <dt class="small">{{ $label }}</dt>
                    <dd class="fw-semibold mb-0">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <h4 class="h6 mt-3">Estimates within this group</h4>
        <ul class="list-group list-group-flush mb-3">
            @include('backend.player-performance.partials.evidence-score-bar', ['name' => $player->name.' '.$player->surname, 'score' => $estimate['score'], 'currentPlayer' => true, 'profileUrl' => null])
            @forelse($estimate['comparators'] as $comparator)
                @include('backend.player-performance.partials.evidence-score-bar', ['name' => $comparator['name'], 'score' => $comparator['score'], 'currentPlayer' => false, 'profileUrl' => route('backend.player-performance.show', $comparator['id'])])
            @empty
                <li class="list-group-item">No comparable players in this sample.</li>
            @endforelse
        </ul>
        <details class="mb-2">
        <summary>Evidence sources and calculation details</summary>
        <p class="mt-2">Comparison group {{ $estimate['component'] }}</p>
        <p class="small">{{ $estimate['confidence_explanation'] ?? '' }}</p>
        <p>Own same-cohort evidence: {{ $estimate['recent_played'] }} matches in the last 90 days; {{ number_format($estimate['effective_played'], 2) }} recency-weighted matches; {{ $estimate['direct_opponents'] }} distinct opponents across {{ $estimate['played_events'] }} played events.</p>
        <p>Last direct-match date (schedule/event proxy): {{ $estimate['last_direct_match'] ?? 'None' }}. Last eligible evidence: {{ $estimate['last_eligible_activity'] ?? 'None' }}. Confidence as of {{ $estimate['confidence_as_of'] }}.</p>
        <p>{{ $estimate['baseline_status'] ?? 'No connected main-trial baseline' }}.
            @if($estimate['baseline_source'] ?? null)
                {{ $estimate['baseline_source']['event_name'] }} · {{ $estimate['baseline_source']['date'] }} · {{ $estimate['baseline_source']['covered_players'] }}/{{ $estimate['baseline_source']['trial_players'] }} trial players in the largest connected benchmark group. This is a model-derived trial-match reference, not a published finishing rank.
            @else
                This local estimate has no connected main-trial reference. Compare only within this cohort and comparison group.
            @endif
        </p>
        <p class="small">Low, Medium and High describe the strength and freshness of recorded evidence, not rating accuracy or win probability. Inactivity reduces it daily. Dates use the match schedule where credible, otherwise conservative event start dates ({{ $estimate['proxy_dated_matches'] }} matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        <p>This player: {{ $estimate['played'] }} played matches; {{ $estimate['inferred'] }} weaker finish-order comparisons. Comparison group: {{ $estimate['component_players'] }} players, {{ $estimate['component_played'] }} played matches, {{ $estimate['component_inferred'] }} inferred comparisons. {{ $estimate['bridge_count'] }} narrow connections; {{ $estimate['division_links'] }} inferred A/B links. These links are not played matches.</p>
        <details class="mb-3"><summary>Players connecting multiple event fields</summary>
            <p>Shared entrants connect event evidence, including regional and open tournaments. Event names and regions confer no strength bonus. These are provenance samples, not claims of head-to-head matches.</p>
            @forelse($estimate['anchors'] as $anchor)
                <p><a href="{{ route('backend.player-performance.show', $anchor['id']) }}">{{ $anchor['name'] }}</a>: {{ collect($anchor['events'])->map(fn ($event) => $event['name'].' ('.$event['date'].')')->implode('; ') }}</p>
            @empty
                <p>No multi-event connection in the retained provenance sample.</p>
            @endforelse
        </details>
        </details>
        </details>
        </section>
    @endforeach
    <details><summary>How age-group estimates are calculated</summary>
        <p>The latest completed, eligible main Cavaliers trial (reviewed event type 5) provides a model-derived played-match benchmark from its largest connected group; no published trial finishing rank is invented. Trial targets are fixed, with a decaying weight-2 prior. Supporting played evidence has a per-player incident budget of one per event, so extra logging alone does not raise ability. Actual later events can adjust the reference. Unanchored groups remain local and cannot be compared as trial-calibrated.</p>
        <p>Completed public individual and verified team singles matches use a bounded score-margin target: close wins provide less separation than dominant wins. The winner target runs from 0.75 to 1 using the average normalized margin of each set; the loser receives the complement. Deciding tiebreak points are normalized separately from games. Custom or ambiguous score units retain outcome-only evidence. Match weights, event budgets and confidence counts stay unchanged. Published finishing fields supply weaker, explicitly inferred field-relative rank constraints (weight 0.2, centered target span ±1). Adjacent rank links are topology only, not played matches. Unique complete disjoint A/B fields can form one ordered field. When same-event played outcomes already connect the whole field, its rank constraint is omitted. All evidence has a 180-day half-life using own scheduled or conservative event-start dates. Open, A and B have no fixed score bands.</p>
        <p>A regularized Bradley–Terry model with separate quadratic ordinal-field constraints estimates strength relative to connected opponents and published finishing fields. Rank targets and weights are uncalibrated pilot assumptions. The fixed display transform is 100 × logistic(strength). Each comparison group is centered, so 50 is a model midpoint, not a certified standard. Different groups, ages, ball types and contexts are not comparable. Sparse results and inferred-only connections remain limited confidence; a score is withheld if calculation does not converge or processing limits are exceeded.</p>
        <p>Shared ability includes verified individual and team singles sources. Doubles and team standings remain separate. Public legacy scores are accepted only when complete canonical outcomes and exact identities can be verified; stored statuses/types are never changed. Saved snapshots refresh in the background; publication flags are checked on every request. Earlier model snapshots remain Pending until a compatible calculation is saved.</p>
    </details>
</div></div>
@else
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Age-specific comparison evidence</h2>
    <p class="mb-0">No age-specific comparison evidence is available for this player.</p>
</div></div>
@endif
</div>
<div id="performance-results">
@foreach($performance['disciplines'] as $discipline => $preview)
@php
    $wins = $preview['cohorts']->sum('wins');
    $losses = $preview['cohorts']->sum('losses');
    $matches = $wins + $losses;
@endphp
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">{{ ucfirst($discipline) }}</h2>
    @if($preview['truncated'])<p class="alert alert-warning">Limited to the latest 50 fields for this player. Older fields within the window were omitted.</p>@endif
    @if($matches)
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="bg-label-success rounded p-2 px-3"><strong class="fs-4">{{ $wins }}</strong> {{ Str::plural('win', $wins) }}</div></div>
            <div class="col-6"><div class="bg-label-danger rounded p-2 px-3"><strong class="fs-4">{{ $losses }}</strong> {{ Str::plural('loss', $losses) }}</div></div>
        </div>
        <div class="progress mb-2" style="height:.55rem" aria-hidden="true">
            <div class="progress-bar bg-success" style="width:{{ 100 * $wins / $matches }}%"></div>
            <div class="progress-bar bg-danger" style="width:{{ 100 * $losses / $matches }}%"></div>
        </div>
        <p class="small text-muted mb-2">{{ $matches }} eligible published {{ Str::plural('match', $matches) }}</p>
    @else
        <p class="mb-2">No eligible published {{ $discipline }} matches.</p>
    @endif
    <details>
    <summary class="text-primary py-2">See finishing results and head-to-head matches</summary>
    @forelse($preview['cohorts'] as $cohort)
        <p><strong>{{ number_format($cohort['score'], 1) }}/100 — provisional</strong> · {{ $cohort['cohort'] }} ({{ $cohort['band'] }}) · {{ $cohort['finish_count'] }} finishes / {{ $cohort['match_count'] }} matches ({{ $cohort['wins'] }} wins, {{ $cohort['losses'] }} losses) across {{ $cohort['count'] }} events · Latest {{ $cohort['last_played'] }}</p>
    @empty
        <p>Unrated: no eligible {{ $discipline }} results.</p>
    @endforelse
    <p class="mt-2">{{ $preview['finish_count'] }} eligible finishes and {{ $preview['match_count'] }} eligible matches counted from all published history. Evidence below shows the latest {{ $preview['evidence']->count() }} of {{ $preview['evidence_count'] }} finish records and {{ $preview['match_evidence']->count() }} of {{ $preview['match_evidence_count'] }} match records; older eligible results still count.</p>
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
        <tr><td>{{ $match['event'] }}<br>{{ $match['date'] }}</td><td>{{ $match['category'] }}<br>{{ $match['source'] }}</td><td class="{{ $match['reason'] ? '' : ($match['won'] ? 'winner-home' : 'loser-home') }}">vs {{ $match['opponents'] }}<br>{{ $match['score'] }} (this player first)<br>{{ $match['reason'] ? 'Excluded' : ($match['won'] ? 'Win' : 'Loss') }}</td><td>{{ $match['points'] === null ? '—' : number_format($match['points'], 1) }}</td><td>{{ $match['reason'] ?? 'Included' }}</td></tr>
    @empty
        <tr><td colspan="5">No publicly visible match evidence.</td></tr>
    @endforelse
    </tbody></table></div>
    @if($preview['reasons'])
        <details><summary>All-history exclusions</summary><ul>@foreach($preview['reasons'] as $reason => $count)<li>{{ $reason }}: {{ $count }} records</li>@endforeach</ul></details>
    @endif
    </details>
</div></div>
@endforeach
<details class="mb-4"><summary class="text-primary py-2">How the Performance Score is calculated</summary>
<p class="mt-2">Pilot v2 uses all published history. A scores 50–100, B 0–50, and unlabelled Open cohorts 0–100 separately. Finishes use the saved ranked field, which can exclude unranked entrants; this is not a starter count. Each event's average finish points and band-scaled match win rate contribute equally when both exist, then events are weighted with a 180-day half-life. An ongoing event uses the as-of date until its end date. A main category is A only when uniquely paired with matching B. Ages, genders, Masters, Open and team contexts remain separate. Doubles reflect partnerships. Match scores require published draws/events and verifiable completed score evidence; imported team identities are excluded.</p>
</details>
</div>
</div>
@endsection
