@extends('layouts.backend')
@section('title', 'Scheduled matches by draw – '.$event->name)
@section('page-style')
<style>
[data-draw-match-review] .btn, [data-draw-match-review] select {min-height:44px;}
[data-draw-match-review] a:focus-visible {outline:2px solid currentColor;outline-offset:3px;}
[data-draw-match-review] .match-review-card {display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr) minmax(0,1fr);gap:1rem;}
[data-draw-match-review] .match-review-card > div, [data-draw-match-review] h3, [data-draw-match-review] h4 {min-width:0;overflow-wrap:anywhere;}
@media(max-width:767.98px){[data-draw-match-review] .match-review-card {grid-template-columns:minmax(0,1fr);gap:.65rem;}}
</style>
@endsection
@section('content')
<div class="container-xxl py-3" data-draw-match-review>
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
    <div><h3 class="mb-1">Scheduled matches by draw</h3><p class="text-muted mb-1">{{ $event->name }}</p><p class="small mb-0">Private review of actual saved matches and assigned players. Times follow date and time, then roster rank within each draw. This review does not publish any changes.</p></div>
    <div class="d-flex flex-wrap gap-2 align-self-start"><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.index',$event) }}">Back to schedule workspace</a><a class="btn btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope) }}">Review / publish times</a></div>
  </div>
  <form method="get" class="card card-body mb-3"><div class="row g-2 align-items-end">
    <input type="hidden" name="group" value="draw"><input type="hidden" name="date" value="{{ $scope['date'] }}">
    @if(!empty($scope['venue_id']))<input type="hidden" name="venue_id" value="{{ $scope['venue_id'] }}">@endif
    <div class="col-12 col-sm-8"><label for="review-draw" class="form-label">Draw / age group</label><select id="review-draw" class="form-select" name="draw_id"><option value="">All draws</option>@foreach($draws as $draw)<option value="{{ $draw->id }}" @selected((int)($scope['draw_id']??0)===$draw->id)>{{ $draw->drawName }} · {{ $counts[$draw->id]??0 }} scheduled</option>@endforeach</select></div>
    <div class="col-12 col-sm-4"><button class="btn btn-primary w-100">View scheduled matches</button></div>
  </div></form>
  <p class="text-muted">{{ $matches->total() }} scheduled matches{{ !empty($scope['draw_id'])?' in this draw':' across the event' }} · {{ $scope['date']==='all'?'all days':$scope['date'] }}{{ !empty($scope['venue_id'])?' · selected venue':' · all venues' }}. Showing {{ $matches->firstItem()??0 }}–{{ $matches->lastItem()??0 }}.</p>
  @if(!empty($scope['draw_id'])||$scope['date']!=='all'||!empty($scope['venue_id']))<a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id,'group'=>'draw','date'=>'all']) }}">View all draws and days</a>@endif
  <details class="card card-body mb-3"><summary style="min-height:44px;cursor:pointer">Scheduled totals by draw (including empty draws)</summary><div class="d-flex flex-wrap gap-2 mt-2">@foreach($draws as $draw)<a class="btn btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id,'group'=>'draw']+array_replace($scope,['draw_id'=>$draw->id])) }}">{{ $draw->drawName }} · {{ $counts[$draw->id]??0 }} scheduled in this selection</a>@endforeach</div></details>
  @forelse($matches->getCollection()->groupBy('draw_id') as $drawId=>$drawMatches)
  <section class="mb-4" aria-label="{{ $drawMatches->first()['draw_name'] }}"><h4 class="h5">{{ $drawMatches->first()['draw_name'] }} <span class="text-muted small">· {{ $counts[$drawId] }} scheduled in this selection</span></h4><div class="list-group">
    @foreach($drawMatches as $row)
    <article class="list-group-item match-review-card" id="match-{{ str_replace(':','-',$row['fixture_key']) }}">
      <div><strong>{{ \Carbon\Carbon::parse($row['scheduled_at'])->format('D j M Y · H:i') }}</strong><div>{{ $row['venue_name'] }} · Court {{ $row['court'] }}</div></div>
      <div>@if($row['fixture_kind']==='team')
        @foreach(['home','away'] as $side)@unless($loop->first)<div class="small text-muted my-1">vs</div>@endunless
        <div class="small text-muted">{{ $row['teams'][$side]??'Team to be confirmed' }}</div><div>@forelse($row['lineup'][$side]['players']??[] as $player)@unless($loop->first) / @endunless<strong>{{ $player['name'] }}</strong>@if($player['rank']) <span class="small text-muted">(rank {{ $player['rank'] }})</span>@endif @empty<span class="text-muted">Players not assigned yet</span>@endforelse</div>
        @endforeach
      @else @forelse(array_filter($row['participants']) as $name)@unless($loop->first)<span class="small text-muted"> vs </span>@endunless<strong>{{ $name }}</strong>@empty<span class="text-muted">Participants determined by draw</span>@endforelse @endif</div>
      <div><strong>{{ $row['match_type'] }}</strong><div class="small">@if($row['round_nr'])Round {{ $row['round_nr'] }} · @elseif(!empty($row['round_label']))Round {{ $row['round_label'] }} · @endif @if($row['tie_nr'])Tie {{ $row['tie_nr'] }} · @endif Match {{ $row['match_nr']??$row['fixture_id'] }}@if($row['rank']) · Rank {{ $row['rank'] }}@endif</div>@if($row['stage'])<div class="small">Stage {{ $row['stage'] }}</div>@endif<div class="small text-muted">{{ ucfirst($row['fixture_kind']) }} fixture #{{ $row['fixture_id'] }} · {{ $row['duration'] }} min</div></div>
    </article>
    @endforeach
  </div></section>
  @empty<div class="card card-body"><p class="mb-0">No scheduled matches in this selection. This draw may have matches without saved times; use the schedule workspace to review or schedule them.</p></div>@endforelse
  {{ $matches->links('pagination::bootstrap-5') }}
</div>
@endsection
