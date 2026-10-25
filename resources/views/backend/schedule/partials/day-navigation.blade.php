@php
  $navigationDate = $data['date'];
  $navigationStart = $event->start_date ? \Carbon\Carbon::parse($event->start_date)->startOfDay() : \Carbon\Carbon::parse($navigationDate);
  $navigationEnd = $event->end_date ? \Carbon\Carbon::parse($event->end_date)->startOfDay() : $navigationStart->copy();
@endphp
<nav class="d-flex gap-2 overflow-auto pb-2 mb-3" aria-label="Planning days" data-schedule-day-navigation>
  @for($navigationDay = $navigationStart->copy(), $navigationIndex = 0; $navigationDay->lte($navigationEnd) && $navigationIndex < 31; $navigationDay->addDay(), $navigationIndex++)
    @php($navigationTarget = $navigationDay->toDateString())
    <a style="min-height:44px;flex-shrink:0" class="btn text-nowrap {{ $navigationTarget === $navigationDate ? 'btn-primary' : 'btn-outline-primary' }}" @if($navigationTarget === $navigationDate) aria-current="date" @endif href="{{ route($mode === 'whole-tie' ? 'backend.event-venue-schedule.calendar.full-day' : 'backend.event-venue-schedule.calendar.edit-day', ['event'=>$event->id,'date'=>$navigationTarget] + ($navigationDraw ? ['draw_id'=>$navigationDraw->id] : []) + (!empty($data['origin_draw_id']) ? ['origin_draw_id'=>$data['origin_draw_id']] : [])) }}">Day {{ $navigationIndex + 1 }} - {{ $navigationDay->format('D j M') }}</a>
  @endfor
</nav>
<p class="small text-muted">Day buttons open that day's saved choices. They do not move bookings from another day. Leave this page only after saving or discarding pending edits.</p>
