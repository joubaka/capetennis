@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Masters qualification review')
@section('content')
<div class="card masters-review"><div class="card-body">
<style>.masters-review h1,.masters-review h2,.masters-review p{overflow-wrap:anywhere}.masters-review .division-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:.5rem}.masters-review .division-grid a{padding:.75rem;border:1px solid #ccc;border-radius:.35rem;display:block;min-height:72px}.masters-review .division-grid a[aria-current]{border:2px solid currentColor;font-weight:600}.masters-review .review-table{min-width:970px;table-layout:auto;word-break:normal;overflow-wrap:normal}.masters-review .review-table td:first-child{min-width:145px}.masters-review .review-table td:last-child{min-width:270px}.masters-review .review-table th{min-width:75px}.masters-review summary{min-height:44px;cursor:pointer;display:flex;align-items:center}.masters-review input,.masters-review button{min-height:44px}.masters-review .table-responsive:focus-visible{outline:2px solid currentColor}</style>
<h1 class="h3">Masters qualification review</h1>
<p><a class="btn btn-primary" href="#masters-top12">View Top 12</a></p>
<style>.masters-compact-list{font-size:1rem}.masters-compact-row{display:grid;grid-template-columns:2.25rem minmax(0,1fr) 3rem;gap:.5rem;align-items:start;padding:.65rem .4rem;border-bottom:1px solid #ddd;line-height:1.35}.masters-compact-heading{font-size:.85rem;font-weight:600}.masters-compact-name{min-width:0;overflow-wrap:break-word}.masters-compact-name small{font-size:.78rem;margin-top:.15rem}.masters-compact-score,.masters-compact-heading>span:last-child{text-align:right}.masters-cutoff-row{background:#fff5d6;color:#332b14}#masters-top12{scroll-margin-top:100px}@media(max-width:400px){.masters-review>.card-body{padding:.75rem}.masters-review section{padding:.65rem!important}.masters-compact-row{gap:.3rem;padding:.6rem .2rem}}</style>
<p>Plan the provisional Top 10 in U10–U13, boys and girls. This screen does not select qualifiers, send invitations, create an event or change ratings.</p>
<div class="alert alert-warning">Review only: qualification rules, younger-player policy and primary-school attendance still need confirmation. Separate comparison groups cannot be ranked against each other.</div>
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="get" class="row g-2 mb-3"><input type="hidden" name="cohort" value="{{ $selected }}"><div class="col-sm-6"><label for="masters-date" class="form-label">Proposed Masters date</label><input class="form-control" id="masters-date" name="date" type="date" value="{{ $qualificationDate }}" required></div><div class="col-sm-6 d-flex align-items-end"><button class="btn btn-outline-primary">Review this date</button></div></form>
<p class="small">Age check: calendar year {{ substr($qualificationDate, 0, 4) }} minus recorded birth year. This is a provisional maximum-age convention, not an approved qualification rule. Changing the proposed date does not recalculate ratings.</p>
@if($snapshot['reason'])<div class="alert alert-info">{{ $snapshot['reason'] }} No qualification assessment is available.</div>
@else
<p>Saved calculation: {{ $snapshot['built_at'] }} · source date {{ $snapshot['snapshot_as_of'] ?? 'unknown' }}. <a href="{{ route('backend.player-performance.ratings') }}">Open private rating leaderboard</a></p>
@if($snapshot['snapshot_stale'] ?? true)<div class="alert alert-warning">The saved snapshot is stale. Displayed positions are diagnostic only and must not be used to finalize qualification. This page does not request a refresh.</div>@endif
@if($refreshStatus['pending'] || $refreshStatus['failed'])<div class="alert alert-warning">A rating update is pending or has failed. These saved positions must not be used to finalize qualification. Opening this review does not start an update.</div>@endif
@if($snapshot_after_date)<div class="alert alert-warning">The saved snapshot includes evidence after the proposed Masters date. It cannot establish qualification for that earlier date.</div>@endif
@endif
<h2 class="h5">1. Choose a division</h2>
<nav class="division-grid mb-4" aria-label="Masters divisions">@foreach($summary as $cohort => $item)<a href="{{ route('backend.player-performance.masters-review', ['cohort' => $cohort, 'date' => $qualificationDate]) }}#masters-top12" @if($selected === $cohort) aria-current="page" @endif>{{ strtoupper($cohort) }}<br><span class="small">{{ $item['players'] }} rated players · {{ $item['groups'] }} comparison groups</span></a>@endforeach</nav>
<h2 class="h5" id="masters-top12">2. Top 12 · {{ strtoupper($selected) }}</h2>
<p>Positions use the complete saved comparison group, ordered by displayed rating then player name. Age flags do not remove players or renumber positions. The first 10 are provisional contenders within that group only.</p>
@forelse($groups as $group)
<section class="border rounded p-3 mb-3"><h3 class="h6">Comparison group {{ $loop->iteration + (($groups->currentPage() - 1) * $groups->perPage()) }} · {{ $group['members'] }} players</h3>
<p class="small">Group reference {{ $group['id'] }}. @if(!$group['converged'])The saved fit did not converge; no qualification assessment is available.@endif</p>
<p class="small">Top 12 within this comparison group, plus any additional players tied at the Top 10 boundary. Positions 8–12 and boundary ties are highlighted.</p>
@include('backend.player-performance.masters-compact-list')
<p class="small mt-2">All players still need an eligibility review, including primary-school attendance.</p>
<details><summary>Detailed eligibility and result checks</summary>@include('backend.player-performance.masters-review-table', ['reviewRows' => $group['rows']])</details>
</section>
@empty<p>No usable saved comparison group is available for this division.</p>@endforelse
{{ $groups->links('pagination::bootstrap-5') }}
<h2 class="h5 mt-4">3. Check source gaps around the cutoff</h2>
<details><summary>Excluded results and publication gaps for visible review-band players</summary>
@foreach($evidence['notices'] as $notice)<p class="small">{{ $notice }}</p>@endforeach
<p class="small">{{ $evidence['events_checked'] }} events checked. Window: {{ $evidence['from'] ?? 'not checked' }} to {{ $evidence['as_of'] ?? 'not checked' }}. Absence from this list does not prove that all results are present.</p>
@forelse($evidence['rows'] as $issue)<article class="border rounded p-3 mb-2"><strong>{{ $issue['event'] }} · {{ $issue['date'] }}</strong><p class="mb-1">{{ $issue['status'] }} · {{ $issue['kind'] }} #{{ $issue['source_id'] }}</p><p class="small mb-1">{{ $issue['context'] ?? 'Category and comparison context not assessed for this source.' }} · {{ $issue['discipline'] ?? 'Discipline not assessed' }}</p><p class="mb-1">Players: @foreach($issue['players'] as $id){{ $snapshot['names'][$id] ?? 'Player #'.$id }}{{ !$loop->last ? ', ' : '' }}@endforeach</p><p class="mb-1">{{ $issue['reason'] }}</p><a href="{{ route('backend.player-performance.event-ratings', $issue['event_id']) }}">Review event ratings</a>@if($issue['kind'] === 'team') · <a href="{{ route('backend.player-performance.identity-review', $issue['event_id']) }}">Review identity evidence</a>@endif</article>
@empty<p>No source issues were returned by this bounded scan. This is not confirmation of complete results, verified identities or qualification.</p>@endforelse
</details>
</div></div>
@endsection
