@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Historical identity review')
@section('content')
<div class="card"><div class="card-body identity-review">
<style>.identity-review{overflow-wrap:anywhere}.identity-review input,.identity-review textarea,.identity-review button{min-height:44px}.identity-review details summary{cursor:pointer;min-height:44px}.identity-review .review-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:1rem}</style>
<h1 class="h3">Historical identity review</h1>
<p>{{ $event->name }} · <a href="{{ route('backend.player-performance.event-ratings', $event) }}">Back to event ratings</a></p>
<div class="alert alert-info">Save evidence proposals, then preview the possible rating changes. Nothing here corrects identities, changes scores, refreshes saved ratings or publishes results. Weighting stays unchanged.</div>
@if(session('status'))<div role="status" class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<h2 class="h5">1. Find the suggested player</h2>
<form method="get" class="mb-3"><label for="player-search">Player name (at least two characters)</label><div class="d-flex gap-2"><input id="player-search" name="search" value="{{ $search }}" maxlength="100" class="form-control"><button class="btn btn-outline-primary">Search</button></div></form>
@if($search)
<p class="small">Up to 20 candidates. A similar name or current link is not sufficient evidence of who played.</p>
@forelse($candidates as $player)<p class="mb-1"><strong>#{{ $player->id }} {{ $player->name }} {{ $player->surname }}</strong> · {{ $player->dateOfBirth }} · {{ $player->gender }}</p>@empty<p>No candidates found.</p>@endforelse
@endif
<h2 class="h5 mt-4">2. Record evidence for an excluded imported identity</h2>
<p>Use the original scorecard, team sheet or other independently checked record to identify the exact participant, team and fixture side. References are plain text and are never fetched automatically. Save a new proposal to revise evidence.</p>
@forelse($items as $item)
<details class="border rounded p-3 mb-3" @if(old('fixture_player_id') == $item['row']->id && old('side') == $item['side']) open @endif>
<summary><strong>Fixture #{{ $item['row']->team_fixture_id }}, side {{ $item['side'] }}: {{ $item['imported']->name }} {{ $item['imported']->surname }}</strong></summary>
<p>{{ $item['row']->fixture->draw->drawName }} · {{ $item['team']?->name ?? 'Missing source team' }} · rank {{ $item['imported']->rank }}</p>
<p>Score: {{ $item['row']->fixture->teamResults->sortBy('set_nr')->map(fn($set) => $set->team1_score.'–'.$set->team2_score)->implode(', ') }}. Current linked profile: {{ $item['imported']->player_profile ?: 'none' }}.</p>
<p class="text-warning">{{ $item['blocker'] }}</p>
<form method="post" action="{{ route('backend.player-performance.identity-proposals', $event) }}">@csrf
<input type="hidden" name="fixture_player_id" value="{{ $item['row']->id }}"><input type="hidden" name="side" value="{{ $item['side'] }}">
<input type="hidden" name="source_team_id" value="{{ $item['team']?->id }}"><input type="hidden" name="source_fingerprint" value="{{ $item['fingerprint'] }}">
<div class="review-grid"><div><label>Suggested player profile ID<input class="form-control" name="suggested_player_id" type="number" min="1" required></label></div>
<div><label>Source reference (document and page / fixture)<input class="form-control" name="reference" minlength="5" maxlength="2000" required></label></div></div>
<label class="d-block mt-2">What evidence confirms this player, team and side?<textarea class="form-control" name="evidence" minlength="30" maxlength="10000" rows="3" required></textarea></label>
<label class="d-block mt-2">Why is this later identity resolution justified?<textarea class="form-control" name="reason" minlength="15" maxlength="3000" rows="2" required></textarea></label>
<button class="btn btn-primary mt-2">Save evidence proposal</button>
</form></details>
@empty<p>No unsupported imported participants found on this page.</p>@endforelse
{{ $rows->withQueryString()->links() }}
<h2 class="h5 mt-4">3. Review proposals and preview ratings</h2>
<p>Select one revision per match side. Preview uses complete affected cohorts, with positions kept within each connected comparison group. It can take a little time.</p>
<form method="post" action="{{ route('backend.player-performance.identity-preview', $event) }}">@csrf
@forelse($proposals as $proposal)
<div class="border rounded p-3 mb-2"><label><input type="checkbox" name="proposals[]" value="{{ $proposal->id }}"> Proposal #{{ $proposal->id }} · fixture-player row #{{ $proposal->fixture_player_id }}, side {{ $proposal->side }} → player #{{ $proposal->suggested_player_id }} · {{ $proposal->cohort }}</label>
<p class="small mb-1">Recorded by user #{{ $proposal->created_by }} at {{ $proposal->created_at }}. Review proposal only; not an original attestation or approval.</p>
<details><summary>Evidence and reason</summary><p>{{ $proposal->evidence }}</p><p>Reference: {{ $proposal->reference }}</p><p>Reason: {{ $proposal->reason }}</p><p class="small">Source fingerprint: {{ $proposal->source_fingerprint }}</p></details></div>
@empty<p>No proposals saved.</p>@endforelse
@if($proposals->count())<button class="btn btn-primary">Preview selected proposals</button>@endif
</form>
{{ $proposals->withQueryString()->links() }}
</div></div>
@endsection
