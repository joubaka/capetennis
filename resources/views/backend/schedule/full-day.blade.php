@extends('layouts.backend')
@section('title', 'Schedule a full day')
@section('content')
<div class="container-xxl py-3">
  <a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'date'=>$data['date']]) }}">Back to saved schedule</a>
  <h3>Schedule a full day</h3>
  <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Scheduling method"><span class="align-self-center">Schedule by:</span><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event'=>$event->id,'date'=>$data['date']]) }}">Position band / draw</a><span class="btn btn-primary" aria-current="page">Whole tie · full day across venues</span></nav>
  <p>{{ $event->name }} · {{ \Carbon\Carbon::parse($data['date'])->format('D j M Y') }}</p>
  <p>Schedule selected draws and rounds together across your venues. Every tie stays at one venue; the next tie may use a different venue. This uses the day's selected courts instead of position bands. Unscheduled matches are included. Matches saved on other days, played matches and unselected rounds stay fixed.</p>
  <p class="small text-muted">Preview first, then save the complete day. Published times change only when you publish updates.</p>
  <p class="small text-muted">A venue can be split into two court groups, for example courts 1–3 and 4–6. Every tie stays within one group; both groups share the same physical venue.</p>
  @if($savedNotice)<div class="alert alert-info">{{ $savedNotice }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="post" id="full-day-form" class="card card-body mb-3">
    @csrf
    <label class="form-label" for="full-day-date">Playing day</label><input class="form-control mb-3" type="date" id="full-day-date" name="date" value="{{ old('date',$data['date']) }}" required>
    <fieldset class="mb-3"><legend class="h5">Draws and rounds for this day</legend>
      <p class="small text-muted">Select the rounds that should play today. Saved times in those rounds on this day will be replanned together.</p>
      <div class="row g-3">@foreach($draws as $draw)<div class="col-12 col-md-6"><div class="border rounded p-3" data-day-draw>
        <label class="d-flex gap-2 align-items-center mb-2" style="min-height:44px"><input type="checkbox" class="form-check-input day-draw" name="draw_ids[]" value="{{ $draw->id }}" @checked(in_array($draw->id,old('draw_ids',$values['draw_ids']))) @disabled($draw->locked || empty($roundOptions[$draw->id]))><strong>{{ $draw->drawName }}</strong> @if($draw->locked)<span class="text-muted">Locked</span>@endif</label>
        <div class="d-flex flex-wrap gap-3">@forelse($roundOptions[$draw->id] ?? [] as $round)<label class="d-flex gap-2 align-items-center" style="min-height:44px"><input class="form-check-input day-round" type="checkbox" name="rounds[{{ $draw->id }}][]" value="{{ $round }}" @checked(in_array($round,old('rounds',$values['rounds'])[$draw->id] ?? []))>Round {{ $round }}</label>@empty<span class="text-muted">No matches available.</span>@endforelse</div>
      </div></div>@endforeach</div>
    </fieldset>
    <fieldset class="mb-3"><legend class="h5">Venues and courts for the full day</legend><div class="row g-3">
      @foreach($venues as $venue)<div class="col-12 col-md-6"><div class="border rounded p-3" data-day-venue>
        @php($savedGroups = old('venue_groups', $values['venue_groups'])[$venue->id] ?? [])
        <label class="d-flex gap-2 align-items-center mb-2" style="min-height:44px"><input class="form-check-input day-venue" type="checkbox" name="venue_ids[]" value="{{ $venue->id }}" @checked(in_array($venue->id,old('venue_ids',$values['venue_ids'])))><strong>{{ $venue->name }}</strong></label>
        <div class="row g-2 mb-2">@foreach(['start_time'=>'Venue starts','end_time'=>'Venue finishes by'] as $key=>$label)<div class="col-6"><label class="form-label" for="venue-{{ $venue->id }}-{{ $key }}">{{ $label }}</label><input class="form-control day-venue-hour" id="venue-{{ $venue->id }}-{{ $key }}" type="time" name="venue_windows[{{ $venue->id }}][{{ $key }}]" value="{{ old('venue_windows',$values['venue_windows'])[$venue->id][$key] ?? '' }}"></div>@endforeach</div>
        <p class="small text-muted">Leave blank to use the overall day times below. Venue hours must fit within that window; both court groups use these hours.</p>
        <div class="d-flex flex-wrap gap-3">@forelse($courtOptions[$venue->id] ?? [] as $court)<label class="d-flex gap-2 align-items-center" style="min-height:44px"><input class="form-check-input day-court" type="checkbox" name="court_labels[{{ $venue->id }}][]" value="{{ $court }}" @checked(in_array($court,old('court_labels',$values['court_labels'])[$venue->id] ?? []))>Court {{ $court }}</label>@empty<span class="text-danger">No active courts.</span>@endforelse</div>
        @if(count($courtOptions[$venue->id] ?? []) >= 2)
        <label class="d-flex gap-2 align-items-center mt-2" style="min-height:44px"><input type="checkbox" class="form-check-input day-split-venue" @checked($savedGroups)>Split into two court groups</label>
        <div class="day-court-groups mt-2" @if(!$savedGroups) hidden @endif>
          <p class="small text-muted">Assign each selected court to Group A or Group B. Both groups need at least one court.</p>
          <div class="row g-2">@foreach($courtOptions[$venue->id] as $court)
            @php($courtGroup = in_array($court,$savedGroups['B'] ?? []) ? 'B' : (in_array($court,$savedGroups['A'] ?? []) ? 'A' : ($loop->index < (int)ceil(count($courtOptions[$venue->id])/2) ? 'A' : 'B')))
            <div class="col-6" data-group-court="{{ $court }}"><label class="form-label w-100">Court {{ $court }}<select class="form-select day-group-select" aria-label="Court {{ $court }} group at {{ $venue->name }}"><option value="A" @selected($courtGroup==='A')>Group A</option><option value="B" @selected($courtGroup==='B')>Group B</option></select></label><input class="day-group-court" type="hidden" data-venue-id="{{ $venue->id }}" name="venue_groups[{{ $venue->id }}][{{ $courtGroup }}][]" value="{{ $court }}"></div>
          @endforeach</div>
        </div>
        @endif
      </div></div>@endforeach
    </div></fieldset>
    <div class="row g-3 mb-3">@foreach(['start_time'=>'Start time','end_time'=>'Finish by','duration'=>'Match minutes','player_rest'=>'Player rest minutes','court_gap'=>'Court gap minutes'] as $key=>$label)<div class="col-6 col-lg-2"><label for="full-day-{{ $key }}" class="form-label">{{ $label }}</label><input class="form-control" id="full-day-{{ $key }}" name="{{ $key }}" type="{{ str_ends_with($key,'time') ? 'time' : 'number' }}" @if(!str_ends_with($key,'time')) min="{{ $key==='duration'?1:0 }}" max="{{ $key==='court_gap'?120:480 }}" @endif value="{{ old($key,$values[$key]) }}" required></div>@endforeach</div>
    <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="action" value="preview">Preview full day</button>
    @if($preview)<input type="hidden" name="revision" value="{{ $preview['revision'] }}"><button class="btn btn-success" id="save-full-day" name="action" value="save" @disabled(count($preview['unscheduled'])>0 || count($preview['matches'])===0)>Save full day preview</button>@endif</div>
  </form>
  @if($preview)
    <div class="alert alert-info">{{ count($preview['matches']) }} proposed match times · {{ count($preview['unscheduled']) }} matches could not fit. Saved times remain unchanged until you save.</div>
    @foreach($preview['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
    @foreach($preview['unscheduled'] as $match)<div class="alert alert-warning">{{ $match['draw_name'] ?? '' }} · Match {{ $match['fixture_id'] ?? '' }}: {{ $match['reason'] ?? 'Cannot fit. Extend the day or add courts.' }}</div>@endforeach
    @foreach($preview['input']['day_venue_groups'] as $venueId=>$groups)<p><strong>{{ $venues->firstWhere('id',(int)$venueId)?->name }}</strong>: @foreach($groups as $group=>$courts){{ $loop->first ? '' : ' · ' }}Group {{ $group }} (courts {{ implode(', ',$courts) }})@endforeach</p>@endforeach
    @foreach($preview['input']['full_day_venue_windows'] as $venueId=>$window)<p><strong>{{ $venues->firstWhere('id',(int)$venueId)?->name }}</strong>: {{ substr($window['start'],11,5) }}–{{ substr($window['end'],11,5) }}</p>@endforeach
    <h5>Proposed full day</h5><div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Venue / group / court</th><th>Draw / round</th><th>Rubber / match</th><th>Tie / matchup</th></tr></thead><tbody>@foreach($preview['matches'] as $match)<tr><td class="text-nowrap">{{ substr($match['scheduled_at'],11,5) }}</td><td>{{ $venues->firstWhere('id',(int)$match['venue_id'])?->name }}@if($match['venue_group']) · Group {{ $match['venue_group'] }}@endif / {{ $match['court'] }}</td><td>{{ $match['draw_name'] }} · Round {{ $match['round'] }}</td><td>{{ $match['match'] }}<div class="small text-muted">{{ $match['stage'] }}@if($match['rank']) · Position {{ $match['rank'] }}@endif</div></td><td>@if($match['tie_nr'])<strong>Tie {{ $match['tie_nr'] }}</strong><br>@endif{{ implode(' vs ',$match['participants'] ?? []) }}</td></tr>@endforeach</tbody></table></div>
  @endif
</div>
@endsection
@section('page-script')
<script>
(() => {
  const form=document.getElementById('full-day-form');
  const sync=()=>{
    form.querySelectorAll('[data-day-draw]').forEach(card=>card.querySelectorAll('.day-round').forEach(input=>input.disabled=!card.querySelector('.day-draw').checked));
    form.querySelectorAll('[data-day-venue]').forEach(card=>{
      const enabled=card.querySelector('.day-venue').checked;
      card.querySelectorAll('.day-court, .day-venue-hour').forEach(input=>input.disabled=!enabled);
      const split=card.querySelector('.day-split-venue');
      if(!split) return;
      split.disabled=!enabled;
      card.querySelector('.day-court-groups').hidden=!split.checked;
      card.querySelectorAll('[data-group-court]').forEach(row=>{
        const selected=[...card.querySelectorAll('.day-court:checked')].some(input=>input.value===row.dataset.groupCourt);
        const select=row.querySelector('.day-group-select');
        const input=row.querySelector('.day-group-court');
        select.disabled=!enabled || !split.checked || !selected;
        input.disabled=select.disabled;
        input.name=`venue_groups[${input.dataset.venueId}][${select.value}][]`;
      });
    });
  };
  const invalidate=()=>{const save=document.getElementById('save-full-day');if(save){save.disabled=true;save.textContent='Preview again to save changes';}};
  form.addEventListener('change',event=>{
    if(event.target.matches('.day-split-venue') && event.target.checked) {
      const card=event.target.closest('[data-day-venue]');
      const selected=[...card.querySelectorAll('.day-court:checked')].map(input=>input.value);
      card.querySelectorAll('[data-group-court]').forEach(row=>{const index=selected.indexOf(row.dataset.groupCourt);if(index>=0) row.querySelector('.day-group-select').value=index<Math.ceil(selected.length/2)?'A':'B';});
    }
    sync();invalidate();
  });form.addEventListener('input',invalidate);sync();
})();
</script>
@endsection
