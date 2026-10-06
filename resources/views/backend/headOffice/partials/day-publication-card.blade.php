@php
  $fullyPublished = $counts['status'] === 'Published';
  $dayAction = $fullyPublished ? 'hide' : 'publish';
  $dayActionLabel = $fullyPublished ? 'Hide day' : (($counts['published'] > 0 || $counts['status'] === 'Updates not published') ? 'Publish updates' : 'Publish day');
@endphp
<div class="col-12 col-md-6 col-xl-4" data-day-card data-date="{{ $day }}"><div class="border rounded p-3 h-100">
  <h6 data-day-label>{{ $day ? \Carbon\Carbon::parse($day)->format('l j M Y') : '' }}</h6>
  <p class="mb-2"><strong data-day-status>{{ $counts['status'] }}</strong></p>
  <p class="small mb-1" data-day-counts>{{ $counts['saved'] }} saved match times · {{ $counts['published'] }} published snapshot times</p>
  <p class="small mb-1" data-day-pending>{{ $counts['matched'] }} saved times match the published snapshots · {{ $counts['pending'] }} matches with pending changes</p>
  <p class="small text-muted">Whole day · all venues and draws</p>
  <div class="d-flex flex-wrap gap-2 align-items-start">
    <a class="btn btn-sm btn-outline-primary" data-day-review href="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $day]) }}">Review day</a>
    <form method="post" data-day-action-form data-main-day-action action="{{ route('backend.event-venue-schedule.calendar.'.$dayAction, $event) }}">
      @csrf<input type="hidden" name="date" value="{{ $day }}"><input type="hidden" name="revision" value="{{ $schedulePublicationRevision }}">
      <button type="submit" data-day-toggle class="btn btn-sm {{ $fullyPublished ? 'btn-outline-danger' : 'btn-success' }}" aria-pressed="{{ $fullyPublished ? 'true' : 'false' }}" data-disabled="{{ ($fullyPublished ? $counts['published'] === 0 : $counts['saved'] === 0) ? 'true' : 'false' }}" @disabled($fullyPublished ? $counts['published'] === 0 : $counts['saved'] === 0)>{{ $dayActionLabel }}</button>
    </form>
    <details data-day-hide-menu @if($fullyPublished || $counts['published'] === 0) hidden @endif>
      <summary class="btn btn-sm btn-outline-secondary">More</summary>
      <form class="mt-2" method="post" data-day-action-form action="{{ route('backend.event-venue-schedule.calendar.hide', $event) }}">
        @csrf<input type="hidden" name="date" value="{{ $day }}"><input type="hidden" name="revision" value="{{ $schedulePublicationRevision }}">
        <button type="submit" class="btn btn-sm btn-outline-danger" data-disabled="{{ $counts['published'] === 0 ? 'true' : 'false' }}" @disabled($counts['published'] === 0)>Hide times</button>
      </form>
    </details>
  </div>
</div></div>
