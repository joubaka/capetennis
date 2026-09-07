<div class="row">
  <div class="col-xl-8 col-lg-7 col-md-7">
    @include('frontend.event.partials.event-information')
    @include('frontend.event.partials.event-announcements')
  </div>

  <div class="col-xl-4 col-lg-5 col-md-5">
    @include('frontend.event.partials.event-about')

    <div class="card event-section-card mb-4">
      <div class="card-header">
        <h5 class="mb-1">Event documents</h5>
        <p class="text-muted small mb-0">Downloads supplied by the organiser.</p>
      </div>
      <div class="card-body">
        @forelse($event->files as $file)
          <div class="event-document mb-2">
            <a class="d-flex align-items-center gap-2 text-break"
               href="{{ route('events.documents.show', [$event, $file]) }}">
              <i class="ti ti-file-description fs-4 text-primary"></i>
              <span>{{ $file->name }}</span>
            </a>
          </div>
        @empty
          <div class="text-center py-3">
            <i class="ti ti-file-off fs-2 text-muted"></i>
            <p class="text-muted small mb-0 mt-2">No event documents are available yet.</p>
          </div>
        @endforelse
      </div>
    </div>

    @if($event->results_published == 1)
      <div class="card event-section-card mb-4">
        <div class="card-header"><small class="text-uppercase">Results</small></div>
        <div class="card-body">
          <a href="{{ route('events.results', $event->id) }}" class="btn bg-label-success btn-sm">
            <i class="ti ti-trophy me-1"></i> View Results
          </a>
        </div>
      </div>
    @endif
  </div>
</div>
