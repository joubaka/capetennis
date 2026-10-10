@extends('layouts.backend')
@section('title', 'Edit draw day schedule')
@section('page-style')<style>[data-draw-day-editor] .btn, [data-draw-day-editor] .form-check { min-height:44px; } [data-draw-day-editor] .form-check {display:flex;align-items:center;gap:.5rem;padding-right:.5rem;}</style>@endsection
@section('content')
<div class="container-xxl py-3" data-draw-day-editor>
  <a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'draw_id'=>$draw->id, 'date'=>$data['date']]) }}">Back to current schedule</a>
  <h3>{{ $draw->drawName }} · {{ \Carbon\Carbon::parse($data['date'])->format('D j M Y') }}</h3>
  <p>Change venues and times for this draw's already scheduled matches on this day. Other draws and days keep their times. Played matches stay fixed. Preview first, then save. Published times change only when you publish updates.</p>
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  @if($rows->isEmpty())<div class="alert alert-info">This draw has no saved times on this day. Use <a href="{{ route('backend.event-venue-schedule.index', ['event'=>$event->id, 'draw_ids'=>[$draw->id], 'date'=>$data['date']]) }}">Add matches to this day</a> to schedule a new batch.</div>@endif
  <div class="card card-body mb-3">
    <form method="post" id="draw-day-form">
      @csrf
      <input type="hidden" name="draw_id" value="{{ $draw->id }}"><input type="hidden" name="date" value="{{ $data['date'] }}">
      <fieldset class="mb-3"><legend class="h5">Venues for this draw and day</legend><p class="small text-muted">Choose destination venues. This does not change venue assignments for other days or age groups.</p>
        <div class="d-flex flex-wrap gap-3">@foreach($venues as $venue)<label class="form-check"><input class="form-check-input" type="checkbox" name="venue_ids[]" value="{{ $venue->id }}" @checked(in_array($venue->id, old('venue_ids', $values['venue_ids'])))><span class="form-check-label">{{ $venue->name }}</span></label>@endforeach</div>
      </fieldset>
      <div class="row g-3 mb-3">
        @foreach(['start_time'=>'Start time', 'end_time'=>'Finish by'] as $key=>$label)<div class="col-6 col-lg-2"><label class="form-label" for="day-{{ $key }}">{{ $label }}</label><input class="form-control" id="day-{{ $key }}" type="time" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" required></div>@endforeach
        @foreach(['duration'=>'Match minutes', 'player_rest'=>'Player rest minutes', 'court_gap'=>'Court gap minutes'] as $key=>$label)<div class="col-6 col-lg-2"><label class="form-label" for="day-{{ $key }}">{{ $label }}</label><input class="form-control" id="day-{{ $key }}" type="number" name="{{ $key }}" min="{{ $key==='duration'?1:0 }}" max="{{ $key==='court_gap'?120:480 }}" value="{{ old($key, $values[$key]) }}" required></div>@endforeach
      </div>
      <button class="btn btn-primary" name="action" value="preview" @disabled($rows->isEmpty())>Preview this draw and day</button>
      @if($preview)
        <input type="hidden" name="revision" value="{{ $preview['revision'] }}">
        <button class="btn btn-success" name="action" value="save" id="save-day-preview" @disabled(count($preview['unscheduled'])>0 || count($preview['matches'])===0)>Save preview</button>
      @endif
    </form>
  </div>
  @if($preview)
    <div class="alert alert-info">{{ count($preview['matches']) }} proposed changes · {{ count($preview['unscheduled']) }} matches could not fit. Saved times remain unchanged until you save.</div>
    @foreach($preview['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
    @foreach($preview['unscheduled'] as $match)<div class="alert alert-warning">Match {{ $match['fixture_id'] ?? $match['match'] ?? '' }}: {{ $match['reason'] ?? 'Cannot fit in this day. Adjust the window or venues.' }}</div>@endforeach
    <h5>Proposed schedule</h5>
    <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Match</th><th>Time</th><th>Venue / court</th><th>Participants</th></tr></thead><tbody>@foreach($preview['matches'] as $match)<tr>@php($current = $rows->firstWhere('fixture_key', $match['fixture_key']))<td>Round {{ $match['round'] ?? $current['round_nr'] ?? 1 }}{{ !empty($current['tie_nr']) ? ' · Tie '.$current['tie_nr'] : '' }} · Match {{ $match['match'] ?? $current['match_nr'] ?? '' }}</td><td>{{ substr($match['scheduled_at'],11,5) }}</td><td>{{ $venues->firstWhere('id',(int)$match['venue_id'])?->name }} / {{ $match['court'] }}</td><td>@if($current)<x-scheduled-participants :row="$current" />@endif</td></tr>@endforeach</tbody></table></div>
  @endif
  <h5>Current saved schedule</h5>
  <div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Venue / court</th><th>Match</th><th>Participants</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ substr($row['scheduled_at'],11,5) }}</td><td>{{ $row['venue_name'] }} / {{ $row['court'] }}</td><td>{{ !empty($row['round_nr']) ? 'Round '.$row['round_nr'].' · ' : '' }}{{ !empty($row['tie_nr']) ? 'Tie '.$row['tie_nr'].' · ' : '' }}Match {{ $row['match_nr'] ?? '' }}</td><td><x-scheduled-participants :row="$row" /></td></tr>@endforeach</tbody></table></div>
</div>
@endsection
@section('page-script')
<script>document.getElementById('draw-day-form').addEventListener('input', () => { const save=document.getElementById('save-day-preview'); if(save) { save.disabled=true; save.textContent='Preview again to save changes'; } });</script>
@endsection
