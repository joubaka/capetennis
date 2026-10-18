@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Historical identity rating preview')
@section('content')
<div class="card"><div class="card-body" style="overflow-wrap:anywhere">
<h1 class="h3">Historical identity rating preview</h1>
<p>{{ $event->name }} · evidence as of {{ $preview['as_of'] }} · <a href="{{ route('backend.player-performance.identity-review', $event) }}">Back to evidence review</a></p>
<div class="alert alert-warning">Hypothetical review only. No records, publication or saved ratings changed. Positions are within each comparison group, not Masters eligibility. When group membership changes, before/after positions are not directly comparable. Primary-school status and age eligibility still require review.</div>
<p>Proposals: {{ $preview['proposals']->pluck('id')->implode(', ') }}. Newly included scored sources: {{ count($preview['added_matches']) }}.</p>
@foreach($preview['source_checks'] as $check)<p class="{{ $check['reason'] ? 'text-danger' : 'text-success' }}">Fixture #{{ $check['fixture_id'] }}: {{ $check['reason'] ?? 'Canonical source checks pass in this hypothetical preview.' }}</p>@endforeach
@foreach($preview['cohorts'] as $label => $data)
<h2 class="h4 mt-4">{{ $label }}</h2><p>Comparison groups: {{ $data['before_components'] }} before → {{ $data['after_components'] }} proposed.</p>
<h3 class="h6">Positions 8–12, Top 10 membership changes and new identities</h3>
@include('backend.player-performance.identity-preview-table', ['reviewRows' => collect($data['rows'])->filter(fn($row) => $row['cutoff'] || $row['top10_changed'] || $row['new_node'])])
<details><summary>All players in this affected cohort</summary>@include('backend.player-performance.identity-preview-table', ['reviewRows' => $data['rows']])</details>
@endforeach
<details class="mt-3"><summary>Calculation evidence</summary><p>Both calculations use the same source read, date, calculator and unchanged weighting. Selected proposals are passed only to the hypothetical calculation. No proposal is used by normal ratings.</p><p>Source fingerprint: {{ $preview['fingerprint'] }}</p></details>
</div></div>
@endsection
