@extends('layouts.backend')
@section('title', 'Saved schedule – '.$event->name)
@section('page-style')
<style>
[data-saved-calendar] nav a { flex-shrink:0; }
[data-saved-calendar] .btn, [data-saved-calendar] select { min-height:44px; }
[data-saved-calendar] summary { min-height:44px; cursor:pointer; }
[data-saved-calendar] .calendar-filters { padding:.65rem 1rem; }
[data-saved-calendar] .calendar-table td { vertical-align:middle; }
[data-saved-calendar] .calendar-table th { white-space:nowrap; }
[data-saved-calendar] .calendar-publication { border-left:3px solid #287d74; }
[data-saved-calendar] .publication-toggle { display:flex; flex-wrap:wrap; gap:4px; padding:4px; border:1px solid #dbe2eb; border-radius:10px; background:#f5f7fa; }
[data-saved-calendar] .publication-toggle form { display:flex; margin:0; flex:1; }
[data-saved-calendar] .publication-toggle .btn { width:100%; border-radius:7px; min-width:120px; }
[data-saved-calendar] .publication-toggle .btn[aria-pressed="true"] { opacity:1; box-shadow:0 1px 3px #172b4d20; }
@media (max-width:767.98px) {
  [data-saved-calendar] .calendar-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); }
  [data-saved-calendar] .calendar-table, [data-saved-calendar] .calendar-table tbody { display:block; }
  [data-saved-calendar] .calendar-table tr { display:block; padding:.75rem 1rem; border-bottom:1px solid #dbe2eb; }
  [data-saved-calendar] .calendar-table td { display:grid; grid-template-columns:6rem minmax(0,1fr); gap:.5rem; padding:.3rem 0; border:0; overflow-wrap:anywhere; white-space:normal!important; }
  [data-saved-calendar] .calendar-table td::before { content:attr(data-label); font-weight:600; }
  [data-saved-calendar] .calendar-table td[colspan] { display:block; }
}
</style>
@endsection
@section('content')
@php
  $planningDraws = !empty($scope['draw_id']) ? $draws->where('id', (int) $scope['draw_id']) : $draws;
  $teamPlanning = $planningDraws->contains(fn ($draw) => $draw->isTeamDraw()) || ($planningDraws->isEmpty() && $event->isTeam());
@endphp
@include('backend.draw.partials.guide-modal')
<div class="container-xxl py-3" data-saved-calendar>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h3 class="mb-1">Saved schedule</h3><p class="text-muted mb-0">{{ $event->name }}</p></div><a class="btn btn-outline-primary" href="{{ route('headOffice.show', $event) }}">Back to event dashboard</a></div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <nav class="d-flex gap-2 overflow-auto pb-2 mb-3" aria-label="Schedule days" data-schedule-day-navigation>
    <a class="btn text-nowrap {{ $date === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+array_replace($scope,['date'=>'all'])) }}">All days</a>
    @forelse($days as $day => $count)<a class="btn text-nowrap {{ $day === $date ? 'btn-primary' : 'btn-outline-primary' }}" @if($day === $date) aria-current="date" @endif href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id]+array_replace($scope,['date'=>$day])) }}">Day {{ $event->start_date ? (int) \Carbon\Carbon::parse($event->start_date)->startOfDay()->diffInDays(\Carbon\Carbon::parse($day)->startOfDay()) + 1 : $loop->iteration }} · {{ \Carbon\Carbon::parse($day)->format('D j M') }} <span class="badge bg-white text-primary ms-1">{{ $count }} event matches</span></a>@empty<span class="text-muted">No saved match times yet.</span>@endforelse
  </nav>
  <p class="small mb-2"><strong>Viewing:</strong> {{ $date === 'all' ? 'All days' : \Carbon\Carbon::parse($date)->format('D j M Y') }} · {{ !empty($scope['venue_id']) ? ($venues->firstWhere('id', (int) $scope['venue_id'])?->name ?? 'Selected venue') : 'All venues' }} · {{ !empty($scope['draw_id']) ? ($draws->firstWhere('id', (int) $scope['draw_id'])?->drawName ?? 'Selected draw') : 'All draws' }}</p>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary" href="{{ route('backend.event-venue-schedule.index', ['event'=>$event->id] + ($date !== 'all' ? ['date'=>$date] : []) + (!empty($scope['venue_id']) ? ['venue_id'=>$scope['venue_id']] : []) + (!empty($scope['draw_id']) ? ['draw_ids'=>[$scope['draw_id']]] : []) + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : [])) }}">{{ $date === 'all' ? 'Schedule more matches' : 'Add matches to this day' }}</a>
    <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>$date !== 'all' ? $date : ($event->start_date ? \Carbon\Carbon::parse($event->start_date)->toDateString() : now()->toDateString())] + (!empty($scope['draw_id']) ? ['draw_id'=>$scope['draw_id']] : []) + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : [])) }}">{{ $teamPlanning ? 'Replan selected rounds - whole ties' : 'Replan selected rounds - full day' }}</a>
    @if($date !== 'all' && !empty($scope['draw_id']))<a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar.edit-day', ['event'=>$event->id, 'date'=>$date, 'draw_id'=>$scope['draw_id']] + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : [])) }}">Edit saved times - this draw and day</a>@endif
  </div>
  <p class="small text-muted">{{ $teamPlanning ? 'Add matches opens position-band planning.' : 'Add matches opens match and round planning.' }} Edit saved times includes only this draw's saved matches on the selected day. {{ $teamPlanning ? 'Whole-tie' : 'Full-day' }} replanning can include unscheduled matches and draws across all venues; calendar filters do not restrict it.</p>
  @if(!empty($scope['origin_draw_id']) || !empty($scope['draw_id']))<a class="btn btn-outline-primary mb-3" href="{{ route('draw.guide.show', [$event, $draws->firstWhere('id', (int) ($scope['origin_draw_id'] ?? $scope['draw_id']))] + ($date !== 'all' ? ['schedule_date'=>$date] : [])) }}">{{ !empty($scope['origin_draw_id']) && ($scope['origin_draw_id'] != ($scope['draw_id'] ?? null)) ? 'Back to original draw' : 'Back to this draw' }} - {{ $draws->firstWhere('id', (int) ($scope['origin_draw_id'] ?? $scope['draw_id']))?->drawName }} - setup &amp; publication</a>@endif
  @php
    $publishedCount = (int) ($publicationCounts['Published'] ?? 0);
    $privateCount = (int) ($publicationCounts['Private'] ?? 0);
    $updatedCount = (int) ($publicationCounts['Updates private'] ?? 0);
    $blockedCount = (int) ($publicationCounts['Not publicly visible'] ?? 0);
    $hasPublishedTimes = $publishedCount + $updatedCount + $blockedCount + $retainedCount > 0;
    $fullyPrivate = !$hasPublishedTimes;
    $fullyPublished = $hasPublishedTimes && $privateCount === 0 && $updatedCount === 0 && ($retainedCount === 0);
    $publicationLabel = ($savedCount === 0) && ($retainedCount === 0) ? 'No saved times' : ($fullyPrivate ? 'Private' : ($privateCount > 0 ? 'Partly published' : ($updatedCount > 0 || ($retainedCount > 0) ? 'Published · updates private' : ($blockedCount > 0 ? 'Published · visibility blocked' : 'Published'))));
  @endphp
  <section class="card calendar-publication mb-3" id="calendar-publication" aria-label="Schedule publication"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><div class="d-flex flex-wrap align-items-center gap-2"><strong>Public schedule</strong><span class="badge {{ $fullyPrivate ? 'bg-label-secondary' : ($fullyPublished && $blockedCount === 0 ? 'bg-label-success' : 'bg-label-warning') }}">{{ $publicationLabel }}</span></div><div class="small text-muted mt-2">{{ $publishedCount }} published times · {{ $privateCount }} private @if($updatedCount) · {{ $updatedCount }} with private updates @endif @if($blockedCount) · {{ $blockedCount }} not publicly visible @endif @if(($retainedCount > 0)) · {{ $retainedCount }} previous public times retained @endif</div><div class="small text-muted">Draws and team ties must also be published before players can see their times.</div></div><div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar.preview',['event'=>$event->id]+$scope) }}">Preview public schedule</a>
    @if($date !== 'all')
      <span class="small text-muted w-100">Publication scope: {{ \Carbon\Carbon::parse($date)->format('D j M Y') }} · {{ !empty($scope['venue_id']) ? ($venues->firstWhere('id', (int) $scope['venue_id'])?->name ?? 'Selected venue') : 'all venues' }} · {{ !empty($scope['draw_id']) ? ($draws->firstWhere('id', (int) $scope['draw_id'])?->drawName ?? 'Selected draw') : 'all draws' }}. Updates apply to this scope. A moved match also replaces its previous public time, even on another day or at another venue.</span>
      <div class="publication-toggle" role="group" aria-label="Public match time visibility">
        <form method="post" action="{{ route('backend.event-venue-schedule.calendar.hide',$event) }}">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@foreach($scope as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button type="submit" class="btn {{ $fullyPrivate ? 'btn-primary' : 'btn-outline-secondary' }}" aria-pressed="{{ $fullyPrivate ? 'true' : 'false' }}" @disabled($fullyPrivate)><i class="ti ti-eye-off me-1" aria-hidden="true"></i>Private</button></form>
        <form method="post" action="{{ route('backend.event-venue-schedule.calendar.publish',$event) }}">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@foreach($scope as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button type="submit" class="btn {{ $fullyPublished ? 'btn-success' : 'btn-outline-success' }}" aria-pressed="{{ $fullyPublished ? 'true' : 'false' }}" @disabled($fullyPublished || (($savedCount === 0) && ($retainedCount === 0)))><i class="ti ti-world me-1" aria-hidden="true"></i>{{ $updatedCount || ($retainedCount > 0) ? 'Publish updates' : ($fullyPublished ? 'Published' : 'Publish') }}</button></form>
      </div>
    @else<span class="small text-muted align-self-center">Choose a day to publish all venues or filter to a venue and draw.</span>@endif
  </div></div></section>
  <p class="small text-muted mb-3">Saved changes stay private until published. Existing public times stay visible until you publish updates or hide them.</p>
  <details class="card calendar-filters mb-3" @if($date !== 'all' && empty($scope['draw_id'])) open @endif>
    <summary style="min-height:44px">Venue and draw filters</summary>
    @if($date !== 'all' && empty($scope['draw_id']))<p class="small mt-2 mb-0">Choose a draw to edit only its saved times for this day. To {{ $teamPlanning ? 'regroup whole ties' : 'replan rounds' }} and include unscheduled matches, use Replan selected rounds above.</p>@endif
  <form method="get" class="mt-3"><div class="row g-3 align-items-end">
    <input type="hidden" name="date" value="{{ $date }}">
    @if(!empty($scope['origin_draw_id']))<input type="hidden" name="origin_draw_id" value="{{ $scope['origin_draw_id'] }}">@endif
    <div class="col-12 col-md-5"><label class="form-label" for="calendar-venue">Venue</label><select class="form-select" id="calendar-venue" name="venue_id"><option value="">All venues</option>@foreach($venues as $venue)<option value="{{ $venue->id }}" @selected((int)($scope['venue_id']??0)===$venue->id)>{{ $venue->name }}</option>@endforeach</select></div>
    <div class="col-12 col-md-5"><label class="form-label" for="calendar-draw">Draw / age group</label><select class="form-select" id="calendar-draw" name="draw_id"><option value="">All draws</option>@foreach($draws as $draw)<option value="{{ $draw->id }}" @selected((int)($scope['draw_id']??0)===$draw->id)>{{ $draw->drawName }}</option>@endforeach</select></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100">Show schedule</button></div>
  </div></form>
  </details>
  <div class="d-flex flex-wrap justify-content-between gap-2 small text-muted mb-2"><span>{{ $savedCount }} saved matches in this view - showing {{ $rows->count() }}</span><span>{{ $unscheduledCount }} matches without a day or time{{ !empty($scope['draw_id']) ? ' in this draw' : ' across the event' }}</span></div>
  <div class="card"><div class="table-responsive"><table class="table calendar-table mb-0"><caption class="visually-hidden">Saved matches for the selected day and filters</caption><thead><tr><th scope="col">Time</th><th scope="col">Venue / court</th><th scope="col">Draw / match</th><th scope="col">Participants</th><th scope="col">Public visibility</th></tr></thead><tbody>
    @forelse($rows as $row)<tr id="match-{{ str_replace(':','-',$row['fixture_key']) }}"><td class="text-nowrap" data-label="Time"><div>{{ $date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5) }}</div></td><td data-label="Venue / court"><div>{{ $row['venue_name'] }}<div class="small text-muted">Court {{ $row['court'] }}</div></div></td><td data-label="Draw / match"><div>{{ $row['draw_name'] }}
      @if(!empty($row['match_type']))<div class="fw-semibold">{{ $row['match_type'] }}</div>@endif
      <div class="small text-muted">@if(!empty($row['round_nr']))Round {{ $row['round_nr'] }} · @endif @if(!empty($row['tie_nr']))Tie {{ $row['tie_nr'] }} · @endif Match {{ $row['match_nr'] ?? $row['fixture_id'] }}</div></div></td><td data-label="Participants"><div>
      @if($row['fixture_kind'] === 'team' && isset($row['lineup']))
        @foreach(['home', 'away'] as $side)
          @unless($loop->first)<div class="small text-muted my-1">vs</div>@endunless
          <div>@forelse($row['lineup'][$side]['players'] ?? [] as $player)@unless($loop->first) / @endunless<strong>{{ $player['name'] }}</strong>@empty<span class="text-muted">Players not assigned yet</span>@endforelse</div>
          <div class="small text-muted">{{ ($row['lineup'][$side]['region'] ?? '') ?: (($row['lineup'][$side]['region_name'] ?? '') ?: ($row['teams'][$side] ?? 'Team to be confirmed')) }}</div>
        @endforeach
      @else
        <x-scheduled-participants :row="$row" />
      @endif
    </div></td><td data-label="Visibility"><div><span class="badge {{ $row['publication_state']==='Published'?'bg-label-success':'bg-label-warning' }}">{{ $row['publication_state'] }}</span></div></td></tr>@empty<tr><td colspan="5" class="text-muted py-4">No saved matches match these filters. Use {{ $date === 'all' ? 'Schedule more matches' : 'Add matches to this day' }} above to add matches here.</td></tr>@endforelse
  </tbody></table></div></div>
  {{ $savedPages->links() }}
  @if(($retainedCount > 0))<div class="alert alert-info mt-3"><strong>Previous public times are still visible</strong><p class="mb-2 small">These match times were moved or removed privately. Publish updates or hide this day and venue to change what players see.</p>@foreach($retained as $row)<div class="small">{{ $row['draw_name'] }} · match {{ $row['fixture_id'] }} · {{ $date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5) }} · {{ $row['venue_name'] }} / {{ $row['court'] }}</div>@endforeach</div>@endif
  {{ $retainedPages->links() }}
  <details class="card card-body mt-3" id="calendar-tools"><summary style="min-height:44px">More schedule tools</summary>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><p class="text-muted mb-0">{{ $unscheduledCount }} matches remain without a day or time{{ !empty($scope['draw_id']) ? ' in this draw' : ' across the event' }}.</p><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar.audit', ['event'=>$event->id]+$scope) }}">Run schedule audit</a></div>
  @if($date === 'all')
    <div class="mb-3">
      <h5 class="mb-1">Publish one day at a time</h5>
      <p class="small text-muted mb-2">Each button publishes that whole day's saved times across all venues and draws, including any updates. Other days keep their current state, except moved matches replace their previous public times. Draws and team ties must also be published for players to see their times.</p>
      <div class="row g-3">
        @foreach($days as $day => $count)
          @php($savedCount = (int) ($dailySavedCounts[$day] ?? 0))
          <div class="col-12 col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">
            <h6 class="mb-1">Day {{ $event->start_date ? (int) \Carbon\Carbon::parse($event->start_date)->startOfDay()->diffInDays(\Carbon\Carbon::parse($day)->startOfDay()) + 1 : $loop->iteration }} · {{ \Carbon\Carbon::parse($day)->format('D j M Y') }}</h6>
            <div class="small mb-1">{{ $savedCount }} saved {{ Str::plural('match', $savedCount) }}</div>
            <div class="small text-muted mb-3">Whole day · all venues and draws</div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id]+array_replace($scope,['date' => $day])) }}">Edit schedule</a>
              <form method="post" action="{{ route('backend.event-venue-schedule.calendar.publish', $event) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $revision }}">
                <input type="hidden" name="date" value="{{ $day }}">
                <button type="submit" class="btn btn-sm btn-success" @disabled($savedCount === 0)>Publish Day {{ $event->start_date ? (int) \Carbon\Carbon::parse($event->start_date)->startOfDay()->diffInDays(\Carbon\Carbon::parse($day)->startOfDay()) + 1 : $loop->iteration }}</button>
              </form>
              <form method="post" action="{{ route('backend.event-venue-schedule.calendar.hide', $event) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $revision }}">
                <input type="hidden" name="date" value="{{ $day }}">
                <button type="submit" class="btn btn-sm btn-outline-danger">Hide Day {{ $event->start_date ? (int) \Carbon\Carbon::parse($event->start_date)->startOfDay()->diffInDays(\Carbon\Carbon::parse($day)->startOfDay()) + 1 : $loop->iteration }}</button>
              </form>
            </div>
          </div></div></div>
        @endforeach
      </div>
    </div>
  @endif
    @if($date !== 'all')
      <details class="mt-3"><summary style="min-height:44px">Clear saved times for this day</summary><p class="small text-muted">Clear only this day{{ !empty($scope['draw_id']) ? ' in the selected draw' : ' across all draws' }}{{ !empty($scope['venue_id']) ? ' at the selected venue' : '' }}. Fixtures and results remain. Played matches and locked draws cannot be cleared. Published times remain until you publish updates or hide them.</p>
        <form method="post" action="{{ route('backend.event-venue-schedule.calendar.clear', $event) }}" onsubmit="return confirm('Clear all saved match times in this day and selected filters? Fixtures and results remain. Published times remain until you publish updates or hide them.');">
          @csrf
          <input type="hidden" name="revision" value="{{ $revision }}">
          @foreach($scope as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
          <button class="btn btn-outline-danger" @disabled(($savedCount === 0))>Clear this day's schedule</button>
        </form>
      </details>
    @endif
@if(!empty($scope['draw_id']))
  @php($guideDraw = $event->draws()->find($scope['draw_id']))
  @if($guideDraw)<a data-draw-guide-open class="btn btn-primary m-3" href="{{ route('draw.guide.show', [$event, $guideDraw] + ($date !== 'all' ? ['schedule_date'=>$date] : [])) }}">{{ $guideDraw->isTeamDraw() ? 'Step 6 · Review setup & publication' : 'Next: review setup & publication' }}</a>@endif
@endif
  </details>

</div>
@endsection

@section('page-script')
@include('backend.schedule.partials.day-navigation-script')
@endsection
