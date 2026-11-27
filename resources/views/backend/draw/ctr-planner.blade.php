@extends('layouts.backend')
@section('title', 'CTR event planner')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'draws', 'eventWorkspaceSubtitle' => 'CTR event planner'])
<link rel="stylesheet" href="{{ asset('css/ctr-planner.css') }}?v={{ filemtime(public_path('css/ctr-planner.css')) }}">
<div id="ctr-planner" class="mx-auto" style="max-width:1100px" data-initial-step="{{ $plan?->applied_at ? 5 : ($plan ? 2 : 1) }}">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div><h1 class="h3 mb-1">Plan your CTR competitions</h1><p class="text-muted mb-0">Nearby ratings, small groups, one winner and one sportsmanship prize per competition.</p></div>
    @if($certified)<span class="badge bg-success">CTR Certified</span>@endif
  </div>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @if(!$certified)
    <div class="card card-body"><h2 class="h5">CTR certification required</h2><p>This planner is available for CTR Certified individual events.</p>
      @if(auth()->user()->hasRole('super-user'))
      <form method="POST" action="{{ route('backend.ctr-planner.certify', $event) }}">@csrf<input type="hidden" name="certified" value="1"><button class="btn btn-primary" type="submit">Mark this event CTR Certified</button><p class="small text-muted mt-2 mb-0">This records your certification of this event. Publication and result eligibility remain separate.</p></form>
      @endif
    </div>
  @else
    <nav class="ctr-steps mb-3" aria-label="CTR setup steps">
      @foreach([1 => 'Players', 2 => 'Groups', 3 => 'Scoring', 4 => 'Review and create', 5 => 'Schedule'] as $step => $label)
        <button type="button" class="btn btn-outline-primary" data-step-target="{{ $step }}" @disabled($step > 1 && !$plan || $step === 5 && !$plan?->applied_at || $plan?->applied_at && $step > 1 && $step < 5)>{{ $step }}. {{ $label }}</button>
      @endforeach
      <button type="button" class="btn btn-link" data-show-all-steps>Show all steps</button>
    </nav>
    <section class="card card-body mb-3" aria-labelledby="ctr-plan-heading" data-step-panel="1">
      <h2 class="h5">1. Review the players</h2>
      <p class="small text-muted">Check each category before planning. Only eligible, unassigned players are included.</p>
      @foreach(collect($source['rows'])->groupBy('category_id') as $categoryRows)
        <details class="mb-2"><summary>{{ $categoryRows->first()['category'] }} · {{ $categoryRows->count() }} players</summary><ul class="mt-2">@foreach($categoryRows as $row)<li>{{ $row['name'] }}</li>@endforeach</ul></details>
      @endforeach
      <h2 class="h5" id="ctr-plan-heading">Plan all draws</h2>
      <p><strong>{{ count($source['rows']) }}</strong> available entries · <strong>{{ $source['excluded'] }}</strong> already assigned · <strong>{{ $source['existing']->count() }}</strong> existing draws</p>
      <p class="small text-muted">Categories and ball levels stay separate. Groups play round robins, usually four players. After planning, choose the group sizes for each category (up to 16 players per group). A linked six-player competition adds straight-over finals and shares one winner. Review the match counts and prizes below. Existing draws are preserved.</p>
      <form method="POST" action="{{ route('backend.ctr-planner.preview', $event) }}" class="d-flex gap-3 align-items-end flex-wrap">@csrf
        <div><label class="form-label" for="ctr-target">Preferred players per group</label><select id="ctr-target" class="form-select" name="target">@foreach([3,4,5] as $size)<option value="{{ $size }}" @selected($size === (int) ($plan?->proposal['target'] ?? 4))>{{ $size }}</option>@endforeach</select></div>
        <button id="ctr-plan-all" class="btn btn-primary" type="submit" @disabled(count($source['rows']) === 0)>Plan all draws</button>
      </form>
    </section>
    @if($plan)
      @php
        $competitions = $planner->competitions($plan->proposal);
      @endphp
      @if($plan->applied_at)
        <section class="card card-body" id="ctr-created" data-step-panel="5"><h2 class="h5">5. Schedule your competitions</h2><p>Your draws are ready. {{ count($plan->draw_ids) }} competitions · {{ count($plan->draw_ids) }} winner prizes · {{ count($plan->draw_ids) }} sportsmanship prizes</p>
          <ul>@foreach(\App\Models\Draw::where('event_id', $event->id)->whereIn('id', $plan->draw_ids)->get() as $created)<li><a href="{{ route('backend.draw.roundrobin.show', $created) }}">{{ $created->drawName }}</a></li>@endforeach</ul>
          <a class="btn btn-outline-primary align-self-start" href="{{ route('headOffice.show', $event) }}">View all event draws</a>
          <a class="btn btn-primary align-self-start mt-3" href="{{ route('backend.event-venue-schedule.index', ['event' => $event, 'draw_ids' => $plan->draw_ids]) }}">Continue to court scheduling</a>
          <p class="small text-muted mt-2 mb-0">Choose venues, courts, dates and start times in the scheduling workspace. Review the round-by-round order and larger-group priority, then save privately. Schedule publication is a separate action.</p>
        </section>
      @else
        @if(($plan->proposal['version'] ?? null) !== \App\Services\Draw\CtrDrawPlanner::VERSION)
          <p class="alert alert-warning" id="ctr-old-preview">This saved preview uses an earlier grouping rule. Press Plan all draws for a fresh preview. Your saved preview has not been changed.</p>
        @else
          @include('backend.draw.ctr-group-board')
        @endif
      @endif
    @endif
    @if(auth()->user()->hasRole('super-user'))<details class="mb-4" data-super-admin-only><summary>Certification settings</summary><form method="POST" action="{{ route('backend.ctr-planner.certify', $event) }}" class="mt-2">@csrf<input type="hidden" name="certified" value="0"><button class="btn btn-outline-secondary" type="submit">Remove CTR certification</button></form></details>@endif
  @endif
</div>
<script src="{{ asset('js/ctr-planner.js') }}?v={{ filemtime(public_path('js/ctr-planner.js')) }}" defer></script>
@endsection
