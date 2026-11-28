@can('event.manage', $event)
@php
  $dayPublicationLabels = [];
  if ($easyDayPublication ?? false) {
    $today = \Carbon\Carbon::today(config('app.timezone'));
    foreach (($wholeDaySchedule ?? collect())->keys() as $date) {
      $label = \Carbon\Carbon::parse($date)->format('l j M Y');
      $prefix = $date === $today->toDateString() ? 'Today · ' : ($date === $today->copy()->addDay()->toDateString() ? 'Tomorrow · ' : '');
      $dayPublicationLabels[$date] = $prefix.$label;
    }
  }
@endphp
@include('backend.schedule.partials.day-publication-script')
<details class="card mb-4 no-print event-publication-panel" @if(($easyDayPublication ?? false) || ($schedulePublicationUnconfirmed ?? false)) open @endif data-whole-day-publication data-easy-day-publication="{{ ($easyDayPublication ?? false) ? 'true' : 'false' }}" data-event-days="{{ json_encode($dayPublicationLabels) }}" data-initial-unconfirmed="{{ ($schedulePublicationUnconfirmed ?? false) ? 'true' : 'false' }}" data-event-id="{{ $event->id }}" data-status-url="{{ route('backend.event-venue-schedule.calendar.publication-status', $event) }}" data-publish-url="{{ route('backend.event-venue-schedule.calendar.publish', $event) }}" data-hide-url="{{ route('backend.event-venue-schedule.calendar.hide', $event) }}" data-calendar-url="{{ route('backend.event-venue-schedule.calendar', $event) }}">
  <summary class="p-3"><span class="fw-semibold">{{ ($easyDayPublication ?? false) ? 'Show or hide match times by day' : 'Whole-day schedule publication' }}</span><span class="small text-muted ms-2">{{ ($easyDayPublication ?? false) ? 'All event draws' : 'Review and publish match times' }}</span></summary>
  <div class="card-body">
    <p class="text-muted">Review, publish or hide one whole day's match times across all venues and draws. Publishing times does not publish hidden draws; existing public visibility rules still apply.</p>
    @if($easyDayPublication ?? false)<p class="small text-muted">Hide today’s times and leave tomorrow published when players only need the next day’s schedule. Hiding times keeps saved bookings, matches and results.</p>@endif
    <div class="alert {{ ($schedulePublicationUnconfirmed ?? false) ? 'alert-warning' : 'd-none' }}" data-day-feedback role="status" aria-live="polite">@if($schedulePublicationUnconfirmed ?? false)Publication status unconfirmed. Actions are paused; retry the status check before changing a day.@endif</div>
    <button type="button" class="btn btn-outline-primary mb-3" data-day-status-retry @if(!($schedulePublicationUnconfirmed ?? false)) hidden @endif>Retry status check</button>
    <div class="row g-3" data-day-cards>
      @forelse($wholeDaySchedule ?? collect() as $day => $counts)
        @include('backend.headOffice.partials.day-publication-card')
      @empty
        @unless($schedulePublicationUnconfirmed ?? false)<p class="text-muted mb-0"><strong>Not scheduled</strong> · No saved or published match times yet. Save a schedule before publishing a day.</p>@endunless
      @endforelse
    </div>
    <template data-day-card-template>
      @include('backend.headOffice.partials.day-publication-card', ['day' => '', 'counts' => ['saved' => 0, 'published' => 0, 'matched' => 0, 'pending' => 0, 'status' => 'Not scheduled']])
    </template>
  </div>
</details>
@endcan
