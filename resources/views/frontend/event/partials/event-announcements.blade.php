@if($event->announcements->isNotEmpty())
  <div class="card event-section-card mb-4">
    <div class="card-body event-card-padding p-4">
      <div class="event-section-heading mb-4">
        <span class="event-section-icon" aria-hidden="true">
          <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
            <path d="M4 14h3l9 4V6l-9 4H4v4Zm3 0 2 5h3l-2-5m9-5a4 4 0 0 1 0 6" />
          </svg>
        </span>
        <div>
          <h5 class="mb-1">Latest announcements</h5>
          <p class="text-muted mb-0">Updates published by the event organiser.</p>
        </div>
      </div>

      @foreach($event->announcements as $announcement)
        <div class="alert alert-primary mb-3">
          @if(filled($announcement->title))
            <h6 class="alert-heading">{{ $announcement->title }}</h6>
          @endif
          <div class="event-information-content">{!! $announcement->message !!}</div>
          <div class="small text-muted mt-2">
            <i class="ti ti-clock me-1"></i>{{ optional($announcement->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif
