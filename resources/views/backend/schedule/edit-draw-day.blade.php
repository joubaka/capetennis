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
      <input type="hidden" name="court_labels_present" value="1">
      <fieldset class="mb-3"><legend class="h5">Venues for this draw and day</legend><p class="small text-muted">Choose destination venues. This does not change venue assignments for other days or age groups.</p>
        <div class="row g-3">
        @foreach($venues as $venue)
          @php($chosenCourts = old('court_labels', $values['court_labels'])[$venue->id] ?? [])
          <div class="col-12 col-md-6"><div class="border rounded p-3" data-day-venue="{{ $venue->id }}">
            <label class="form-check"><input class="form-check-input day-venue-choice" type="checkbox" name="venue_ids[]" value="{{ $venue->id }}" @checked(in_array($venue->id, old('venue_ids', $values['venue_ids'])))><span class="form-check-label">{{ $venue->name }}</span></label>
            <label class="form-label" for="court-count-{{ $venue->id }}">Number of courts for this day</label>
            <input class="form-control day-court-count mb-2" id="court-count-{{ $venue->id }}" type="number" min="1" max="{{ count($courtOptions[$venue->id] ?? []) }}" value="{{ count($chosenCourts) }}">
            <div class="d-flex flex-wrap gap-2">@foreach($courtOptions[$venue->id] ?? [] as $court)<label class="form-check"><input class="form-check-input day-court-label" type="checkbox" name="court_labels[{{ $venue->id }}][]" value="{{ $court }}" @checked(in_array($court, $chosenCourts))><span class="form-check-label">Court {{ $court }}</span></label>@endforeach</div>
            @if(empty($courtOptions[$venue->id]))<p class="small text-danger mb-0">No active event courts available.</p>@endif
          </div></div>
        @endforeach
        </div>
        <p class="small text-muted mt-2 mb-0">The count selects the first available courts. Select individual labels to use different courts. These choices apply only to this draw and day.</p>
      </fieldset>
      @if($draw->isTeamDraw())
      <fieldset class="mb-3"><legend class="h5">Position bands for this day</legend>
        <p class="small text-muted">Assign roster positions to selected destination venues. With no bands, the planner uses the selected courts normally. Missing roster positions produce a preview warning.</p>
        @if($bandNotice)<div class="alert alert-info">{{ $bandNotice }}</div>@endif
        <div id="day-position-bands">
          @foreach(array_values(old('bands', $values['bands'])) as $index=>$band)
          <div class="row g-2 align-items-end mb-2 day-band-row">
            <div class="col-6 col-md-2"><label class="form-label">From position<input class="form-control" type="number" min="1" max="100" name="bands[{{ $index }}][min_rank]" value="{{ $band['min_rank'] }}" required></label></div>
            <div class="col-6 col-md-2"><label class="form-label">To position<input class="form-control" type="number" min="1" max="100" name="bands[{{ $index }}][max_rank]" value="{{ $band['max_rank'] }}" required></label></div>
            <div class="col-12 col-md-6"><label class="form-label w-100">Destination venue<select class="form-select day-band-venue" name="bands[{{ $index }}][venue_id]" required><option value="">Choose destination venue</option>@foreach($venues as $venue)<option value="{{ $venue->id }}" @selected((int)$band['venue_id']===$venue->id)>{{ $venue->name }}</option>@endforeach</select></label></div>
            <div class="col-12 col-md-2"><button type="button" class="btn btn-outline-danger remove-day-band mb-2">Remove</button></div>
          </div>
          @endforeach
        </div>
        <button class="btn btn-outline-primary mb-3" id="add-day-band" type="button">Add position band</button>
        <label class="form-label d-block" for="day-cross-band">When players span different bands</label>
        <select class="form-select" id="day-cross-band" name="cross_band_policy"><option value="highest_ranked" @selected(old('cross_band_policy',$values['cross_band_policy'])==='highest_ranked')>Use the highest-ranked player's venue</option><option value="manual" @selected(old('cross_band_policy',$values['cross_band_policy'])==='manual')>Require manual venue placement</option></select>
      </fieldset>
      <template id="day-band-template"><div class="row g-2 align-items-end mb-2 day-band-row">
        <div class="col-6 col-md-2"><label class="form-label">From position<input class="form-control" type="number" min="1" max="100" data-band-field="min_rank" required></label></div>
        <div class="col-6 col-md-2"><label class="form-label">To position<input class="form-control" type="number" min="1" max="100" data-band-field="max_rank" required></label></div>
        <div class="col-12 col-md-6"><label class="form-label w-100">Destination venue<select class="form-select day-band-venue" data-band-field="venue_id" required><option value="">Choose destination venue</option>@foreach($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}</option>@endforeach</select></label></div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-danger remove-day-band mb-2" type="button">Remove</button></div>
      </div></template>
      @endif
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
<script>
(() => {
  const form = document.getElementById('draw-day-form');
  const invalidate = () => { const save=document.getElementById('save-day-preview'); if(save) { save.disabled=true; save.textContent='Preview again to save changes'; } };
  const syncVenues = () => {
    const selected = [...form.querySelectorAll('.day-venue-choice:checked')].map(input=>input.value);
    form.querySelectorAll('[data-day-venue]').forEach(card => {
      const enabled=selected.includes(card.dataset.dayVenue);
      card.querySelectorAll('.day-court-count, .day-court-label').forEach(input=>input.disabled=!enabled);
    });
    form.querySelectorAll('.day-band-venue').forEach(select=>{
      select.querySelectorAll('option').forEach(option=>option.disabled=option.value!=='' && !selected.includes(option.value));
      if(select.value && !selected.includes(select.value)) select.value='';
    });
  };
  form.addEventListener('input',invalidate);
  form.addEventListener('change',event=>{
    if(event.target.matches('.day-venue-choice')) syncVenues();
    const card=event.target.closest('[data-day-venue]');
    if(card && event.target.matches('.day-court-count')) card.querySelectorAll('.day-court-label').forEach((input,index)=>input.checked=index<Number(event.target.value));
    if(card && event.target.matches('.day-court-label')) card.querySelector('.day-court-count').value=card.querySelectorAll('.day-court-label:checked').length;
    invalidate();
  });
  let bandIndex={{ count(old('bands',$values['bands'])) }};
  document.getElementById('add-day-band')?.addEventListener('click',()=>{
    const list=document.getElementById('day-position-bands');
    if(list.children.length>=50) return;
    const row=document.getElementById('day-band-template').content.cloneNode(true);
    row.querySelectorAll('[data-band-field]').forEach(input=>input.name=`bands[${bandIndex}][${input.dataset.bandField}]`);
    bandIndex++;list.appendChild(row);syncVenues();invalidate();
  });
  form.addEventListener('click',event=>{ if(event.target.closest('.remove-day-band')) { event.target.closest('.day-band-row').remove();invalidate(); } });
  syncVenues();
})();
</script>
@endsection
