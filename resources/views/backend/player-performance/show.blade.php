@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player performance')
@section('content')
<h1 class="h3">{{ $player->name }} {{ $player->surname }}</h1>
<p><a href="{{ route('backend.player-performance.directory') }}">Find another player</a> · As of {{ $performance['as_of'] }}</p>
@include('backend.player-performance.card')
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
