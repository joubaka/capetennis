@extends('layouts.backend')
@section('title', 'CTR event planner')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'draws', 'eventWorkspaceSubtitle' => 'CTR event planner'])
<style>#ctr-planner .btn,#ctr-planner .form-select{min-height:44px}#ctr-planner .card{min-width:0}#ctr-planner li{overflow-wrap:anywhere}</style>
<div id="ctr-planner" class="mx-auto" style="max-width:1100px">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div><h1 class="h3 mb-1">Plan your CTR competitions</h1><p class="text-muted mb-0">Nearby ratings, small groups, one winner and one sportsmanship prize per group.</p></div>
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
    <section class="card card-body mb-3" aria-labelledby="ctr-plan-heading">
      <h2 class="h5" id="ctr-plan-heading">Plan all draws</h2>
      <p><strong>{{ count($source['rows']) }}</strong> available entries · <strong>{{ $source['excluded'] }}</strong> already assigned · <strong>{{ $source['existing']->count() }}</strong> existing draws</p>
      <p class="small text-muted">Categories and ball levels stay separate. Groups of four are suggested, with up to five players or groups of three where needed. Every group plays a round robin with its own winner. Existing draws are preserved.</p>
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
        <section class="card card-body" id="ctr-created"><h2 class="h5">Your draws are ready</h2><p>{{ count($plan->draw_ids) }} competitions · {{ count($plan->draw_ids) }} winner prizes · {{ count($plan->draw_ids) }} sportsmanship prizes</p>
          <ul>@foreach(\App\Models\Draw::where('event_id', $event->id)->whereIn('id', $plan->draw_ids)->get() as $created)<li><a href="{{ route('backend.draw.roundrobin.show', $created) }}">{{ $created->drawName }}</a></li>@endforeach</ul>
          <a class="btn btn-outline-primary align-self-start" href="{{ route('headOffice.show', $event) }}">View all event draws</a>
        </section>
      @else
        <section class="card card-body mb-3" id="ctr-review">
          <h2 class="h5">Review player placements</h2><p class="small text-muted">Group numbers apply within each category. Change a number to move a player, then review the changes. Unavailable CTR is never treated as zero. Plans expire after 30 minutes.</p>
          <form id="ctr-placement-form" method="POST" action="{{ route('backend.ctr-planner.review', $event) }}">@csrf
            <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
            @foreach(collect($plan->proposal['rows'])->groupBy('category_id') as $categoryRows)
              <fieldset class="mb-4"><legend class="h6">{{ $categoryRows->first()['category'] }} · {{ ['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'Green/Yellow CTR'][$categoryRows->first()['pool']] ?? 'Unknown ball level' }}</legend>
                @foreach($categoryRows as $row)
                  <div class="d-flex align-items-center gap-3 flex-wrap border-bottom py-2">
                    <div class="flex-grow-1" style="min-width:180px">
                      <label for="ctr-entry-{{ $row['entry_id'] }}">{{ $row['name'] }}</label>
                      <div class="small text-muted">
                        @if($row['score'] !== null)
                          CTR {{ number_format($row['score'], 1) }} · {{ $row['confidence'] }}
                          @if($row['historical_connection'])
                            · Historical connection
                          @endif
                        @else
                          {{ $row['issue'] }}
                        @endif
                      </div>
                    </div>
                    <div><label class="small" for="ctr-entry-{{ $row['entry_id'] }}">Group</label><input id="ctr-entry-{{ $row['entry_id'] }}" name="placements[{{ $row['entry_id'] }}]" class="form-control" style="width:90px;min-height:44px" type="number" min="1" max="1600" value="{{ old('placements.'.$row['entry_id'], $row['group'] ?: '') }}" required></div>
                  </div>
                @endforeach
              </fieldset>
            @endforeach
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="manual_reviewed" value="1" @checked(old('manual_reviewed', $plan->proposal['manual_reviewed']))><span class="form-check-label">I reviewed manual placements, including unavailable CTR or different comparison groups.</span></label>
            <button id="ctr-review-changes" class="btn btn-outline-primary" type="submit">Review changed placements</button>
          </form>
        </section>
        <section id="ctr-proposal" aria-labelledby="ctr-proposal-heading">
          <h2 class="h5" id="ctr-proposal-heading">Proposed competitions</h2>
          @foreach($competitions as $competition)
            <article class="card card-body mb-2"><h3 class="h6">{{ $competition['name'] }}</h3><p class="small mb-1">{{ count($competition['rows']) }} players · {{ count($competition['rows']) * (count($competition['rows']) - 1) / 2 }} round-robin matches · 1 winner prize + 1 sportsmanship prize</p><ul class="mb-0">@foreach($competition['rows'] as $row)<li>{{ $row['name'] }}</li>@endforeach</ul></article>
          @endforeach
          <p class="fw-semibold" id="ctr-prize-total">{{ count($competitions) }} winner prizes + {{ count($competitions) }} sportsmanship prizes = {{ count($competitions) * 2 }} prizes</p>
          @php
            $ready = true;
            try { $planner->validateProposal($plan->proposal); } catch (\Illuminate\Validation\ValidationException $e) { $ready = false; $blocked = $e->validator->errors()->first(); }
            $stale = $plan->expires_at->isPast() || !hash_equals($plan->fingerprint, $source['fingerprint']);
          @endphp
          @if(!$ready)<p class="alert alert-warning" id="ctr-blocked">{{ $blocked }}</p>@endif
          @if($stale)<p class="alert alert-warning">This plan expired or the source changed. Press Plan all draws to review a fresh proposal.</p>@endif
          <form method="POST" action="{{ route('backend.ctr-planner.apply', $event) }}" id="ctr-confirm-form" class="mb-4">@csrf
            <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
            <button id="ctr-confirm" class="btn btn-primary" type="submit" @disabled(!$ready || $stale || $errors->any())>Confirm and create all draws</button>
            <p class="small text-muted mt-2">Creates the saved proposal above. Review any edited placements first. Draws are created privately, without scheduled times or publication.</p>
          </form>
        </section>
      @endif
    @endif
    @if(auth()->user()->hasRole('super-user'))<details class="mb-4"><summary>Certification settings</summary><form method="POST" action="{{ route('backend.ctr-planner.certify', $event) }}" class="mt-2">@csrf<input type="hidden" name="certified" value="0"><button class="btn btn-outline-secondary" type="submit">Remove CTR certification</button></form></details>@endif
  @endif
</div>
<script src="{{ asset('js/ctr-planner.js') }}" defer></script>
@endsection
