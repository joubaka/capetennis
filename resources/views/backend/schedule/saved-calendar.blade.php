@extends('layouts.backend')
@section('title', 'Saved schedule – '.$event->name)
@section('page-style')<style>[data-saved-calendar] nav a { flex-shrink:0; }</style>@endsection
@section('content')
<div class="container-xxl py-3" data-saved-calendar>
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><h3 class="mb-1">Saved schedule</h3><p class="text-muted mb-0">{{ $event->name }} · Add batches across the weekend. Saved changes stay private until you publish them.</p></div><a class="btn btn-primary align-self-start" href="{{ route('backend.event-venue-schedule.index', $event) }}">Schedule more matches</a></div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <nav class="d-flex gap-2 overflow-auto pb-2 mb-3" aria-label="Schedule days">
    <a class="btn text-nowrap {{ $date === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+array_replace($scope,['date'=>'all'])) }}">All days</a>
    @forelse($days as $day => $count)<a class="btn text-nowrap {{ $day === $date ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id]+array_replace($scope,['date'=>$day])) }}">{{ \Carbon\Carbon::parse($day)->format('D j M') }} <span class="badge bg-white text-primary ms-1">{{ $count }}</span></a>@empty<span class="text-muted">No saved match times yet.</span>@endforelse
  </nav>
  @if($date === 'all')
    <div class="mb-3">
      <h5 class="mb-1">Publish one day at a time</h5>
      <p class="small text-muted mb-2">Each button publishes that whole day's saved times across all venues and draws, including any updates. Other days keep their current state, except moved matches replace their previous public times. Draws and team ties must also be published for players to see their times.</p>
      <div class="row g-3">
        @foreach($days as $day => $count)
          @php($savedCount = (int) ($dailySavedCounts[$day] ?? 0))
          <div class="col-12 col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">
            <h6 class="mb-1">Day {{ $loop->iteration }} · {{ \Carbon\Carbon::parse($day)->format('D j M Y') }}</h6>
            <div class="small mb-1">{{ $savedCount }} saved {{ Str::plural('match', $savedCount) }}</div>
            <div class="small text-muted mb-3">Whole day · all venues and draws</div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $day]) }}">Review day</a>
              <form method="post" action="{{ route('backend.event-venue-schedule.calendar.publish', $event) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $revision }}">
                <input type="hidden" name="date" value="{{ $day }}">
                <button type="submit" class="btn btn-sm btn-success" @disabled($savedCount === 0)>Publish Day {{ $loop->iteration }}</button>
              </form>
            </div>
          </div></div></div>
        @endforeach
      </div>
    </div>
  @endif
  <form method="get" class="card card-body mb-3"><div class="row g-3 align-items-end">
    <div class="col-12 col-md-3"><label class="form-label" for="calendar-date">Day</label><input class="form-control" id="calendar-date" type="date" name="date" value="{{ $date === 'all' ? '' : $date }}"></div>
    <div class="col-12 col-md-4"><label class="form-label" for="calendar-venue">Venue</label><select class="form-select" id="calendar-venue" name="venue_id"><option value="">All venues</option>@foreach($venues as $venue)<option value="{{ $venue->id }}" @selected((int)($scope['venue_id']??0)===$venue->id)>{{ $venue->name }}</option>@endforeach</select></div>
    <div class="col-12 col-md-3"><label class="form-label" for="calendar-draw">Draw / age group</label><select class="form-select" id="calendar-draw" name="draw_id"><option value="">All draws</option>@foreach($draws as $draw)<option value="{{ $draw->id }}" @selected((int)($scope['draw_id']??0)===$draw->id)>{{ $draw->drawName }}</option>@endforeach</select></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100">Show schedule</button></div>
  </div></form>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><p class="text-muted mb-0">{{ $unscheduledCount }} matches remain without a day or time{{ !empty($scope['draw_id']) ? ' in this draw' : ' across the event' }}.</p><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar.audit', ['event'=>$event->id]+$scope) }}">Run schedule audit</a></div>
  <div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><strong>{{ $rows->count() }} saved matches on this view</strong><div class="small text-muted">{{ $rows->where('publication_state','Published')->count() }} published times · {{ $rows->where('publication_state','Private')->count() }} private · {{ $rows->where('publication_state','Updates private')->count() }} with private updates · {{ $rows->where('publication_state','Not publicly visible')->count() }} not publicly visible</div><div class="small text-muted">Draws and team ties must also be published before players can see their times.</div></div><div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar.preview',['event'=>$event->id]+$scope) }}">Preview public schedule</a>
    @if($date !== 'all')
      <form method="post" action="{{ route('backend.event-venue-schedule.calendar.publish',$event) }}">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@foreach($scope as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button class="btn btn-success">{{ $rows->where('publication_state','Updates private')->isNotEmpty() ? 'Publish updates for this day' : 'Publish this day' }}</button></form>
      <form method="post" action="{{ route('backend.event-venue-schedule.calendar.hide',$event) }}">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@foreach($scope as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button class="btn btn-outline-danger">Hide public times</button></form>
    @else<span class="small text-muted align-self-center">Choose a day to publish all venues or filter to a venue and draw.</span>@endif
  </div></div></div>
  <p class="small text-muted d-md-none mb-2">Swipe the match table sideways to see every column.</p>
  <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Time</th><th>Venue / court</th><th>Draw / match</th><th>Participants</th><th>Public visibility</th></tr></thead><tbody>
    @forelse($rows as $row)<tr id="match-{{ str_replace(':','-',$row['fixture_key']) }}"><td class="text-nowrap">{{ $date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5) }}</td><td>{{ $row['venue_name'] }}<div class="small text-muted">Court {{ $row['court'] }}</div></td><td>{{ $row['draw_name'] }}<div class="small text-muted">{{ ucfirst($row['fixture_kind']) }} match {{ $row['fixture_id'] }}</div></td><td><x-scheduled-participants :row="$row" /></td><td><span class="badge {{ $row['publication_state']==='Published'?'bg-label-success':'bg-label-warning' }}">{{ $row['publication_state'] }}</span></td></tr>@empty<tr><td colspan="5" class="text-muted py-4">No saved matches match these filters. Schedule another batch to add matches here.</td></tr>@endforelse
  </tbody></table></div></div>
  @if($retained->isNotEmpty())<div class="alert alert-info mt-3"><strong>Previous public times are still visible</strong><p class="mb-2 small">These match times were moved or removed privately. Publish updates or hide this day and venue to change what players see.</p>@foreach($retained as $row)<div class="small">{{ $row['draw_name'] }} · match {{ $row['fixture_id'] }} · {{ $date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5) }} · {{ $row['venue_name'] }} / {{ $row['court'] }}</div>@endforeach</div>@endif
</div>
@endsection
