<section class="card dashboard-events-card mb-4" aria-labelledby="upcoming-events-title">
  <div class="card-header dashboard-section-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
      <h5 class="mb-1" id="upcoming-events-title">Upcoming events</h5>
      <p class="text-muted small mb-0">Published Cape Tennis events that are coming up or currently under way.</p>
    </div>
    <a class="btn btn-sm btn-outline-primary align-self-start align-self-sm-center" href="{{ route('events.index') }}">View all events</a>
  </div>
  <div class="card-body">
    @forelse($upcomingEvents->chunk(2) as $eventRow)
      <div class="row g-3 {{ $loop->last ? '' : 'mb-3' }}">
        @foreach($eventRow as $event)
          <div class="col-12 col-md-6">
            <article class="upcoming-event-card d-flex flex-column">
              <div class="d-flex flex-wrap gap-2 mb-2">
                @if($event->eventTypeModel?->name)
                  <span class="badge bg-label-primary">{{ $event->eventTypeModel->name }}</span>
                @endif
                @if($event->series?->name)
                  <span class="badge bg-label-secondary">{{ $event->series->name }}</span>
                @endif
              </div>
              <h5 class="mb-2">{{ $event->name }}</h5>
              <p class="text-muted small mb-3">
                <i class="ti ti-calendar me-1" aria-hidden="true"></i>
                @if($event->start_date && $event->end_date && ! $event->start_date->isSameDay($event->end_date))
                  {{ $event->start_date->format('d M Y') }} – {{ $event->end_date->format('d M Y') }}
                @else
                  {{ $event->start_date?->format('d M Y') ?? 'Date to be confirmed' }}
                @endif
              </p>
              <a class="btn btn-sm btn-primary mt-auto align-self-start" href="{{ route('events.show', $event) }}">View event</a>
            </article>
          </div>
        @endforeach
      </div>
    @empty
      <div class="text-center py-4">
        <span class="badge bg-label-secondary p-3 mb-3"><i class="ti ti-calendar-off ti-lg" aria-hidden="true"></i></span>
        <h5>No upcoming events published</h5>
        <p class="text-muted mb-0">Check back later for newly published Cape Tennis events.</p>
      </div>
    @endforelse
  </div>
</section>
