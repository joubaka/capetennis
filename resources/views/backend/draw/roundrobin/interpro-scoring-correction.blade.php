@extends('layouts.backend')
@section('title','Review Interpro deciding tiebreak rules')
@section('content')
<div class="card"><div class="card-header"><h4>Review deciding tiebreak rules — {{ $draw->drawName }}</h4></div><div class="card-body">
  <p>Both round robin and playoffs will use two full sets starting at 2–2. At one set all, play a 10-point match tiebreak from 0–0, won by two points.</p>
  <p>{{ $review['compatible'] }} recorded results are compatible. Unchanged results, fixtures, scheduled times and publication will be retained. Any explicit score or winner change is listed below. Scores are never converted automatically.</p>
  @if($review['incompatible'])
    <div class="alert alert-warning">Some recorded scores need individual review. No scoring rules can be changed through this review until they are compatible.</div>
    <form method="GET" action="{{ route('draw.interpro-scoring-correction.show',$draw) }}">
    <ul>@foreach($review['incompatible'] as $problem)<li class="mb-3">Match {{ $problem['match_nr'] ?? $problem['fixture_id'] }} ({{ $problem['stage'] }}): {{ $problem['home'] }} v {{ $problem['away'] }}. {{ $problem['reason'] }}
      @if($problem['can_correct_third'])
        <div class="d-flex flex-wrap gap-2 mt-2"><label>{{ $problem['home'] }} points<input class="form-control" style="max-width:9rem" type="number" min="0" max="999" name="third_set_points[{{ $problem['fixture_id'] }}][0]"></label><label>{{ $problem['away'] }} points<input class="form-control" style="max-width:9rem" type="number" min="0" max="999" name="third_set_points[{{ $problem['fixture_id'] }}][1]"></label></div>
      @endif
    </li>@endforeach</ul>
    <p>Enter the actual recorded tiebreak points in player order. A stored 7–6 is never interpreted or converted automatically; the first two set rows remain unchanged.</p>
    <button class="btn btn-outline-primary">Review supplied points</button></form>
  @elseif($review['already_applied'] && !$review['corrections'])
    <div class="alert alert-success">These scoring rules are already selected for both stages.</div>
  @else
    <form method="POST" action="{{ route('draw.interpro-scoring-correction.store',$draw) }}">@csrf
      <input type="hidden" name="revision" value="{{ $review['revision'] }}">
      @foreach($review['corrections'] as $correction)
        <p>Match {{ $correction['match_nr'] ?? $correction['fixture_id'] }}: {{ $correction['home'] }} v {{ $correction['away'] }}. Third set {{ implode('–',$correction['before']) }} → {{ implode('–',$correction['after']) }}. Winner: {{ $correction['previous_winner_name'] }} → {{ $correction['winner_name'] }}.</p>
        @foreach($correction['after'] as $side=>$points)<input type="hidden" name="third_set_points[{{ $correction['fixture_id'] }}][{{ $side }}]" value="{{ $points }}">@endforeach
      @endforeach
      @if($review['winner_changes'])<label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="acknowledge_winner_change" value="1" required>I confirm the displayed winner change from the actual tiebreak points.</label>@endif
      <button type="submit" class="btn btn-primary">Apply reviewed rules to both stages</button>
    </form>
  @endif
  <a class="btn btn-link mt-2" href="{{ route('backend.draw.roundrobin.show',$draw) }}">Back to draw</a>
</div></div>
@endsection
